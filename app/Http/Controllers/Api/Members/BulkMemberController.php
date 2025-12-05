<?php

namespace App\Http\Controllers\Api\Members;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Member;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BulkMemberController extends Controller
{
    /**
     * Download CSV template for bulk member upload
     */
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'First Name',
            'Last Name', 
            'Email',
            'Phone',
            'Staff ID',
            'IPPIS Number',
            'Date of Birth (YYYY-MM-DD or DD-MM-YYYY)',
            'Gender (Male/Female)',
            'Marital Status (Single/Married/Divorced/Widowed)',
            'Nationality',
            'State of Origin',
            'LGA',
            'Residential Address',
            'City',
            'State',
            'Rank',
            'Department',
            'Command State',
            'Employment Date (YYYY-MM-DD or DD-MM-YYYY)',
            'Years of Service',
            'Membership Type (Regular/Associate)'
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
        
        // Add sample data (showing DD-MM-YYYY format)
        $sampleData = [
            'John',
            'Doe',
            'john.doe@frsc.gov.ng',
            '08012345678',
            'FRSC/2024/001',
            'IPPIS001',
            '15-01-1990',
            'Male',
            'Single',
            'Nigerian',
            'Lagos',
            'Ikeja',
            '123 Main Street, Victoria Island',
            'Lagos',
            'Lagos',
            'Inspector',
            'Operations',
            'Lagos',
            '15-01-2020',
            '4',
            'Regular'
        ];
        
        $csvContent .= implode(',', array_map($escapeCsv, $sampleData)) . "\n";

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'members_upload_template.csv'
        ]);
    }

    /**
     * Upload and process bulk members from CSV
     */
    public function uploadBulk(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120', // 5MB max
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

            $fileExtension = $file->getClientOriginalExtension();
            
            // Parse file based on extension
            if (in_array($fileExtension, ['xlsx', 'xls'])) {
                $parsedData = $this->parseExcel($file);
            } else {
                $parsedData = $this->parseCSV($file);
            }
            
            if (!$parsedData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse file',
                    'errors' => $parsedData['errors'] ?? ['Unable to parse the file. Please check the file format.'],
                    'error_type' => 'parsing_error'
                ], 422);
            }

            $members = $parsedData['data'];
            
            if (empty($members)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No member data found in file',
                    'errors' => ['The file appears to be empty or contains no valid member data.'],
                    'error_type' => 'empty_data'
                ], 422);
            }

            $validationErrors = $this->validateMemberData($members);
            
            if (!empty($validationErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data validation errors found',
                    'errors' => $validationErrors,
                    'error_type' => 'data_validation',
                    'total_rows' => count($members),
                    'error_count' => count($validationErrors)
                ], 422);
            }

            $result = $this->processBulkMembers($members);

            // Check if all records failed
            if ($result['successful'] === 0 && $result['failed'] > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'All member records failed to process',
                    'errors' => $result['errors'],
                    'error_type' => 'processing_error',
                    'data' => $result
                ], 422);
            }

            // Partial success - some records succeeded, some failed
            if ($result['failed'] > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Bulk upload completed with errors. {$result['successful']} successful, {$result['failed']} failed.",
                    'data' => $result,
                    'has_errors' => true
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk upload processed successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk member upload error: ' . $e->getMessage(), [
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

    /**
     * Parse CSV file and extract member data
     */
    private function parseCSV($file): array
    {
        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            return [
                'success' => false,
                'errors' => ['Could not open CSV file']
            ];
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [
                'success' => false,
                'errors' => ['CSV file is empty or invalid']
            ];
        }

        $expectedHeaders = [
            'First Name', 'Last Name', 'Email', 'Phone', 'Staff ID', 'IPPIS Number',
            'Date of Birth (YYYY-MM-DD)', 'Gender (Male/Female)', 'Marital Status (Single/Married/Divorced/Widowed)',
            'Nationality', 'State of Origin', 'LGA', 'Residential Address', 'City', 'State',
            'Rank', 'Department', 'Command State', 'Employment Date (YYYY-MM-DD)', 'Years of Service',
            'Membership Type (Regular/Associate)'
        ];

        // Also accept headers with updated format mentioning DD-MM-YYYY
        $alternativeHeaders = [
            'First Name', 'Last Name', 'Email', 'Phone', 'Staff ID', 'IPPIS Number',
            'Date of Birth (YYYY-MM-DD or DD-MM-YYYY)', 'Gender (Male/Female)', 'Marital Status (Single/Married/Divorced/Widowed)',
            'Nationality', 'State of Origin', 'LGA', 'Residential Address', 'City', 'State',
            'Rank', 'Department', 'Command State', 'Employment Date (YYYY-MM-DD or DD-MM-YYYY)', 'Years of Service',
            'Membership Type (Regular/Associate)'
        ];

        // Normalize headers for comparison (remove extra spaces, case-insensitive)
        $normalizeHeaders = function($headerArray) {
            return array_map(function($h) {
                return strtolower(trim($h));
            }, $headerArray);
        };

        $normalizedExpected = $normalizeHeaders($expectedHeaders);
        $normalizedAlternative = $normalizeHeaders($alternativeHeaders);
        $normalizedActual = $normalizeHeaders($headers);

        if ($normalizedActual !== $normalizedExpected && $normalizedActual !== $normalizedAlternative) {
            fclose($handle);
            return [
                'success' => false,
                'errors' => ['CSV headers do not match expected format. Expected ' . count($expectedHeaders) . ' columns.']
            ];
        }

        $data = [];
        $errors = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            // Trim empty values from the end of the row (common issue with Excel exports)
            while (!empty($row) && empty(trim(end($row)))) {
                array_pop($row);
            }
            
            // If row has more columns than headers, trim excess columns
            if (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }
            
            if (count($row) !== count($headers)) {
                $errors[] = "Row {$lineNumber}: Expected " . count($headers) . " columns, found " . count($row);
                continue;
            }

            $memberData = array_combine($headers, $row);
            
            // Clean and validate basic data
            $memberData = array_map('trim', $memberData);
            
            // Normalize date field names - handle both old and new header formats
            if (isset($memberData['Date of Birth (YYYY-MM-DD or DD-MM-YYYY)'])) {
                $memberData['Date of Birth (YYYY-MM-DD)'] = $memberData['Date of Birth (YYYY-MM-DD or DD-MM-YYYY)'];
                unset($memberData['Date of Birth (YYYY-MM-DD or DD-MM-YYYY)']);
            }
            
            if (isset($memberData['Employment Date (YYYY-MM-DD or DD-MM-YYYY)'])) {
                $memberData['Employment Date (YYYY-MM-DD)'] = $memberData['Employment Date (YYYY-MM-DD or DD-MM-YYYY)'];
                unset($memberData['Employment Date (YYYY-MM-DD or DD-MM-YYYY)']);
            }
            
            // Skip empty rows
            if (empty(array_filter($memberData))) {
                continue;
            }

            $data[] = $memberData;
        }

        fclose($handle);

        return [
            'success' => true,
            'data' => $data,
            'errors' => $errors
        ];
    }

    /**
     * Parse Excel file and extract member data
     */
    private function parseExcel($file): array
    {
        try {
            $data = Excel::toArray(new class implements ToArray, WithHeadingRow {
                public function array(array $array): array
                {
                    return $array;
                }
            }, $file);

            if (empty($data) || empty($data[0])) {
                return [
                    'success' => false,
                    'errors' => ['Excel file is empty or invalid']
                ];
            }

            $rows = $data[0];
            $errors = [];
            $memberData = [];

            foreach ($rows as $index => $row) {
                $lineNumber = $index + 2; // +2 because line 1 is header and array is 0-indexed
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Map Excel headers to our expected format - handle both old and new header formats
                $mappedRow = [
                    'First Name' => $row['First Name'] ?? '',
                    'Last Name' => $row['Last Name'] ?? '',
                    'Email' => $row['Email'] ?? '',
                    'Phone' => $row['Phone'] ?? '',
                    'Staff ID' => $row['Staff ID'] ?? '',
                    'IPPIS Number' => $row['IPPIS Number'] ?? '',
                    'Date of Birth (YYYY-MM-DD)' => $row['Date of Birth (YYYY-MM-DD)'] 
                        ?? $row['Date of Birth (YYYY-MM-DD or DD-MM-YYYY)'] ?? '',
                    'Gender (Male/Female)' => $row['Gender (Male/Female)'] ?? '',
                    'Marital Status (Single/Married/Divorced/Widowed)' => $row['Marital Status (Single/Married/Divorced/Widowed)'] ?? '',
                    'Nationality' => $row['Nationality'] ?? '',
                    'State of Origin' => $row['State of Origin'] ?? '',
                    'LGA' => $row['LGA'] ?? '',
                    'Residential Address' => $row['Residential Address'] ?? '',
                    'City' => $row['City'] ?? '',
                    'State' => $row['State'] ?? '',
                    'Rank' => $row['Rank'] ?? '',
                    'Department' => $row['Department'] ?? '',
                    'Command State' => $row['Command State'] ?? '',
                    'Employment Date (YYYY-MM-DD)' => $row['Employment Date (YYYY-MM-DD)'] 
                        ?? $row['Employment Date (YYYY-MM-DD or DD-MM-YYYY)'] ?? '',
                    'Years of Service' => $row['Years of Service'] ?? '',
                    'Membership Type (Regular/Associate)' => $row['Membership Type (Regular/Associate)'] ?? '',
                ];

                // Clean and validate basic data
                $mappedRow = array_map('trim', $mappedRow);
                
                $memberData[] = $mappedRow;
            }

            return [
                'success' => true,
                'data' => $memberData,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Failed to parse Excel file: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Parse date string in multiple formats (YYYY-MM-DD or DD-MM-YYYY)
     */
    private function parseDate($dateString): ?\Carbon\Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        $dateString = trim($dateString);
        
        // Try YYYY-MM-DD format first
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
            try {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $dateString);
            } catch (\Exception $e) {
                // Continue to try other formats
            }
        }
        
        // Try DD-MM-YYYY format
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dateString)) {
            try {
                return \Carbon\Carbon::createFromFormat('d-m-Y', $dateString);
            } catch (\Exception $e) {
                // Continue to try other formats
            }
        }
        
        // Try DD/MM/YYYY format
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateString)) {
            try {
                return \Carbon\Carbon::createFromFormat('d/m/Y', $dateString);
            } catch (\Exception $e) {
                // Continue to try other formats
            }
        }
        
        // Try YYYY/MM/DD format
        if (preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $dateString)) {
            try {
                return \Carbon\Carbon::createFromFormat('Y/m/d', $dateString);
            } catch (\Exception $e) {
                // Continue to try other formats
            }
        }
        
        // Fallback to strtotime
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            try {
                return \Carbon\Carbon::createFromTimestamp($timestamp);
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return null;
    }

    /**
     * Validate member data
     */
    private function validateMemberData(array $members): array
    {
        $errors = [];
        $existingEmails = User::pluck('email')->toArray();
        $existingStaffIds = Member::pluck('staff_id')->toArray();

        foreach ($members as $index => $member) {
            $lineNumber = $index + 2; // +2 because line 1 is header

            // Required fields validation
            $requiredFields = [
                'First Name' => 'first_name',
                'Last Name' => 'last_name', 
                'Email' => 'email',
                'Phone' => 'phone',
                'Staff ID' => 'staff_id',
                'Date of Birth (YYYY-MM-DD)' => 'date_of_birth',
                'Gender (Male/Female)' => 'gender',
                'Department' => 'department',
                'Rank' => 'rank'
            ];

            foreach ($requiredFields as $field => $key) {
                if (empty($member[$field])) {
                    $errors[] = "Line {$lineNumber}: {$field} is required";
                }
            }

            // Email validation
            if (!empty($member['Email']) && !filter_var($member['Email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Line {$lineNumber}: Invalid email format";
            }

            // Check for duplicate emails
            if (!empty($member['Email']) && in_array($member['Email'], $existingEmails)) {
                $errors[] = "Line {$lineNumber}: Email already exists";
            }

            // Check for duplicate staff IDs
            if (!empty($member['Staff ID']) && in_array($member['Staff ID'], $existingStaffIds)) {
                $errors[] = "Line {$lineNumber}: Staff ID already exists";
            }

            // Date validation - support both YYYY-MM-DD and DD-MM-YYYY formats
            if (!empty($member['Date of Birth (YYYY-MM-DD)'])) {
                $parsedDate = $this->parseDate($member['Date of Birth (YYYY-MM-DD)']);
                if (!$parsedDate) {
                    $errors[] = "Line {$lineNumber}: Invalid date format for Date of Birth. Accepted formats: YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY";
                }
            }

            if (!empty($member['Employment Date (YYYY-MM-DD)'])) {
                $parsedDate = $this->parseDate($member['Employment Date (YYYY-MM-DD)']);
                if (!$parsedDate) {
                    $errors[] = "Line {$lineNumber}: Invalid date format for Employment Date. Accepted formats: YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY";
                }
            }

            // Gender validation
            if (!empty($member['Gender (Male/Female)']) && !in_array($member['Gender (Male/Female)'], ['Male', 'Female'])) {
                $errors[] = "Line {$lineNumber}: Gender must be Male or Female";
            }

            // Marital Status validation
            if (!empty($member['Marital Status (Single/Married/Divorced/Widowed)']) && 
                !in_array($member['Marital Status (Single/Married/Divorced/Widowed)'], ['Single', 'Married', 'Divorced', 'Widowed'])) {
                $errors[] = "Line {$lineNumber}: Invalid marital status";
            }

            // Membership Type validation
            if (!empty($member['Membership Type (Regular/Associate)']) && 
                !in_array($member['Membership Type (Regular/Associate)'], ['Regular', 'Associate'])) {
                $errors[] = "Line {$lineNumber}: Membership type must be Regular or Associate";
            }
        }

        return $errors;
    }

    /**
     * Process bulk members creation
     */
    private function processBulkMembers(array $members): array
    {
        $results = [
            'total' => count($members),
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];

        // Check for existing emails and staff IDs before processing
        $existingEmails = User::pluck('email')->toArray();
        $existingStaffIds = Member::pluck('staff_id')->toArray();

        DB::beginTransaction();

        try {
            foreach ($members as $index => $memberData) {
                $lineNumber = $index + 2;
                $memberName = trim(($memberData['First Name'] ?? '') . ' ' . ($memberData['Last Name'] ?? ''));

                try {
                    // Check for duplicate email
                    if (!empty($memberData['Email']) && in_array($memberData['Email'], $existingEmails)) {
                        throw new \Exception("Email '{$memberData['Email']}' already exists in the system");
                    }

                    // Check for duplicate staff ID
                    if (!empty($memberData['Staff ID']) && in_array($memberData['Staff ID'], $existingStaffIds)) {
                        throw new \Exception("Staff ID '{$memberData['Staff ID']}' already exists in the system");
                    }

                    // Validate required fields
                    if (empty($memberData['First Name'])) {
                        throw new \Exception("First Name is required");
                    }
                    if (empty($memberData['Last Name'])) {
                        throw new \Exception("Last Name is required");
                    }
                    if (empty($memberData['Email'])) {
                        throw new \Exception("Email is required");
                    }
                    if (empty($memberData['Phone'])) {
                        throw new \Exception("Phone is required");
                    }
                    if (empty($memberData['Staff ID'])) {
                        throw new \Exception("Staff ID is required");
                    }

                    // Validate email format
                    if (!filter_var($memberData['Email'], FILTER_VALIDATE_EMAIL)) {
                        throw new \Exception("Invalid email format: '{$memberData['Email']}'");
                    }

                    // Validate and parse date formats - support both YYYY-MM-DD and DD-MM-YYYY
                    $dob = null;
                    if (!empty($memberData['Date of Birth (YYYY-MM-DD)'])) {
                        $dob = $this->parseDate($memberData['Date of Birth (YYYY-MM-DD)']);
                        if (!$dob) {
                            throw new \Exception("Invalid date format for Date of Birth. Accepted formats: YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY");
                        }
                    }

                    $empDate = null;
                    if (!empty($memberData['Employment Date (YYYY-MM-DD)'])) {
                        $empDate = $this->parseDate($memberData['Employment Date (YYYY-MM-DD)']);
                        if (!$empDate) {
                            throw new \Exception("Invalid date format for Employment Date. Accepted formats: YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY");
                        }
                    }

                    // Create user first
                    $user = User::create([
                        'first_name' => $memberData['First Name'],
                        'last_name' => $memberData['Last Name'],
                        'email' => $memberData['Email'],
                        'phone' => $memberData['Phone'],
                        'password' => Hash::make('password123'), // Default password
                        'role' => 'member',
                        'email_verified_at' => now(),
                    ]);

                    // Add to existing arrays to prevent duplicates within the same batch
                    $existingEmails[] = $memberData['Email'];
                    $existingStaffIds[] = $memberData['Staff ID'];

                    // Create member with automatic KYC approval for CSV uploads
                    $member = Member::create([
                        'user_id' => $user->id,
                        'member_number' => $this->generateMemberNumber(),
                        'staff_id' => $memberData['Staff ID'],
                        'ippis_number' => $memberData['IPPIS Number'] ?? null,
                        'date_of_birth' => $dob,
                        'gender' => $memberData['Gender (Male/Female)'],
                        'marital_status' => $memberData['Marital Status (Single/Married/Divorced/Widowed)'] ?? 'Single',
                        'nationality' => $memberData['Nationality'] ?? 'Nigerian',
                        'state_of_origin' => $memberData['State of Origin'] ?? null,
                        'lga' => $memberData['LGA'] ?? null,
                        'residential_address' => $memberData['Residential Address'] ?? null,
                        'city' => $memberData['City'] ?? null,
                        'state' => $memberData['State'] ?? null,
                        'rank' => $memberData['Rank'],
                        'department' => $memberData['Department'],
                        'command_state' => $memberData['Command State'] ?? null,
                        'employment_date' => $empDate,
                        'years_of_service' => $memberData['Years of Service'] ?? null,
                        'membership_type' => $memberData['Membership Type (Regular/Associate)'] ?? 'Regular',
                        'kyc_status' => 'verified', // Automatically approve KYC for CSV uploads
                        'kyc_verified_at' => now(), // Set verification timestamp
                    ]);

                    $results['successful']++;

                } catch (\Illuminate\Database\QueryException $e) {
                    $results['failed']++;
                    $errorCode = $e->getCode();
                    $errorMessage = $e->getMessage();
                    
                    // Handle specific database errors
                    if ($errorCode == 23000) { // Integrity constraint violation
                        if (strpos($errorMessage, 'email') !== false) {
                            $results['errors'][] = "Line {$lineNumber} ({$memberName}): Email '{$memberData['Email']}' already exists in the database";
                        } elseif (strpos($errorMessage, 'staff_id') !== false) {
                            $results['errors'][] = "Line {$lineNumber} ({$memberName}): Staff ID '{$memberData['Staff ID']}' already exists in the database";
                        } else {
                            $results['errors'][] = "Line {$lineNumber} ({$memberName}): Database constraint violation - " . $e->getMessage();
                        }
                    } else {
                        $results['errors'][] = "Line {$lineNumber} ({$memberName}): Database error - " . $e->getMessage();
                    }
                    
                    Log::error("Bulk member upload - Line {$lineNumber} error", [
                        'error' => $e->getMessage(),
                        'member_data' => $memberData
                    ]);
                    
                } catch (\Exception $e) {
                    $results['failed']++;
                    $memberName = !empty($memberName) ? " ({$memberName})" : '';
                    $results['errors'][] = "Line {$lineNumber}{$memberName}: " . $e->getMessage();
                    
                    Log::error("Bulk member upload - Line {$lineNumber} error", [
                        'error' => $e->getMessage(),
                        'member_data' => $memberData
                    ]);
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            $results['errors'][] = "Transaction failed: " . $e->getMessage();
            Log::error('Bulk member upload transaction error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $results;
    }

    /**
     * Generate unique member number
     */
    private function generateMemberNumber(): string
    {
        do {
            $memberNumber = 'FRSC/' . date('Y') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Member::where('member_number', $memberNumber)->exists());

        return $memberNumber;
    }
}
