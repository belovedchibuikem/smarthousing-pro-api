<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BulkContributionController extends Controller
{
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'Member ID (UUID or Staff ID)',
            'Amount',
            'Contribution Type',
            'Payment Method',
            'Payment Date (YYYY-MM-DD)',
            'Notes'
        ];

        $csvContent = implode(',', $template) . "\n";
        
        $sampleData = [
            'FRSC/HMS/2024/001',
            '50000',
            'monthly',
            'bank_transfer',
            '2024-01-15',
            'Monthly contribution for January'
        ];
        
        $csvContent .= implode(',', $sampleData) . "\n";

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'contributions_upload_template.csv'
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
                $member = Member::where('member_id', $data['Member ID (UUID or Staff ID)'])
                    ->orWhere('staff_id', $data['Member ID (UUID or Staff ID)'])
                    ->first();

                if (!$member) {
                    $errors[] = "Line {$lineNumber}: Member not found";
                    $failed++;
                    continue;
                }

                Contribution::create([
                    'member_id' => $member->id,
                    'amount' => floatval($data['Amount'] ?? 0),
                    'type' => $data['Contribution Type'] ?? 'monthly',
                    'payment_method' => $data['Payment Method'] ?? 'bank_transfer',
                    'payment_date' => $data['Payment Date (YYYY-MM-DD)'] ?? now(),
                    'status' => 'completed',
                    'notes' => $data['Notes'] ?? null,
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

