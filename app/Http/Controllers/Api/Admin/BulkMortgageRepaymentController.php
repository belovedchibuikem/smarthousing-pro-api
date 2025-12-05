<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Mortgage;
use App\Models\Tenant\MortgageRepayment;
use App\Models\Tenant\InternalMortgagePlan;
use App\Models\Tenant\InternalMortgageRepayment;
use App\Models\Tenant\Member;
use App\Traits\HandlesBulkFileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BulkMortgageRepaymentController extends Controller
{
    use HandlesBulkFileUpload;
    /**
     * Download CSV template for bulk mortgage repayment upload
     */
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'Mortgage ID',
            'Member ID (UUID, Staff ID, or IPPIS)',
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
            'Member ID (UUID, Staff ID, or IPPIS)',
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
                    'message' => 'No mortgage repayment data found in file',
                    'errors' => ['The file appears to be empty or contains no valid mortgage repayment data.'],
                    'error_type' => 'empty_data'
                ], 422);
            }

            $successful = 0;
            $failed = 0;
            $errors = $parsedResult['errors'] ?? [];
            $user = $request->user();
            
            DB::beginTransaction();

            foreach ($rows as $index => $data) {
                $lineNumber = $index + 2; // +2 because line 1 is header

                try {
                    // Handle different header formats
                    $mortgageId = $data['Mortgage ID'] ?? $data['mortgage_id'] ?? null;
                    $memberId = $data['Member ID (UUID, Staff ID, or IPPIS)'] ?? $data['Member ID (UUID or Staff ID)'] ?? $data['Member ID'] ?? $data['member_id'] ?? null;
                    $amount = floatval($data['Amount'] ?? $data['amount'] ?? 0);
                    $principalPaid = floatval($data['Principal Paid'] ?? $data['principal_paid'] ?? 0);
                    $interestPaid = floatval($data['Interest Paid'] ?? $data['interest_paid'] ?? 0);
                    $paymentDate = $data['Payment Date (YYYY-MM-DD)'] ?? $data['Payment Date'] ?? $data['payment_date'] ?? now()->format('Y-m-d');
                    $paymentMethod = $data['Payment Method (monthly/yearly/bi-yearly)'] ?? $data['Payment Method'] ?? $data['payment_method'] ?? 'monthly';
                    $reference = $data['Transaction Reference'] ?? $data['Transaction Ref'] ?? $data['transaction_ref'] ?? 'BULK-MORT-' . time() . '-' . rand(1000, 9999);
                    $notes = $data['Notes'] ?? $data['notes'] ?? null;

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
                        ->orWhere('ippis_number', $memberId)
                        ->first();

                    if (!$member) {
                        $errors[] = "Line {$lineNumber}: Member not found ({$memberId}). Please use Member Number, Staff ID, IPPIS Number, or Member UUID.";
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
                    'total' => count($rows),
                    'successful' => $successful,
                    'failed' => $failed,
                    'errors' => array_slice($errors, 0, 50),
                ]
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk mortgage repayments processed successfully',
            'data' => [
                'total' => count($rows),
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
        $parsedResult = $this->parseFile($file);
        
        if (!$parsedResult['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse file',
                'errors' => $parsedResult['errors']
            ], 422);
        }

        $rows = $parsedResult['data'];
        $successful = 0;
        $failed = 0;
        $errors = $parsedResult['errors'] ?? [];
        $user = $request->user();
        
        try {
            DB::beginTransaction();

            foreach ($rows as $index => $data) {
                $lineNumber = $index + 2; // +2 because line 1 is header

                try {
                    // Handle different header formats
                    $planId = $data['Internal Mortgage Plan ID'] ?? $data['Plan ID'] ?? $data['plan_id'] ?? null;
                    $memberId = $data['Member ID (UUID, Staff ID, or IPPIS)'] ?? $data['Member ID (UUID or Staff ID)'] ?? $data['Member ID'] ?? $data['member_id'] ?? null;
                    $amount = floatval($data['Amount'] ?? $data['amount'] ?? 0);
                    $principalPaid = floatval($data['Principal Paid'] ?? $data['principal_paid'] ?? 0);
                    $interestPaid = floatval($data['Interest Paid'] ?? $data['interest_paid'] ?? 0);
                    $paymentDate = $data['Payment Date (YYYY-MM-DD)'] ?? $data['Payment Date'] ?? $data['payment_date'] ?? now()->format('Y-m-d');
                    $paymentMethod = $data['Payment Method (monthly/yearly/bi-yearly)'] ?? $data['Payment Method'] ?? $data['payment_method'] ?? 'monthly';
                    $reference = $data['Transaction Reference'] ?? $data['Transaction Ref'] ?? $data['transaction_ref'] ?? 'BULK-INT-MORT-' . time() . '-' . rand(1000, 9999);
                    $notes = $data['Notes'] ?? $data['notes'] ?? null;

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
                        ->orWhere('ippis_number', $memberId)
                        ->first();

                    if (!$member) {
                        $errors[] = "Line {$lineNumber}: Member not found ({$memberId}). Please use Member Number, Staff ID, IPPIS Number, or Member UUID.";
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
                } catch (\Illuminate\Database\QueryException $e) {
                    DB::rollBack();
                    $errorCode = $e->getCode();
                    $mortgageId = $mortgageId ?? 'unknown';
                    
                    if ($errorCode == 23000) {
                        $errors[] = "Row {$lineNumber} (Mortgage: {$mortgageId}): Database constraint violation - " . $e->getMessage();
                    } else {
                        $errors[] = "Row {$lineNumber} (Mortgage: {$mortgageId}): Database error - " . $e->getMessage();
                    }
                    $failed++;
                    Log::error("Bulk mortgage repayment upload - Row {$lineNumber} database error", [
                        'error' => $e->getMessage(),
                        'data' => $data
                    ]);
                    DB::beginTransaction(); // Restart transaction for next row
                } catch (\Exception $e) {
                    $errors[] = "Row {$lineNumber}: " . $e->getMessage();
                    $failed++;
                    Log::error("Bulk mortgage repayment upload - Row {$lineNumber} error", [
                        'error' => $e->getMessage(),
                        'data' => $data
                    ]);
                }
            }

            DB::commit();
            
            // Check if all records failed
            if ($successful === 0 && $failed > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'All mortgage repayment records failed to process',
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
            
            Log::error('Bulk mortgage repayment upload error: ' . $e->getMessage(), [
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

