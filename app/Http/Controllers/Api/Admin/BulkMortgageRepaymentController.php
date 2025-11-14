<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Mortgage;
use App\Models\Tenant\MortgageRepayment;
use App\Models\Tenant\InternalMortgagePlan;
use App\Models\Tenant\InternalMortgageRepayment;
use App\Models\Tenant\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BulkMortgageRepaymentController extends Controller
{
    /**
     * Download CSV template for bulk mortgage repayment upload
     */
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'Mortgage ID',
            'Member ID (UUID or Staff ID)',
            'Member Name',
            'Amount',
            'Principal Paid',
            'Interest Paid',
            'Payment Date (YYYY-MM-DD)',
            'Payment Method (monthly/yearly/bi-yearly)',
            'Transaction Reference',
            'Notes'
        ];

        $csvContent = implode(',', $template) . "\n";
        
        // Add sample data
        $sampleData = [
            'MORT-2024-001',
            'FRSC/HMS/2024/001',
            'John Doe',
            '150000',
            '120000',
            '30000',
            '2025-01-15',
            'monthly',
            'TRX123456789',
            'Monthly mortgage repayment'
        ];
        
        $csvContent .= implode(',', $sampleData) . "\n";

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'mortgage_repayments_upload_template.csv'
        ]);
    }

    /**
     * Download CSV template for bulk internal mortgage repayment upload
     */
    public function downloadInternalTemplate(): JsonResponse
    {
        $template = [
            'Internal Mortgage Plan ID',
            'Member ID (UUID or Staff ID)',
            'Member Name',
            'Amount',
            'Principal Paid',
            'Interest Paid',
            'Payment Date (YYYY-MM-DD)',
            'Payment Method (monthly/yearly/bi-yearly)',
            'Transaction Reference',
            'Notes'
        ];

        $csvContent = implode(',', $template) . "\n";
        
        // Add sample data
        $sampleData = [
            'INT-MORT-2024-001',
            'FRSC/HMS/2024/001',
            'John Doe',
            '100000',
            '80000',
            '20000',
            '2025-01-15',
            'monthly',
            'TRX123456789',
            'Monthly cooperative deduction'
        ];
        
        $csvContent .= implode(',', $sampleData) . "\n";

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'internal_mortgage_repayments_upload_template.csv'
        ]);
    }

    /**
     * Upload and process bulk mortgage repayments from CSV
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
        $user = $request->user();

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
                    $mortgageId = $data['Mortgage ID'] ?? null;
                    $memberId = $data['Member ID (UUID or Staff ID)'] ?? null;
                    $amount = floatval($data['Amount'] ?? 0);
                    $principalPaid = floatval($data['Principal Paid'] ?? 0);
                    $interestPaid = floatval($data['Interest Paid'] ?? 0);
                    $paymentDate = $data['Payment Date (YYYY-MM-DD)'] ?? now()->format('Y-m-d');
                    $paymentMethod = $data['Payment Method (monthly/yearly/bi-yearly)'] ?? 'monthly';
                    $reference = $data['Transaction Reference'] ?? 'BULK-MORT-' . time() . '-' . rand(1000, 9999);
                    $notes = $data['Notes'] ?? null;

                    // Validate required fields
                    if (!$mortgageId || !$memberId || $amount <= 0) {
                        $errors[] = "Line {$lineNumber}: Missing required fields";
                        $failed++;
                        continue;
                    }

                    // Validate principal + interest = amount
                    if (abs($principalPaid + $interestPaid - $amount) > 0.01) {
                        $errors[] = "Line {$lineNumber}: Principal Paid + Interest Paid must equal Amount";
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

                    // Find mortgage
                    $mortgage = Mortgage::where('id', $mortgageId)
                        ->where('member_id', $member->id)
                        ->first();

                    if (!$mortgage) {
                        $errors[] = "Line {$lineNumber}: Mortgage not found for member";
                        $failed++;
                        continue;
                    }

                    if ($mortgage->status !== 'approved' && $mortgage->status !== 'active') {
                        $errors[] = "Line {$lineNumber}: Mortgage is not approved or active";
                        $failed++;
                        continue;
                    }

                    // Check if schedule has been approved by member
                    if (!$mortgage->schedule_approved) {
                        $errors[] = "Line {$lineNumber}: Repayment schedule must be approved by the member before repayments can be processed";
                        $failed++;
                        continue;
                    }

                    $remainingPrincipal = $mortgage->getRemainingPrincipal();
                    if ($principalPaid > $remainingPrincipal) {
                        $errors[] = "Line {$lineNumber}: Principal paid exceeds remaining balance";
                        $failed++;
                        continue;
                    }

                    // Parse payment date
                    try {
                        $paidAt = \Carbon\Carbon::parse($paymentDate);
                    } catch (\Exception $e) {
                        $errors[] = "Line {$lineNumber}: Invalid payment date format";
                        $failed++;
                        continue;
                    }

                    // Create repayment
                    $repayment = MortgageRepayment::create([
                        'mortgage_id' => $mortgage->id,
                        'property_id' => $mortgage->property_id,
                        'amount' => $amount,
                        'principal_paid' => $principalPaid,
                        'interest_paid' => $interestPaid,
                        'due_date' => $paidAt,
                        'status' => 'paid',
                        'paid_at' => $paidAt,
                        'payment_method' => $paymentMethod,
                        'reference' => $reference,
                        'recorded_by' => $user->id,
                        'notes' => $notes,
                    ]);

                    // Update mortgage status
                    if ($mortgage->isFullyRepaid()) {
                        $mortgage->update(['status' => 'completed']);
                    } else {
                        $mortgage->update(['status' => 'active']);
                    }

                    // Create PropertyPaymentTransaction if mortgage is tied to property
                    if ($mortgage->property_id) {
                        $this->createPropertyTransaction(
                            $mortgage->property_id,
                            $mortgage->member_id,
                            $principalPaid,
                            $reference,
                            'mortgage',
                            $mortgage->id
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
            'message' => 'Bulk mortgage repayments processed successfully',
            'data' => [
                'total' => $lineNumber - 1,
                'successful' => $successful,
                'failed' => $failed,
                'errors' => array_slice($errors, 0, 50),
            ]
        ]);
    }

    /**
     * Upload and process bulk internal mortgage repayments from CSV
     */
    public function uploadInternalBulk(Request $request): JsonResponse
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
        $user = $request->user();

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
                    $planId = $data['Internal Mortgage Plan ID'] ?? null;
                    $memberId = $data['Member ID (UUID or Staff ID)'] ?? null;
                    $amount = floatval($data['Amount'] ?? 0);
                    $principalPaid = floatval($data['Principal Paid'] ?? 0);
                    $interestPaid = floatval($data['Interest Paid'] ?? 0);
                    $paymentDate = $data['Payment Date (YYYY-MM-DD)'] ?? now()->format('Y-m-d');
                    $paymentMethod = $data['Payment Method (monthly/yearly/bi-yearly)'] ?? 'monthly';
                    $reference = $data['Transaction Reference'] ?? 'BULK-INT-MORT-' . time() . '-' . rand(1000, 9999);
                    $notes = $data['Notes'] ?? null;

                    // Validate required fields
                    if (!$planId || !$memberId || $amount <= 0) {
                        $errors[] = "Line {$lineNumber}: Missing required fields";
                        $failed++;
                        continue;
                    }

                    // Validate principal + interest = amount
                    if (abs($principalPaid + $interestPaid - $amount) > 0.01) {
                        $errors[] = "Line {$lineNumber}: Principal Paid + Interest Paid must equal Amount";
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

                    // Find plan
                    $plan = InternalMortgagePlan::where('id', $planId)
                        ->where('member_id', $member->id)
                        ->first();

                    if (!$plan) {
                        $errors[] = "Line {$lineNumber}: Internal mortgage plan not found for member";
                        $failed++;
                        continue;
                    }

                    if ($plan->status !== 'active') {
                        $errors[] = "Line {$lineNumber}: Plan is not active";
                        $failed++;
                        continue;
                    }

                    // Check if schedule has been approved by member
                    if (!$plan->schedule_approved) {
                        $errors[] = "Line {$lineNumber}: Repayment schedule must be approved by the member before repayments can be processed";
                        $failed++;
                        continue;
                    }

                    $remainingPrincipal = $plan->getRemainingPrincipal();
                    if ($principalPaid > $remainingPrincipal) {
                        $errors[] = "Line {$lineNumber}: Principal paid exceeds remaining balance";
                        $failed++;
                        continue;
                    }

                    // Parse payment date
                    try {
                        $paidAt = \Carbon\Carbon::parse($paymentDate);
                    } catch (\Exception $e) {
                        $errors[] = "Line {$lineNumber}: Invalid payment date format";
                        $failed++;
                        continue;
                    }

                    // Create repayment
                    $repayment = InternalMortgageRepayment::create([
                        'internal_mortgage_plan_id' => $plan->id,
                        'property_id' => $plan->property_id,
                        'amount' => $amount,
                        'principal_paid' => $principalPaid,
                        'interest_paid' => $interestPaid,
                        'due_date' => $paidAt,
                        'status' => 'paid',
                        'paid_at' => $paidAt,
                        'payment_method' => $paymentMethod,
                        'frequency' => $plan->frequency,
                        'reference' => $reference,
                        'recorded_by' => $user->id,
                        'notes' => $notes,
                    ]);

                    // Update plan status
                    if ($plan->isFullyRepaid()) {
                        $plan->update(['status' => 'completed']);
                    }

                    // Create PropertyPaymentTransaction if plan is tied to property
                    if ($plan->property_id) {
                        $this->createPropertyTransaction(
                            $plan->property_id,
                            $plan->member_id,
                            $principalPaid,
                            $reference,
                            'cooperative',
                            null,
                            $plan->id
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
            'message' => 'Bulk internal mortgage repayments processed successfully',
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
        ?string $mortgageId = null,
        ?string $mortgagePlanId = null
    ): void {
        $plan = \App\Models\Tenant\PropertyPaymentPlan::where('property_id', $propertyId)
            ->whereHas('interest', function ($query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->first();

        \App\Models\Tenant\PropertyPaymentTransaction::create([
            'property_id' => $propertyId,
            'member_id' => $memberId,
            'plan_id' => $plan?->id,
            'mortgage_plan_id' => $mortgagePlanId,
            'source' => $source,
            'amount' => $amount,
            'direction' => 'credit',
            'reference' => $reference,
            'status' => 'completed',
            'paid_at' => now(),
            'metadata' => [
                'mortgage_id' => $mortgageId,
                'mortgage_plan_id' => $mortgagePlanId,
                'recorded_by_admin' => true,
            ],
        ]);
    }
}

