<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Mortgage;
use App\Models\Tenant\MortgageProvider;
use App\Models\Tenant\Member;
use App\Models\Tenant\Property;
use App\Traits\HandlesBulkFileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BulkMortgageController extends Controller
{
    use HandlesBulkFileUpload;
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
            'Member ID (UUID, Staff ID, or IPPIS)',
            'Mortgage Provider Name',
            'Property ID (UUID)',
            'Loan Amount',
            'Interest Rate (%)',
            'Tenure (Years)',
            'Notes'
        ];

        $sampleData = [
            [
                'FRSC/HMS/2024/001',
                'Federal Mortgage Bank of Nigeria',
                'PROP-UUID-123',
                '15000000',
                '6.5',
                '20',
                'Mortgage for property purchase'
            ],
        ];

        $csvContent = implode(',', array_map($escapeCsv, $headers)) . "\n";
        foreach ($sampleData as $row) {
            $csvContent .= implode(',', array_map($escapeCsv, $row)) . "\n";
        }

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'mortgages_upload_template.csv'
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
                    'message' => 'No mortgage data found in file',
                    'errors' => ['The file appears to be empty or contains no valid mortgage data.'],
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
                
                $providerName = $findValue([
                    'Mortgage Provider Name',
                    'mortgage_provider_name',
                    'Provider Name',
                    'provider_name',
                    'Provider',
                    'provider'
                ]);
                
                $propertyId = $findValue([
                    'Property ID (UUID)',
                    'property_id_uuid',
                    'Property ID',
                    'property_id',
                    'PropertyID',
                    'PropertyId'
                ]);
                
                $loanAmount = $findValue([
                    'Loan Amount',
                    'loan_amount',
                    'LoanAmount',
                    'Amount',
                    'amount'
                ]);
                
                $interestRate = $findValue([
                    'Interest Rate (%)',
                    'interest_rate_percent',
                    'Interest Rate',
                    'interest_rate',
                    'InterestRate',
                    'Rate',
                    'rate'
                ]);
                
                $tenureYears = $findValue([
                    'Tenure (Years)',
                    'tenure_years',
                    'Tenure',
                    'tenure',
                    'TenureYears',
                    'Years',
                    'years'
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

                if (empty($loanAmount) || !is_numeric($loanAmount) || floatval($loanAmount) <= 0) {
                    $errors[] = "Row {$lineNumber}: Loan Amount is required and must be greater than 0";
                    $failed++;
                    continue;
                }

                if (empty($interestRate) || !is_numeric($interestRate)) {
                    $errors[] = "Row {$lineNumber}: Interest Rate is required and must be a valid number";
                    $failed++;
                    continue;
                }

                if (empty($tenureYears) || !is_numeric($tenureYears) || intval($tenureYears) <= 0) {
                    $errors[] = "Row {$lineNumber}: Tenure is required and must be greater than 0";
                    $failed++;
                    continue;
                }

                $loanAmount = floatval($loanAmount);
                $interestRate = floatval($interestRate);
                $tenureYears = intval($tenureYears);

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

                // Find provider
                $provider = MortgageProvider::where('name', $providerName)->first();
                
                // Find property if provided
                $property = null;
                if (!empty($propertyId)) {
                    $property = Property::find($propertyId);
                }

                $monthlyPayment = ($loanAmount * ($interestRate / 100) * $tenureYears + $loanAmount) / ($tenureYears * 12);

                Mortgage::create([
                    'member_id' => $member->id,
                    'provider_id' => $provider?->id,
                    'property_id' => $property?->id,
                    'loan_amount' => $loanAmount,
                    'interest_rate' => $interestRate,
                    'tenure_years' => $tenureYears,
                    'monthly_payment' => $monthlyPayment,
                    'status' => 'pending',
                    'application_date' => now(),
                    'notes' => $notes,
                ]);

                $successful++;
            } catch (\Illuminate\Database\QueryException $e) {
                DB::rollBack();
                $errorCode = $e->getCode();
                $memberId = $memberId ?? 'unknown';
                
                if ($errorCode == 23000) {
                    $errors[] = "Row {$lineNumber} (Member: {$memberId}): Database constraint violation - " . $e->getMessage();
                } else {
                    $errors[] = "Row {$lineNumber} (Member: {$memberId}): Database error - " . $e->getMessage();
                }
                $failed++;
                Log::error("Bulk mortgage upload - Row {$lineNumber} database error", [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
                DB::beginTransaction(); // Restart transaction for next row
            } catch (\Exception $e) {
                Log::error("BulkMortgageController error on row {$lineNumber}: " . $e->getMessage(), [
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
                    'message' => 'All mortgage records failed to process',
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
            
            Log::error('Bulk mortgage upload error: ' . $e->getMessage(), [
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

