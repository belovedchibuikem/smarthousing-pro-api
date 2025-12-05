<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\Member;
use App\Traits\HandlesBulkFileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BulkContributionController extends Controller
{
    use HandlesBulkFileUpload;
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'Member ID (UUID, Staff ID, or IPPIS)',
            'Amount',
            'Contribution Type',
            'Payment Method',
            'Payment Date (YYYY-MM-DD)',
            'Notes'
        ];

        // Helper function to escape CSV values
        $escapeCsv = function($value) {
            // If value contains comma, quote, or newline, wrap in quotes and escape internal quotes
            if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
                return '"' . str_replace('"', '""', $value) . '"';
            }
            return $value;
        };

        $csvContent = implode(',', array_map($escapeCsv, $template)) . "\n";
        
        $sampleData = [
            'FRSC/HMS/2024/001',
            '50000',
            'monthly',
            'bank_transfer',
            '2024-01-15',
            'Monthly contribution for January'
        ];
        
        $csvContent .= implode(',', array_map($escapeCsv, $sampleData)) . "\n";

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'contributions_upload_template.csv'
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
                    'message' => 'No contribution data found in file',
                    'errors' => ['The file appears to be empty or contains no valid contribution data.'],
                    'error_type' => 'empty_data'
                ], 422);
            }

            $successful = 0;
            $failed = 0;
            $errors = $parsedResult['errors'] ?? [];

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
                                
                                // Partial match for headers with parentheses (e.g., "Member ID (UUID, Staff ID, or IPPIS)" matches "Member ID")
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

                    // Handle different header formats (original and snake_case from Excel)
                    $memberId = $findValue([
                        'Member ID (UUID, Staff ID, or IPPIS)',
                        'Member ID (UUID or Staff ID)',
                        'member_id_uuid_staff_id_or_ippis',
                        'member_id_uuid_or_staff_id',
                        'Member ID',
                        'member_id',
                        'MemberID',
                        'MemberId'
                    ]);
                    
                    $amount = $findValue([
                        'Amount',
                        'amount'
                    ]);
                    
                    $type = $findValue([
                        'Contribution Type',
                        'contribution_type',
                        'Type',
                        'type',
                        'ContributionType'
                    ], 'monthly');
                    
                    $paymentMethod = $findValue([
                        'Payment Method',
                        'payment_method',
                        'PaymentMethod'
                    ], 'bank_transfer');
                    
                    $paymentDate = $findValue([
                        'Payment Date (YYYY-MM-DD)',
                        'payment_date_yyyy_mm_dd',
                        'Payment Date',
                        'payment_date',
                        'PaymentDate'
                    ]);
                    
                    $notes = $findValue([
                        'Notes',
                        'notes'
                    ]);

                    // Validate required fields
                    if (empty($memberId)) {
                        $availableKeys = implode(', ', array_keys($data));
                        $errors[] = "Row {$lineNumber}: Member ID is required. Available columns: {$availableKeys}";
                        $failed++;
                        continue;
                    }

                    if (empty($amount) || floatval($amount) <= 0) {
                        $errors[] = "Row {$lineNumber}: Amount is required and must be greater than 0. Found: '{$amount}'";
                        $failed++;
                        continue;
                    }

                    if (empty($type)) {
                        $availableKeys = implode(', ', array_keys($data));
                        $errors[] = "Row {$lineNumber}: Contribution Type is required. Available columns: {$availableKeys}";
                        $failed++;
                        continue;
                    }

                    if (empty($paymentDate)) {
                        $availableKeys = implode(', ', array_keys($data));
                        $errors[] = "Row {$lineNumber}: Payment Date is required. Available columns: {$availableKeys}";
                        $failed++;
                        continue;
                    }

                    // Validate date format
                    try {
                        $parsedDate = \Carbon\Carbon::parse($paymentDate);
                        if ($parsedDate->format('Y-m-d') !== $paymentDate && strlen($paymentDate) === 10) {
                            throw new \Exception("Invalid date format");
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Row {$lineNumber}: Invalid date format for Payment Date '{$paymentDate}'. Expected YYYY-MM-DD";
                        $failed++;
                        continue;
                    }

                    $member = Member::where('id', $memberId)
                        ->orWhere('member_number', $memberId)
                        ->orWhere('staff_id', $memberId)
                        ->orWhere('ippis_number', $memberId)
                        ->first();

                    if (!$member) {
                        $errors[] = "Row {$lineNumber}: Member not found for ID '{$memberId}'. Please verify the Member ID (UUID, Staff ID, or IPPIS) exists in the system.";
                        $failed++;
                        continue;
                    }

                    DB::beginTransaction();
                    try {
                        $contribution = Contribution::create([
                            'member_id' => $member->id,
                            'amount' => floatval($amount),
                            'type' => $type,
                            'contribution_date' => \Carbon\Carbon::parse($paymentDate),
                            'status' => 'completed',
                        ]);

                        // Create payment record if payment method is provided
                        if (!empty($paymentMethod)) {
                            ContributionPayment::create([
                                'contribution_id' => $contribution->id,
                                'amount' => floatval($amount),
                                'payment_date' => \Carbon\Carbon::parse($paymentDate),
                                'payment_method' => $paymentMethod,
                                'status' => 'completed',
                                'reference' => 'BULK-' . time() . '-' . rand(1000, 9999),
                                'metadata' => !empty($notes) ? ['notes' => $notes] : null,
                            ]);
                        }

                        DB::commit();
                        $successful++;
                    } catch (\Illuminate\Database\QueryException $e) {
                        DB::rollBack();
                        $errorCode = $e->getCode();
                        if ($errorCode == 23000) {
                            $errors[] = "Row {$lineNumber}: Database constraint violation - " . $e->getMessage();
                        } else {
                            $errors[] = "Row {$lineNumber}: Database error - " . $e->getMessage();
                        }
                        $failed++;
                        Log::error("Bulk contribution upload - Row {$lineNumber} error", [
                            'error' => $e->getMessage(),
                            'data' => $data
                        ]);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $errors[] = "Row {$lineNumber}: " . $e->getMessage();
                        $failed++;
                        Log::error("Bulk contribution upload - Row {$lineNumber} error", [
                            'error' => $e->getMessage(),
                            'data' => $data
                        ]);
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row {$lineNumber}: Unexpected error - " . $e->getMessage();
                    $failed++;
                    Log::error("Bulk contribution upload - Row {$lineNumber} unexpected error", [
                        'error' => $e->getMessage(),
                        'data' => $data
                    ]);
                }
            }

            // Check if all records failed
            if ($successful === 0 && $failed > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'All contribution records failed to process',
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
                'message' => 'Bulk upload processed successfully',
                'data' => [
                    'total' => count($rows),
                    'successful' => $successful,
                    'failed' => $failed,
                    'errors' => [],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk contribution upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during bulk upload',
                'errors' => ['Server error: ' . $e->getMessage()],
                'error_type' => 'server_error'
            ], 500);
        }
    }
}

