<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BulkPropertyController extends Controller
{
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'Title',
            'Description',
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

        $csvContent = implode(',', $template) . "\n";
        
        $sampleData = [
            '3 Bedroom Duplex',
            'Beautiful 3 bedroom duplex in a prime location',
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
        ];
        
        $csvContent .= implode(',', $sampleData) . "\n";

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'properties_upload_template.csv'
        ]);
    }

    public function uploadBulk(Request $request): JsonResponse
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
        $handle = fopen($file->getPathname(), 'r');
        
        if (!$handle) {
            return response()->json([
                'success' => false,
                'message' => 'Could not open file'
            ], 422);
        }

        $headers = fgetcsv($handle);
        $successful = 0;
        $failed = 0;
        $errors = [];

        $lineNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            if (count($row) !== count($headers)) {
                $errors[] = "Line {$lineNumber}: Invalid number of columns";
                $failed++;
                continue;
            }

            $data = array_combine($headers, $row);
            $data = array_map('trim', $data);

            if (empty(array_filter($data))) {
                continue;
            }

            try {
                $features = !empty($data['Features (comma-separated)']) 
                    ? explode(',', $data['Features (comma-separated)'])
                    : [];

                Property::create([
                    'title' => $data['Title'] ?? null,
                    'description' => $data['Description'] ?? null,
                    'address' => $data['Address'] ?? null,
                    'city' => $data['City'] ?? null,
                    'state' => $data['State'] ?? null,
                    'property_type' => $data['Property Type'] ?? 'other',
                    'price' => floatval($data['Price'] ?? 0),
                    'size_sqft' => intval($data['Size (sqft)'] ?? 0),
                    'bedrooms' => intval($data['Bedrooms'] ?? 0),
                    'bathrooms' => intval($data['Bathrooms'] ?? 0),
                    'status' => $data['Status (available/reserved/sold)'] ?? 'available',
                    'features' => $features,
                ]);

                $successful++;
            } catch (\Exception $e) {
                $errors[] = "Line {$lineNumber}: " . $e->getMessage();
                $failed++;
            }
        }

        fclose($handle);

        return response()->json([
            'success' => true,
            'message' => 'Bulk upload processed',
            'data' => [
                'total' => $lineNumber - 1,
                'successful' => $successful,
                'failed' => $failed,
                'errors' => array_slice($errors, 0, 50),
            ]
        ]);
    }
}

