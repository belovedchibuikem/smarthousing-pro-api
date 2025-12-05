<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\InternalMortgagePlan;
use App\Models\Tenant\Member;
use App\Models\Tenant\Property;
use App\Traits\HandlesBulkFileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BulkInternalMortgagePlanController extends Controller
{
    use HandlesBulkFileUpload;

    /**
     * Download CSV template for bulk internal mortgage plan upload
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
            'Member ID (UUID, Staff ID, or IPPIS)',
            'Property ID (UUID)',
            'Title',
            'Description',
            'Principal Amount',
            'Interest Rate (%)',
            'Tenure (Years)',
            'Frequency (monthly/quarterly/biannually/annually)',
            'Start Date (YYYY-MM-DD)',
            'Status (draft/active/completed/cancelled)',
            'Notes'
        ];

        $sampleData = [
            [
                'FRSC/HMS/2024/001',
                'PROP-UUID-123',
                'Property Purchase Mortgage Plan',
                'Internal mortgage plan for property acquisition',
                '5000000',
                '6.5',
                '20',
                'monthly',
                '2025-01-01',
                'draft',
                'Initial mortgage plan setup'
            ],
            [
                'FRSC/HMS/2024/002',
                'PROP-UUID-456',
                'Property Development Mortgage',
                'Mortgage for property development project',
                '10000000',
                '7.0',
                '15',
                'monthly',
                '2025-02-01',
                'draft',
                'Development project financing'
            ],
        ];

        $csvContent = implode(',', array_map($escapeCsv, $headers)) . "\n";
        foreach ($sampleData as $row) {
            $csvContent .= implode(',', array_map($escapeCsv, $row)) . "\n";
        }

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'internal_mortgage_plans_upload_template.csv'
        ]);
    }

    /**
     * Upload and process bulk internal mortgage plans from CSV/Excel
     */
    public function uploadBulk(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'errors' => ['You must be logged in to perform this action.'],
                    'error_type' => 'authentication_error'
                ], 401);
            }

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
                    'message' => 'No internal mortgage plan data found in file',
                    'errors' => ['The file appears to be empty or contains no valid internal mortgage plan data.'],
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
                    
                    $propertyId = $findValue([
                        'Property ID (UUID)',
                        'property_id_uuid',
                        'Property ID',
                        'property_id',
                        'PropertyID',
                        'PropertyId'
                    ]);
                    
                    $title = $findValue([
                        'Title',
                        'title'
                    ]);
                    
                    $description = $findValue([
                        'Description',
                        'description'
                    ]);
                    
                    $principal = $findValue([
                        'Principal Amount',
                        'principal_amount',
                        'Principal',
                        'principal',
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
                    
                    $frequency = $findValue([
                        'Frequency (monthly/quarterly/biannually/annually)',
                        'frequency_monthly_quarterly_biannually_annually',
                        'Frequency',
                        'frequency'
                    ], 'monthly');
                    
                    $startDate = $findValue([
                        'Start Date (YYYY-MM-DD)',
                        'start_date_yyyy_mm_dd',
                        'Start Date',
                        'start_date',
                        'StartDate'
                    ]);
                    
                    $status = $findValue([
                        'Status (draft/active/completed/cancelled)',
                        'status_draft_active_completed_cancelled',
                        'Status',
                        'status'
                    ], 'draft');
                    
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

                    if (empty($title)) {
                        $errors[] = "Row {$lineNumber}: Title is required";
                        $failed++;
                        continue;
                    }

                    if (empty($principal) || !is_numeric($principal) || floatval($principal) <= 0) {
                        $errors[] = "Row {$lineNumber}: Principal Amount is required and must be greater than 0";
                        $failed++;
                        continue;
                    }

                    if (empty($interestRate) || !is_numeric($interestRate)) {
                        $errors[] = "Row {$lineNumber}: Interest Rate is required and must be a valid number";
                        $failed++;
                        continue;
                    }

                    $interestRate = floatval($interestRate);
                    if ($interestRate < 0 || $interestRate > 100) {
                        $errors[] = "Row {$lineNumber}: Interest Rate must be between 0 and 100";
                        $failed++;
                        continue;
                    }

                    if (empty($tenureYears) || !is_numeric($tenureYears) || intval($tenureYears) <= 0) {
                        $errors[] = "Row {$lineNumber}: Tenure is required and must be greater than 0";
                        $failed++;
                        continue;
                    }

                    $tenureYears = intval($tenureYears);
                    if ($tenureYears > 35) {
                        $errors[] = "Row {$lineNumber}: Tenure cannot exceed 35 years";
                        $failed++;
                        continue;
                    }

                    // Validate frequency
                    $validFrequencies = ['monthly', 'quarterly', 'biannually', 'annually'];
                    $frequency = strtolower($frequency);
                    if (!in_array($frequency, $validFrequencies)) {
                        $errors[] = "Row {$lineNumber}: Invalid frequency '{$frequency}'. Must be one of: " . implode(', ', $validFrequencies);
                        $failed++;
                        continue;
                    }

                    // Validate status
                    $validStatuses = ['draft', 'active', 'completed', 'cancelled'];
                    $status = strtolower($status);
                    if (!in_array($status, $validStatuses)) {
                        $errors[] = "Row {$lineNumber}: Invalid status '{$status}'. Must be one of: " . implode(', ', $validStatuses);
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
                        $errors[] = "Row {$lineNumber}: Member not found for identifier '{$memberId}'. Please use Member Number, Staff ID, IPPIS Number, or Member UUID.";
                        $failed++;
                        continue;
                    }

                    // Find property if provided
                    $property = null;
                    if (!empty($propertyId)) {
                        $property = Property::find($propertyId);
                        if (!$property) {
                            $errors[] = "Row {$lineNumber}: Property ID '{$propertyId}' not found";
                            $failed++;
                            continue;
                        }
                    }

                    // Calculate mortgage plan details
                    $principal = floatval($principal);
                    $tenureMonths = $tenureYears * 12;
                    
                    // Calculate monthly payment using amortization formula
                    $periodsPerYear = match ($frequency) {
                        'monthly' => 12,
                        'quarterly' => 4,
                        'biannually' => 2,
                        'annually' => 1,
                        default => 12,
                    };

                    $numberOfPayments = $tenureMonths;
                    $monthlyRate = $interestRate > 0 ? ($interestRate / 100) / 12 : 0;

                    $monthlyPayment = null;
                    if ($monthlyRate > 0 && $numberOfPayments > 0) {
                        $factor = pow(1 + $monthlyRate, $numberOfPayments);
                        if ($factor === 1.0) {
                            $monthlyPayment = $principal / $numberOfPayments;
                        } else {
                            $monthlyPayment = $principal * ($monthlyRate * $factor) / ($factor - 1);
                        }
                    } elseif ($numberOfPayments > 0) {
                        $monthlyPayment = $principal / $numberOfPayments;
                    }

                    // Parse start date if provided
                    $startsOn = null;
                    $endsOn = null;
                    if (!empty($startDate)) {
                        try {
                            $startsOn = \Carbon\Carbon::parse($startDate);
                            $endsOn = $startsOn->copy()->addYears($tenureYears);
                        } catch (\Exception $e) {
                            $errors[] = "Row {$lineNumber}: Invalid start date format '{$startDate}'. Expected format: YYYY-MM-DD";
                            $failed++;
                            continue;
                        }
                    }

                    // Create internal mortgage plan
                    InternalMortgagePlan::create([
                        'property_id' => $property?->id,
                        'member_id' => $member->id,
                        'configured_by' => $user->id,
                        'title' => $title,
                        'description' => !empty($description) ? $description : null,
                        'principal' => $principal,
                        'interest_rate' => $interestRate,
                        'tenure_months' => $tenureMonths,
                        'monthly_payment' => $monthlyPayment,
                        'frequency' => $frequency,
                        'status' => $status,
                        'starts_on' => $startsOn,
                        'ends_on' => $endsOn,
                        'schedule' => null,
                        'metadata' => !empty($notes) ? ['notes' => $notes, 'bulk_upload' => true] : ['bulk_upload' => true],
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
                    Log::error("Bulk internal mortgage plan upload - Row {$lineNumber} database error", [
                        'error' => $e->getMessage(),
                        'data' => $data
                    ]);
                    DB::beginTransaction(); // Restart transaction for next row
                } catch (\Exception $e) {
                    Log::error("BulkInternalMortgagePlanController error on row {$lineNumber}: " . $e->getMessage(), [
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
                    'message' => 'All internal mortgage plan records failed to process',
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
            
            Log::error('Bulk internal mortgage plan upload error: ' . $e->getMessage(), [
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

