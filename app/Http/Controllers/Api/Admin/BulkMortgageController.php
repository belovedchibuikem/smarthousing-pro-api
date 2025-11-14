<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Mortgage;
use App\Models\Tenant\MortgageProvider;
use App\Models\Tenant\Member;
use App\Models\Tenant\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BulkMortgageController extends Controller
{
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'Member ID (UUID or Staff ID)',
            'Mortgage Provider Name',
            'Property ID (UUID)',
            'Loan Amount',
            'Interest Rate (%)',
            'Tenure (Years)',
            'Notes'
        ];

        $csvContent = implode(',', $template) . "\n";
        
        $sampleData = [
            'FRSC/HMS/2024/001',
            'Federal Mortgage Bank of Nigeria',
            'PROP-UUID-123',
            '15000000',
            '6.5',
            '20',
            'Mortgage for property purchase'
        ];
        
        $csvContent .= implode(',', $sampleData) . "\n";

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'mortgages_upload_template.csv'
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
                // Find member
                $member = Member::where('member_id', $data['Member ID (UUID or Staff ID)'])
                    ->orWhere('staff_id', $data['Member ID (UUID or Staff ID)'])
                    ->first();

                if (!$member) {
                    $errors[] = "Line {$lineNumber}: Member not found";
                    $failed++;
                    continue;
                }

                // Find provider
                $provider = MortgageProvider::where('name', $data['Mortgage Provider Name'])->first();
                
                // Find property if provided
                $property = null;
                if (!empty($data['Property ID (UUID)'])) {
                    $property = Property::find($data['Property ID (UUID)']);
                }

                $loanAmount = floatval($data['Loan Amount']);
                $interestRate = floatval($data['Interest Rate (%)']);
                $tenureYears = intval($data['Tenure (Years)']);
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
                'errors' => array_slice($errors, 0, 50), // Limit errors
            ]
        ]);
    }
}

