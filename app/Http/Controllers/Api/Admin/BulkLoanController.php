<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Member;
use App\Traits\HandlesBulkFileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BulkLoanController extends Controller
{
    use HandlesBulkFileUpload;
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'Member ID (UUID, Staff ID, or IPPIS)',
            'Loan Amount',
            'Interest Rate (%)',
            'Duration (Months)',
            'Type',
            'Purpose',
            'Application Date (YYYY-MM-DD)'
        ];

        $csvContent = implode(',', $template) . "\n";
        
        $sampleData = [
            'FRSC/HMS/2024/001',
            '1000000',
            '12',
            '12',
            'personal',
            'Emergency loan',
            '2024-01-15'
        ];
        
        $csvContent .= implode(',', $sampleData) . "\n";

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'loans_upload_template.csv'
        ]);
    }

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
                    'message' => 'No loan data found in file',
                    'errors' => ['The file appears to be empty or contains no valid loan data.'],
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
                    // Handle different header formats
                    $memberId = $data['Member ID (UUID, Staff ID, or IPPIS)'] ?? $data['Member ID (UUID or Staff ID)'] ?? $data['Member ID'] ?? $data['member_id'] ?? '';
                    $loanAmount = $data['Loan Amount'] ?? $data['loan_amount'] ?? $data['amount'] ?? 0;
                    $interestRate = $data['Interest Rate (%)'] ?? $data['Interest Rate'] ?? $data['interest_rate'] ?? 0;
                    $duration = $data['Duration (Months)'] ?? $data['Duration'] ?? $data['duration'] ?? 12;
                    $type = $data['Type'] ?? $data['type'] ?? 'personal';
                    $purpose = $data['Purpose'] ?? $data['purpose'] ?? null;
                    $applicationDate = $data['Application Date (YYYY-MM-DD)'] ?? $data['Application Date'] ?? $data['application_date'] ?? now();

                    // Validate required fields
                    if (empty($memberId)) {
                        $errors[] = "Row {$lineNumber}: Member ID is required";
                        $failed++;
                        continue;
                    }

                    if (empty($loanAmount) || floatval($loanAmount) <= 0) {
                        $errors[] = "Row {$lineNumber}: Loan Amount is required and must be greater than 0";
                        $failed++;
                        continue;
                    }

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

                    Loan::create([
                        'member_id' => $member->id,
                        'amount' => floatval($loanAmount),
                        'interest_rate' => floatval($interestRate),
                        'duration_months' => intval($duration),
                        'type' => $type,
                        'purpose' => $purpose,
                        'status' => 'pending',
                        'application_date' => $applicationDate ? \Carbon\Carbon::parse($applicationDate) : now(),
                    ]);

                    $successful++;
                } catch (\Illuminate\Database\QueryException $e) {
                    $errorCode = $e->getCode();
                    $memberId = $memberId ?? 'unknown';
                    
                    if ($errorCode == 23000) {
                        $errors[] = "Row {$lineNumber} (Member: {$memberId}): Database constraint violation - " . $e->getMessage();
                    } else {
                        $errors[] = "Row {$lineNumber} (Member: {$memberId}): Database error - " . $e->getMessage();
                    }
                    $failed++;
                    Log::error("Bulk loan upload - Row {$lineNumber} database error", [
                        'error' => $e->getMessage(),
                        'data' => $data
                    ]);
                } catch (\Exception $e) {
                    $errors[] = "Row {$lineNumber}: " . $e->getMessage();
                    $failed++;
                    Log::error("Bulk loan upload - Row {$lineNumber} error", [
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
                    'message' => 'All loan records failed to process',
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
            
            Log::error('Bulk loan upload error: ' . $e->getMessage(), [
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
}

