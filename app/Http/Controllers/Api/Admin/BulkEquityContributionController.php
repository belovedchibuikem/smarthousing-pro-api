<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EquityContribution;
use App\Models\Tenant\EquityPlan;
use App\Models\Tenant\EquityWalletBalance;
use App\Models\Tenant\Member;
use App\Traits\HandlesBulkFileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BulkEquityContributionController extends Controller
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
            'Amount',
            'Plan ID (optional)',
            'Payment Method',
            'Payment Reference (optional)',
            'Notes (optional)'
        ];

        $sampleData = [
            [
                'STAFF-001',
                '50000',
                '',
                'manual',
                'REF-001',
                'Monthly equity contribution'
            ],
            [
                'STAFF-002',
                '75000',
                '',
                'bank_transfer',
                'REF-002',
                ''
            ],
        ];

        $csvContent = implode(',', array_map($escapeCsv, $headers)) . "\n";
        foreach ($sampleData as $row) {
            $csvContent .= implode(',', array_map($escapeCsv, $row)) . "\n";
        }

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'equity_contributions_template.csv'
        ]);
    }

    public function uploadBulk(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
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
                    'message' => 'No equity contribution data found in file',
                    'errors' => ['The file appears to be empty or contains no valid equity contribution data.'],
                    'error_type' => 'empty_data'
                ], 422);
            }

            $successCount = 0;
            $errorCount = 0;
            $errors = $parsedResult['errors'] ?? [];

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because header is row 1, and array is 0-indexed

                try {
                    // Helper function to find value by multiple possible keys (case-insensitive, handles whitespace)
                    $findValue = function($keys, $default = '') use ($row) {
                        foreach ($keys as $key) {
                            // Try exact match first
                            if (array_key_exists($key, $row)) {
                                $value = trim((string)$row[$key]);
                                if ($value !== '') {
                                    return $value;
                                }
                            }
                            // Try case-insensitive match
                            foreach ($row as $dataKey => $dataValue) {
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

                    // Handle different header formats - check for Member ID (UUID, Staff ID, or IPPIS) first, then member_number
                    $memberIdentifier = $findValue([
                        'Member ID (UUID, Staff ID, or IPPIS)',
                        'Member ID (UUID or Staff ID)',
                        'member_id_uuid_staff_id_or_ippis',
                        'member_id_uuid_or_staff_id',
                        'Member ID',
                        'member_id',
                        'member_number',
                        'Member Number',
                        'staff_id',
                        'Staff ID'
                    ]);
                    
                    $amount = $findValue([
                        'Amount',
                        'amount'
                    ]);
                    
                    $planIdRaw = $findValue([
                        'Plan ID',
                        'plan_id',
                        'Plan ID (optional)',
                        'plan_id_optional'
                    ]);
                    
                    // Normalize plan_id immediately - convert empty string to null
                    $planId = (!empty($planIdRaw) && trim($planIdRaw) !== '') ? trim($planIdRaw) : null;
                    
                    $paymentMethod = $findValue([
                        'Payment Method',
                        'payment_method'
                    ], 'manual');
                    
                    $paymentReference = $findValue([
                        'Payment Reference',
                        'payment_reference',
                        'Payment Reference (optional)',
                        'payment_reference_optional'
                    ]);
                    
                    if (empty($paymentReference)) {
                        $paymentReference = 'BULK-' . time() . '-' . $rowNumber;
                    }
                    
                    $notes = $findValue([
                        'Notes',
                        'notes',
                        'Notes (optional)',
                        'notes_optional'
                    ]);

                    // Validate row data
                    if (empty($memberIdentifier)) {
                        $availableKeys = implode(', ', array_keys($row));
                        $errors[] = "Row {$rowNumber}: Member ID/Number is required. Available columns: {$availableKeys}";
                        $errorCount++;
                        continue;
                    }

                    if (empty($amount) || !is_numeric($amount) || floatval($amount) <= 0) {
                        $errors[] = "Row {$rowNumber}: Invalid amount";
                        $errorCount++;
                        continue;
                    }

                    // Find member by multiple fields: member_number, staff_id, ippis_number, or UUID
                    $member = Member::where('member_number', $memberIdentifier)
                        ->orWhere('staff_id', $memberIdentifier)
                        ->orWhere('ippis_number', $memberIdentifier)
                        ->orWhere('id', $memberIdentifier)
                        ->first();
                    
                    if (!$member) {
                        $errors[] = "Row {$rowNumber}: Member not found for identifier '{$memberIdentifier}'. Please use Member Number, Staff ID, IPPIS Number, or Member UUID.";
                        $errorCount++;
                        continue;
                    }

                    // Validate plan if provided
                    $plan = null;
                    if ($planId) {
                        $plan = EquityPlan::find($planId);
                        if (!$plan || !$plan->is_active) {
                            $errors[] = "Row {$rowNumber}: Invalid or inactive equity plan";
                            $errorCount++;
                            continue;
                        }
                        if (floatval($amount) < $plan->min_amount) {
                            $errors[] = "Row {$rowNumber}: Amount below minimum for selected plan";
                            $errorCount++;
                            continue;
                        }
                        if ($plan->max_amount && floatval($amount) > $plan->max_amount) {
                            $errors[] = "Row {$rowNumber}: Amount exceeds maximum for selected plan";
                            $errorCount++;
                            continue;
                        }
                    }

                    // Validate payment method
                    if (!in_array($paymentMethod, ['manual', 'bank_transfer', 'paystack', 'remita', 'stripe'])) {
                        $errors[] = "Row {$rowNumber}: Invalid payment method";
                        $errorCount++;
                        continue;
                    }

                    // Create contribution - plan_id is already normalized to null if empty
                    $contribution = EquityContribution::create([
                        'member_id' => $member->id,
                        'plan_id' => $planId, // Already null if empty, satisfies foreign key constraint
                        'amount' => floatval($amount),
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference,
                        'status' => in_array($paymentMethod, ['paystack', 'remita', 'stripe']) ? 'approved' : 'pending',
                        'notes' => !empty($notes) && trim($notes) !== '' ? trim($notes) : null,
                        'approved_at' => in_array($paymentMethod, ['paystack', 'remita', 'stripe']) ? now() : null,
                        'paid_at' => in_array($paymentMethod, ['paystack', 'remita', 'stripe']) ? now() : null,
                    ]);

                    // If auto-approved (payment gateway), add to wallet
                    if (in_array($paymentMethod, ['paystack', 'remita', 'stripe'])) {
                        $this->addToEquityWallet($contribution);
                    }

                    $successCount++;

                } catch (\Illuminate\Database\QueryException $e) {
                    $errorCode = $e->getCode();
                    $memberId = $memberIdentifier ?? 'unknown';
                    
                    if ($errorCode == 23000) {
                        $errors[] = "Row {$rowNumber} (Member: {$memberId}): Database constraint violation - " . $e->getMessage();
                    } else {
                        $errors[] = "Row {$rowNumber} (Member: {$memberId}): Database error - " . $e->getMessage();
                    }
                    $errorCount++;
                    Log::error("Bulk equity contribution upload - Row {$rowNumber} database error", [
                        'error' => $e->getMessage(),
                        'row' => $row,
                    ]);
                } catch (\Exception $e) {
                    $errorCount++;
                    $memberId = $memberIdentifier ?? 'unknown';
                    $errors[] = "Row {$rowNumber} (Member: {$memberId}): " . $e->getMessage();
                    Log::error("Bulk equity contribution upload error - Row {$rowNumber}", [
                        'error' => $e->getMessage(),
                        'row' => $row,
                    ]);
                }
            }

            DB::commit();

            // Check if all records failed
            if ($successCount === 0 && $errorCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'All equity contribution records failed to process',
                    'errors' => $errors,
                    'error_type' => 'processing_error',
                    'data' => [
                        'total' => count($rows),
                        'successful' => $successCount,
                        'failed' => $errorCount,
                        'errors' => $errors,
                    ]
                ], 422);
            }

            // Partial success - some records succeeded, some failed
            if ($errorCount > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Bulk upload completed with errors. {$successCount} successful, {$errorCount} failed.",
                    'data' => [
                        'total' => count($rows),
                        'successful' => $successCount,
                        'failed' => $errorCount,
                        'errors' => $errors,
                    ],
                    'has_errors' => true
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk upload completed successfully. {$successCount} successful.",
                'data' => [
                    'total' => count($rows),
                    'successful' => $successCount,
                    'failed' => $errorCount,
                    'errors' => [],
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk equity contribution upload error: ' . $e->getMessage(), [
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

    private function addToEquityWallet(EquityContribution $contribution): void
    {
        $wallet = EquityWalletBalance::firstOrCreate(
            ['member_id' => $contribution->member_id],
            [
                'balance' => 0,
                'total_contributed' => 0,
                'total_used' => 0,
                'currency' => 'NGN',
                'is_active' => true,
            ]
        );

        $wallet->add(
            $contribution->amount,
            $contribution->id,
            'contribution',
            "Equity contribution (bulk upload) - {$contribution->payment_reference}"
        );
    }
}

