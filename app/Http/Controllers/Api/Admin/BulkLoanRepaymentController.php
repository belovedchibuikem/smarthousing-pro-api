<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanRepayment;
use App\Models\Tenant\Member;
use App\Models\Tenant\PropertyPaymentTransaction;
use App\Models\Tenant\PropertyPaymentPlan;
use App\Traits\HandlesBulkFileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BulkLoanRepaymentController extends Controller
{
    use HandlesBulkFileUpload;
    /**
     * Download CSV template for bulk loan repayment upload
     */
    public function downloadTemplate(): JsonResponse
    {
        // Helper function to escape CSV values
        $escapeCsv = function($value) {
            // If value contains comma, quote, or newline, wrap in quotes and escape internal quotes
            if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
                return '"' . str_replace('"', '""', $value) . '"';
            }
            return $value;
        };

        $headers = [
            'Loan ID',
            'Member ID (UUID, Staff ID, or IPPIS)',
            'Member Name',
            'Amount',
            'Principal Paid',
            'Interest Paid',
            'Payment Date (YYYY-MM-DD)',
            'Payment Method',
            'Transaction Reference'
        ];

        $sampleData = [
            [
                'LOAN-2024-001',
                'FRSC/HMS/2024/001',
                'John Doe',
                '100000',
                '80000',
                '20000',
                '2025-01-15',
                'bank_transfer',
                'TRX123456789'
            ],
            [
                'LOAN-2024-002',
                'FRSC/HMS/2024/002',
                'Jane Smith',
                '150000',
                '',
                '',
                '2025-01-15',
                'paystack',
                'PAY987654321'
            ],
        ];

        $csvContent = implode(',', array_map($escapeCsv, $headers)) . "\n";
        foreach ($sampleData as $row) {
            $csvContent .= implode(',', array_map($escapeCsv, $row)) . "\n";
        }

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
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File validation failed',
                    'errors' => $validator->errors()->all(),
                    'error_type' => 'file_validation'
                ], 422);
            }

            $file = $request->file('file');
            
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or corrupted file',
                    'errors' => ['The uploaded file is invalid or corrupted. Please check the file and try again.'],
                    'error_type' => 'file_invalid'
                ], 422);
            }

            $parsedResult = $this->parseFile($file);
            
            if (!$parsedResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse file',
                    'errors' => $parsedResult['errors'] ?? ['Unable to parse the file. Please check the file format.'],
                    'error_type' => 'parsing_error'
                ], 422);
            }

            $rows = $parsedResult['data'];
            
            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No loan repayment data found in file',
                    'errors' => ['The file appears to be empty or contains no valid loan repayment data.'],
                    'error_type' => 'empty_data'
                ], 422);
            }

            $successful = 0;
            $failed = 0;
            $errors = $parsedResult['errors'] ?? [];
            
            DB::beginTransaction();

            foreach ($rows as $index => $data) {
                $lineNumber = $index + 2; // +2 because line 1 is header

                try {
                    // Helper function to find value by multiple possible keys (case-insensitive, handles whitespace)
                    $findValue = function($keys, $default = '') use ($data) {
                        foreach ($keys as $key) {
                            // Try exact match first
                            if (array_key_exists($key, $data)) {
                                $value = trim((string)$data[$key]);
                                if ($value !== '') {
                                    return $value;
                                }
                            }
                            // Try case-insensitive match
                            foreach ($data as $dataKey => $dataValue) {
                                $normalizedKey = trim((string)$dataKey);
                                $normalizedSearchKey = trim((string)$key);
                                
                                // Exact case-insensitive match
                                if (strcasecmp($normalizedKey, $normalizedSearchKey) === 0) {
                                    $value = trim((string)$dataValue);
                                    if ($value !== '') {
                                        return $value;
                                    }
                                }
                                
                                // Partial match for headers with parentheses
                                $keyWithoutParentheses = preg_replace('/\s*\([^)]*\)\s*/', '', $normalizedKey);
                                $searchKeyWithoutParentheses = preg_replace('/\s*\([^)]*\)\s*/', '', $normalizedSearchKey);
                                if (strcasecmp(trim($keyWithoutParentheses), trim($searchKeyWithoutParentheses)) === 0) {
                                    $value = trim((string)$dataValue);
                                    if ($value !== '') {
                                        return $value;
                                    }
                                }
                            }
                        }
                        return $default;
                    };

                    // Extract values using flexible header matching
                    $loanId = $findValue([
                        'Loan ID',
                        'loan_id',
                        'LoanID',
                        'LoanId'
                    ]);
                    
                    $memberId = $findValue([
                        'Member ID (UUID, Staff ID, or IPPIS)',
                        'Member ID (UUID or Staff ID)',
                        'member_id_uuid_staff_id_or_ippis',
                        'member_id_uuid_or_staff_id',
                        'Member ID',
                        'member_id',
                        'MemberID',
                        'MemberId',
                        'Member Number',
                        'member_number',
                        'Staff ID',
                        'staff_id'
                    ]);
                    
                    $amount = $findValue([
                        'Amount',
                        'amount'
                    ]);
                    
                    $principalPaid = $findValue([
                        'Principal Paid',
                        'principal_paid',
                        'PrincipalPaid'
                    ]);
                    
                    $interestPaid = $findValue([
                        'Interest Paid',
                        'interest_paid',
                        'InterestPaid'
                    ]);
                    
                    $paymentDate = $findValue([
                        'Payment Date (YYYY-MM-DD)',
                        'payment_date_yyyy_mm_dd',
                        'Payment Date',
                        'payment_date',
                        'PaymentDate'
                    ], now()->format('Y-m-d'));
                    
                    $paymentMethod = $findValue([
                        'Payment Method',
                        'payment_method',
                        'PaymentMethod'
                    ], 'bank_transfer');
                    
                    $reference = $findValue([
                        'Transaction Reference',
                        'transaction_reference',
                        'Transaction Ref',
                        'transaction_ref',
                        'TransactionReference'
                    ]);

                    // Validate required fields
                    if (empty($loanId)) {
                        $availableKeys = implode(', ', array_keys($data));
                        $errors[] = "Row {$lineNumber}: Loan ID is required. Available columns: {$availableKeys}";
                        $failed++;
                        continue;
                    }

                    if (empty($memberId)) {
                        $availableKeys = implode(', ', array_keys($data));
                        $errors[] = "Row {$lineNumber}: Member ID is required. Available columns: {$availableKeys}";
                        $failed++;
                        continue;
                    }

                    if (empty($amount) || !is_numeric($amount) || floatval($amount) <= 0) {
                        $errors[] = "Row {$lineNumber}: Amount is required and must be greater than 0";
                        $failed++;
                        continue;
                    }

                    $amount = floatval($amount);
                    $principalPaid = !empty($principalPaid) ? floatval($principalPaid) : null;
                    $interestPaid = !empty($interestPaid) ? floatval($interestPaid) : null;

                    // Find member
                    $member = Member::where('id', $memberId)
                        ->orWhere('member_number', $memberId)
                        ->orWhere('staff_id', $memberId)
                        ->orWhere('ippis_number', $memberId)
                        ->first();

                    if (!$member) {
                        $errors[] = "Row {$lineNumber}: Member not found for identifier '{$memberId}'. Please use Member Number, Staff ID, IPPIS Number, or Member UUID.";
                        $failed++;
                        continue;
                    }

                    // Find loan by ID first
                    $loan = Loan::where('id', $loanId)->first();

                    // If not found by ID, try to find by member (fallback)
                    if (!$loan) {
                        $loan = Loan::where('member_id', $member->id)
                            ->where('status', 'approved')
                            ->orderBy('created_at', 'desc')
                            ->first();
                        
                        if ($loan) {
                            $errors[] = "Row {$lineNumber}: Loan ID '{$loanId}' not found, but found a loan for this member. Please verify the Loan ID.";
                        }
                    }

                    if (!$loan) {
                        $errors[] = "Row {$lineNumber}: Loan ID '{$loanId}' not found for member '{$memberId}'";
                        $failed++;
                        continue;
                    }

                    // Verify loan belongs to the member
                    if ($loan->member_id !== $member->id) {
                        $errors[] = "Row {$lineNumber}: Loan ID '{$loanId}' does not belong to member '{$memberId}'";
                        $failed++;
                        continue;
                    }

                    // Check if loan is completed
                    if ($loan->status === 'completed') {
                        $errors[] = "Row {$lineNumber}: Loan ID '{$loanId}' has been completed and cannot accept further repayments";
                        $failed++;
                        continue;
                    }

                    // Check if loan is approved or active
                    if ($loan->status !== 'approved' && $loan->status !== 'active') {
                        $errors[] = "Row {$lineNumber}: Loan ID '{$loanId}' is not approved or active (Status: {$loan->status}). Only 'approved' or 'active' loans can accept repayments.";
                        $failed++;
                        continue;
                    }

                    // Check if loan is already fully repaid
                    $remainingPrincipal = $loan->getRemainingPrincipal();
                    $totalRepaid = $loan->repayments()->where('status', 'paid')->sum('amount');
                    $loanTotalAmount = $loan->total_amount ?? ($loan->amount + ($loan->amount * ($loan->interest_rate ?? 0) / 100));
                    $remainingAmount = $loanTotalAmount - $totalRepaid;

                    if ($loan->isFullyRepaid()) {
                        $errors[] = "Row {$lineNumber}: Loan ID '{$loanId}' has been fully paid. Remaining principal: ₦0.00";
                        $failed++;
                        continue;
                    }

                    if ($remainingAmount <= 0) {
                        $errors[] = "Row {$lineNumber}: Loan ID '{$loanId}' has been fully repaid. Total repaid: ₦" . number_format($totalRepaid, 2) . ", Loan total: ₦" . number_format($loanTotalAmount, 2);
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
                        $errors[] = "Row {$lineNumber}: Principal Paid (₦" . number_format($principalPaid, 2) . ") + Interest Paid (₦" . number_format($interestPaid, 2) . ") must equal Amount (₦" . number_format($amount, 2) . ")";
                        $failed++;
                        continue;
                    }

                    // Validate repayment amount doesn't exceed remaining balance
                    if ($amount > $remainingAmount) {
                        $errors[] = "Row {$lineNumber}: Repayment amount (₦" . number_format($amount, 2) . ") exceeds remaining balance (₦" . number_format($remainingAmount, 2) . ")";
                        $failed++;
                        continue;
                    }

                    // Validate principal doesn't exceed remaining principal
                    if ($principalPaid > $remainingPrincipal) {
                        $errors[] = "Row {$lineNumber}: Principal paid (₦" . number_format($principalPaid, 2) . ") exceeds remaining principal balance (₦" . number_format($remainingPrincipal, 2) . ")";
                        $failed++;
                        continue;
                    }

                    // Parse payment date
                    try {
                        $paidAt = \Carbon\Carbon::parse($paymentDate);
                    } catch (\Exception $e) {
                        $errors[] = "Row {$lineNumber}: Invalid payment date format '{$paymentDate}'. Expected format: YYYY-MM-DD (e.g., 2025-01-15)";
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
                } catch (\Illuminate\Database\QueryException $e) {
                    DB::rollBack();
                    $errorCode = $e->getCode();
                    $loanId = $findValue(['Loan ID', 'loan_id', 'LoanID', 'LoanId']) ?? 'unknown';
                    
                    if ($errorCode == 23000) {
                        $errors[] = "Row {$lineNumber} (Loan ID: {$loanId}): Database constraint violation - " . $e->getMessage();
                    } else {
                        $errors[] = "Row {$lineNumber} (Loan ID: {$loanId}): Database error - " . $e->getMessage();
                    }
                    $failed++;
                    Log::error("Bulk loan repayment upload - Row {$lineNumber} database error", [
                        'error' => $e->getMessage(),
                        'data' => $data
                    ]);
                    DB::beginTransaction(); // Restart transaction for next row
                } catch (\Exception $e) {
                    Log::error("BulkLoanRepaymentController error on row {$lineNumber}: " . $e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                        'data' => $data
                    ]);
                    $errors[] = "Row {$lineNumber}: " . $e->getMessage();
                    $failed++;
                }
            }

            DB::commit();
            
            // Check if all records failed
            if ($successful === 0 && $failed > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'All loan repayment records failed to process',
                    'errors' => $errors,
                    'error_type' => 'processing_error',
                    'data' => [
                        'total' => count($rows),
                        'successful' => $successful,
                        'failed' => $failed,
                        'errors' => $errors,
                    ]
                ], 422);
            }

            // Partial success - some records succeeded, some failed
            if ($failed > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Bulk upload completed with errors. {$successful} successful, {$failed} failed.",
                    'data' => [
                        'total' => count($rows),
                        'successful' => $successful,
                        'failed' => $failed,
                        'errors' => $errors,
                    ],
                    'has_errors' => true
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk upload processed successfully. {$successful} successful.",
                'data' => [
                    'total' => count($rows),
                    'successful' => $successful,
                    'failed' => $failed,
                    'errors' => [],
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Bulk loan repayment upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during bulk upload',
                'errors' => ['Server error: ' . $e->getMessage()],
                'error_type' => 'server_error',
                'data' => [
                    'total' => isset($rows) ? count($rows) : 0,
                    'successful' => isset($successful) ? $successful : 0,
                    'failed' => isset($failed) ? $failed : 0,
                    'errors' => isset($errors) ? $errors : [],
                ]
            ], 500);
        }
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

