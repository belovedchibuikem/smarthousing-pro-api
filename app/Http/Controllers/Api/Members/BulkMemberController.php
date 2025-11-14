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
use Illuminate\Support\Str;
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
            'Date of Birth (YYYY-MM-DD)',
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
            'Employment Date (YYYY-MM-DD)',
            'Years of Service',
            'Membership Type (Regular/Associate)'
        ];

        $csvContent = implode(',', $template) . "\n";
        
        // Add sample data
        $sampleData = [
            'John',
            'Doe',
            'john.doe@frsc.gov.ng',
            '08012345678',
            'FRSC/2024/001',
            'IPPIS001',
            '1990-01-15',
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
            '2020-01-15',
            '4',
            'Regular'
        ];
        
        $csvContent .= implode(',', $sampleData) . "\n";

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
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
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
                'errors' => $parsedData['errors']
            ], 422);
        }

        $members = $parsedData['data'];
        $validationErrors = $this->validateMemberData($members);
        
        if (!empty($validationErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors found',
                'errors' => $validationErrors
            ], 422);
        }

        $result = $this->processBulkMembers($members);

        return response()->json([
            'success' => true,
            'message' => 'Bulk upload processed successfully',
            'data' => $result
        ]);
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

        if (count($headers) !== count($expectedHeaders)) {
            fclose($handle);
            return [
                'success' => false,
                'errors' => ['CSV headers do not match expected format']
            ];
        }

        $data = [];
        $errors = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            if (count($row) !== count($headers)) {
                $errors[] = "Line {$lineNumber}: Invalid number of columns";
                continue;
            }

            $memberData = array_combine($headers, $row);
            
            // Clean and validate basic data
            $memberData = array_map('trim', $memberData);
            
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

                // Map Excel headers to our expected format
                $mappedRow = [
                    'First Name' => $row['First Name'] ?? '',
                    'Last Name' => $row['Last Name'] ?? '',
                    'Email' => $row['Email'] ?? '',
                    'Phone' => $row['Phone'] ?? '',
                    'Staff ID' => $row['Staff ID'] ?? '',
                    'IPPIS Number' => $row['IPPIS Number'] ?? '',
                    'Date of Birth (YYYY-MM-DD)' => $row['Date of Birth (YYYY-MM-DD)'] ?? '',
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
                    'Employment Date (YYYY-MM-DD)' => $row['Employment Date (YYYY-MM-DD)'] ?? '',
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

            // Date validation
            if (!empty($member['Date of Birth (YYYY-MM-DD)']) && !strtotime($member['Date of Birth (YYYY-MM-DD)'])) {
                $errors[] = "Line {$lineNumber}: Invalid date format for Date of Birth";
            }

            if (!empty($member['Employment Date (YYYY-MM-DD)']) && !strtotime($member['Employment Date (YYYY-MM-DD)'])) {
                $errors[] = "Line {$lineNumber}: Invalid date format for Employment Date";
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

        DB::beginTransaction();

        try {
            foreach ($members as $index => $memberData) {
                $lineNumber = $index + 2;

                try {
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

                    // Create member
                    $member = Member::create([
                        'user_id' => $user->id,
                        'member_number' => $this->generateMemberNumber(),
                        'staff_id' => $memberData['Staff ID'],
                        'ippis_number' => $memberData['IPPIS Number'] ?? null,
                        'date_of_birth' => $memberData['Date of Birth (YYYY-MM-DD)'] ? 
                            \Carbon\Carbon::parse($memberData['Date of Birth (YYYY-MM-DD)']) : null,
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
                        'employment_date' => $memberData['Employment Date (YYYY-MM-DD)'] ? 
                            \Carbon\Carbon::parse($memberData['Employment Date (YYYY-MM-DD)']) : null,
                        'years_of_service' => $memberData['Years of Service'] ?? null,
                        'membership_type' => $memberData['Membership Type (Regular/Associate)'] ?? 'Regular',
                        'kyc_status' => 'pending',
                    ]);

                    $results['successful']++;

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Line {$lineNumber}: " . $e->getMessage();
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            $results['errors'][] = "Database error: " . $e->getMessage();
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
