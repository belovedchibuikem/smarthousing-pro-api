<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanRepayment;
use App\Models\Tenant\Member;
use App\Models\Tenant\PropertyPaymentTransaction;
use App\Models\Tenant\PropertyPaymentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BulkLoanRepaymentController extends Controller
{
    /**
     * Download CSV template for bulk loan repayment upload
     */
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'Loan ID',
            'Member ID (UUID or Staff ID)',
            'Member Name',
            'Amount',
            'Principal Paid',
            'Interest Paid',
            'Payment Date (YYYY-MM-DD)',
            'Payment Method',
            'Transaction Reference'
        ];

        $csvContent = implode(',', $template) . "\n";
        
        // Add sample data
        $sampleData = [
            'LOAN-2024-001',
            'FRSC/HMS/2024/001',
            'John Doe',
            '100000',
            '80000',
            '20000',
            '2025-01-15',
            'Bank Transfer',
            'TRX123456789'
        ];
        
        $csvContent .= implode(',', $sampleData) . "\n";

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'loan_repayments_upload_template.csv'
        ]);
    }

    /**
     * Upload and process bulk loan repayments from CSV
     */
    public function uploadBulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');
        
        if (!$handle) {
            return response()->json([
                'success' => false,
                'message' => 'Could not open file'
            ], 422);
        }

        $headers = fgetcsv($handle);
        $successful = 0;
        $failed = 0;
        $errors = [];

        $lineNumber = 1;
        
        try {
            DB::beginTransaction();

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                
                if (count($row) !== count($headers)) {
                    $errors[] = "Line {$lineNumber}: Invalid number of columns";
                    $failed++;
                    continue;
                }

                $data = array_combine($headers, $row);
                $data = array_map('trim', $data);

                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }

                try {
                    // Find loan by ID or member
                    $loanId = $data['Loan ID'] ?? null;
                    $memberId = $data['Member ID (UUID or Staff ID)'] ?? null;
                    $amount = floatval($data['Amount'] ?? 0);
                    $principalPaid = !empty($data['Principal Paid']) ? floatval($data['Principal Paid']) : null;
                    $interestPaid = !empty($data['Interest Paid']) ? floatval($data['Interest Paid']) : null;
                    $paymentDate = $data['Payment Date (YYYY-MM-DD)'] ?? now()->format('Y-m-d');
                    $paymentMethod = $data['Payment Method'] ?? 'bank_transfer';
                    $reference = $data['Transaction Reference'] ?? 'BULK-' . time() . '-' . rand(1000, 9999);

                    // Validate required fields
                    if (!$loanId || !$memberId || $amount <= 0) {
                        $errors[] = "Line {$lineNumber}: Missing required fields (Loan ID, Member ID, or Amount)";
                        $failed++;
                        continue;
                    }

                    // Find member
                    $member = Member::where('id', $memberId)
                        ->orWhere('member_number', $memberId)
                        ->orWhere('staff_id', $memberId)
                        ->first();

                    if (!$member) {
                        $errors[] = "Line {$lineNumber}: Member not found ({$memberId})";
                        $failed++;
                        continue;
                    }

                    // Find loan by ID or by member
                    $loan = Loan::where('id', $loanId)
                        ->orWhere(function($query) use ($loanId, $member) {
                            $query->where('member_id', $member->id)
                                  ->where('id', $loanId);
                        })
                        ->first();

                    // If loan ID format doesn't match, try to find by member
                    if (!$loan) {
                        $loan = Loan::where('member_id', $member->id)
                            ->where('status', 'approved')
                            ->orderBy('created_at', 'desc')
                            ->first();
                    }

                    if (!$loan) {
                        $errors[] = "Line {$lineNumber}: Loan not found for member {$memberId}";
                        $failed++;
                        continue;
                    }

                    // Check if loan is approved
                    if ($loan->status !== 'approved' && $loan->status !== 'active') {
                        $errors[] = "Line {$lineNumber}: Loan is not approved or active (Status: {$loan->status})";
                        $failed++;
                        continue;
                    }

                    // Check if loan is already fully repaid
                    $loanTotalAmount = $loan->total_amount ?? ($loan->amount + ($loan->amount * ($loan->interest_rate ?? 0) / 100));
                    $totalRepaid = $loan->repayments()->where('status', 'paid')->sum('amount');
                    $remainingAmount = $loanTotalAmount - $totalRepaid;
                    $remainingPrincipal = $loan->getRemainingPrincipal();

                    if ($remainingAmount <= 0) {
                        $errors[] = "Line {$lineNumber}: Loan is already fully repaid";
                        $failed++;
                        continue;
                    }

                    // Calculate principal and interest if not provided
                    if ($principalPaid === null || $interestPaid === null) {
                        $remainingInterest = max(0, $remainingAmount - $remainingPrincipal);
                        if ($remainingAmount > 0) {
                            $principalPaid = ($remainingPrincipal / $remainingAmount) * $amount;
                            $interestPaid = $amount - $principalPaid;
                        } else {
                            $principalPaid = 0;
                            $interestPaid = $amount;
                        }
                    }

                    // Validate principal + interest = amount
                    if (abs($principalPaid + $interestPaid - $amount) > 0.01) {
                        $errors[] = "Line {$lineNumber}: Principal Paid + Interest Paid must equal Amount";
                        $failed++;
                        continue;
                    }

                    // Validate repayment amount doesn't exceed remaining balance
                    if ($amount > $remainingAmount) {
                        $errors[] = "Line {$lineNumber}: Repayment amount ({$amount}) exceeds remaining balance ({$remainingAmount})";
                        $failed++;
                        continue;
                    }

                    // Validate principal doesn't exceed remaining principal
                    if ($principalPaid > $remainingPrincipal) {
                        $errors[] = "Line {$lineNumber}: Principal paid exceeds remaining principal balance";
                        $failed++;
                        continue;
                    }

                    // Parse payment date
                    try {
                        $paidAt = \Carbon\Carbon::parse($paymentDate);
                    } catch (\Exception $e) {
                        $errors[] = "Line {$lineNumber}: Invalid payment date format ({$paymentDate})";
                        $failed++;
                        continue;
                    }

                    // Create loan repayment record
                    $repayment = LoanRepayment::create([
                        'loan_id' => $loan->id,
                        'property_id' => $loan->property_id,
                        'amount' => $amount,
                        'principal_paid' => $principalPaid,
                        'interest_paid' => $interestPaid,
                        'due_date' => $paidAt,
                        'status' => 'paid',
                        'paid_at' => $paidAt,
                        'payment_method' => $paymentMethod,
                        'reference' => $reference,
                        'recorded_by' => $request->user()->id,
                    ]);

                    // Update loan status based on principal repayment
                    if ($loan->isFullyRepaid()) {
                        $loan->update(['status' => 'completed']);
                    } elseif ($loan->status === 'approved') {
                        $loan->update(['status' => 'active']);
                    }

                    // Create PropertyPaymentTransaction if loan is tied to property
                    if ($loan->property_id) {
                        $this->createPropertyTransaction(
                            $loan->property_id,
                            $loan->member_id,
                            $principalPaid,
                            $reference,
                            'loan',
                            $loan->id
                        );
                    }

                    $successful++;
                } catch (\Exception $e) {
                    $errors[] = "Line {$lineNumber}: " . $e->getMessage();
                    $failed++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Bulk upload failed: ' . $e->getMessage(),
                'data' => [
                    'total' => $lineNumber - 1,
                    'successful' => $successful,
                    'failed' => $failed,
                    'errors' => array_slice($errors, 0, 50),
                ]
            ], 500);
        }

        fclose($handle);

        return response()->json([
            'success' => true,
            'message' => 'Bulk loan repayments processed successfully',
            'data' => [
                'total' => $lineNumber - 1,
                'successful' => $successful,
                'failed' => $failed,
                'errors' => array_slice($errors, 0, 50),
            ]
        ]);
    }

    /**
     * Create PropertyPaymentTransaction record
     */
    private function createPropertyTransaction(
        string $propertyId,
        string $memberId,
        float $amount,
        string $reference,
        string $source,
        ?string $loanId = null
    ): void {
        $plan = PropertyPaymentPlan::where('property_id', $propertyId)
            ->whereHas('interest', function ($query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->first();

        PropertyPaymentTransaction::create([
            'property_id' => $propertyId,
            'member_id' => $memberId,
            'plan_id' => $plan?->id,
            'source' => $source,
            'amount' => $amount, // Only principal amount for property progress
            'direction' => 'credit',
            'reference' => $reference,
            'status' => 'completed',
            'paid_at' => now(),
            'metadata' => [
                'loan_id' => $loanId,
                'recorded_by_admin' => true,
            ],
        ]);
    }
}

