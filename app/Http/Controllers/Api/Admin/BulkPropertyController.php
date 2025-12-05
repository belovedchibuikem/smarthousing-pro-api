<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Property;
use App\Traits\HandlesBulkFileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BulkPropertyController extends Controller
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
            'Title',
            'Description',
            'Location',
            'Address',
            'City',
            'State',
            'Property Type',
            'Price',
            'Size (sqft)',
            'Bedrooms',
            'Bathrooms',
            'Status (available/reserved/sold)',
            'Features (comma-separated)'
        ];

        $sampleData = [
            [
                '3 Bedroom Duplex',
                'Beautiful 3 bedroom duplex in a prime location',
                'Wuse, Abuja',
                '123 Main Street, Wuse',
                'Abuja',
                'FCT',
                'Duplex',
                '15000000',
                '3500',
                '3',
                '3',
                'available',
                'Swimming Pool,Gym,Parking'
            ],
            [
                '2 Bedroom Apartment',
                'Modern apartment with great amenities',
                'Victoria Island, Lagos',
                '456 Victoria Island, Lagos',
                'Lagos',
                'Lagos',
                'Apartment',
                '8500000',
                '1800',
                '2',
                '2',
                'available',
                'Elevator,Security,Generator'
            ],
        ];

        $csvContent = implode(',', array_map($escapeCsv, $headers)) . "\n";
        foreach ($sampleData as $row) {
            $csvContent .= implode(',', array_map($escapeCsv, $row)) . "\n";
        }

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'properties_upload_template.csv'
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
                    'message' => 'No property data found in file',
                    'errors' => ['The file appears to be empty or contains no valid property data.'],
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
                    $title = $findValue(['Title', 'title']);
                    $description = $findValue(['Description', 'description']);
                    $location = $findValue(['Location', 'location']);
                    $address = $findValue(['Address', 'address']);
                    $city = $findValue(['City', 'city']);
                    $state = $findValue(['State', 'state']);
                    $propertyType = $findValue([
                        'Property Type',
                        'property_type',
                        'PropertyType',
                        'Type',
                        'type'
                    ], 'other');
                    $price = $findValue(['Price', 'price']);
                    $sizeSqft = $findValue([
                        'Size (sqft)',
                        'size_sqft',
                        'Size',
                        'size',
                        'Size Sqft',
                        'size_sqft'
                    ]);
                    $bedrooms = $findValue(['Bedrooms', 'bedrooms']);
                    $bathrooms = $findValue(['Bathrooms', 'bathrooms']);
                    $status = $findValue([
                        'Status (available/reserved/sold)',
                        'status_available_reserved_sold',
                        'Status',
                        'status'
                    ], 'available');
                    $featuresStr = $findValue([
                        'Features (comma-separated)',
                        'features_comma_separated',
                        'Features',
                        'features'
                    ]);

                    // Validate required fields
                    if (empty($title)) {
                        $availableKeys = implode(', ', array_keys($data));
                        $errors[] = "Row {$lineNumber}: Title is required. Available columns: {$availableKeys}";
                        $failed++;
                        continue;
                    }

                    if (empty($location)) {
                        $errors[] = "Row {$lineNumber}: Location is required";
                        $failed++;
                        continue;
                    }

                    if (empty($address)) {
                        $errors[] = "Row {$lineNumber}: Address is required";
                        $failed++;
                        continue;
                    }

                    if (empty($price) || !is_numeric($price) || floatval($price) <= 0) {
                        $errors[] = "Row {$lineNumber}: Price is required and must be greater than 0";
                        $failed++;
                        continue;
                    }

                    // Validate status
                    $validStatuses = ['available', 'reserved', 'sold'];
                    $status = strtolower($status);
                    if (!in_array($status, $validStatuses)) {
                        $errors[] = "Row {$lineNumber}: Invalid status '{$status}'. Must be one of: " . implode(', ', $validStatuses);
                        $failed++;
                        continue;
                    }

                    // Parse features
                    $features = [];
                    if (!empty($featuresStr)) {
                        $features = array_map('trim', explode(',', $featuresStr));
                        $features = array_filter($features); // Remove empty values
                    }

                    // Create property
                    Property::create([
                        'title' => $title,
                        'description' => !empty($description) ? $description : null,
                        'location' => $location, // Required field
                        'address' => $address,
                        'city' => !empty($city) ? $city : null,
                        'state' => !empty($state) ? $state : null,
                        'property_type' => $propertyType,
                        'price' => floatval($price),
                        'size' => !empty($sizeSqft) ? floatval($sizeSqft) : null, // Use 'size' column, not 'size_sqft'
                        'bedrooms' => !empty($bedrooms) ? intval($bedrooms) : null,
                        'bathrooms' => !empty($bathrooms) ? intval($bathrooms) : null,
                        'status' => $status,
                        'features' => $features,
                    ]);

                    $successful++;
                } catch (\Illuminate\Database\QueryException $e) {
                    DB::rollBack();
                    $errorCode = $e->getCode();
                    $propertyTitle = $title ?? 'unknown';
                    
                    if ($errorCode == 23000) {
                        $errors[] = "Row {$lineNumber} (Property: {$propertyTitle}): Database constraint violation - " . $e->getMessage();
                    } else {
                        $errors[] = "Row {$lineNumber} (Property: {$propertyTitle}): Database error - " . $e->getMessage();
                    }
                    $failed++;
                    Log::error("Bulk property upload - Row {$lineNumber} database error", [
                        'error' => $e->getMessage(),
                        'data' => $data
                    ]);
                    DB::beginTransaction(); // Restart transaction for next row
                } catch (\Exception $e) {
                    Log::error("BulkPropertyController error on row {$lineNumber}: " . $e->getMessage(), [
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
                    'message' => 'All property records failed to process',
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
            
            Log::error('Bulk property upload error: ' . $e->getMessage(), [
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

