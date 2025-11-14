<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BulkLoanController extends Controller
{
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'Member ID (UUID or Staff ID)',
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

                Loan::create([
                    'member_id' => $member->id,
                    'amount' => floatval($data['Loan Amount'] ?? 0),
                    'interest_rate' => floatval($data['Interest Rate (%)'] ?? 0),
                    'duration_months' => intval($data['Duration (Months)'] ?? 12),
                    'type' => $data['Type'] ?? 'personal',
                    'purpose' => $data['Purpose'] ?? null,
                    'status' => 'pending',
                    'application_date' => $data['Application Date (YYYY-MM-DD)'] ?? now(),
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

