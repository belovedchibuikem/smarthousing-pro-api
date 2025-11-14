<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Member;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BulkRefundController extends Controller
{
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'Member ID (UUID or Staff ID)',
            'Amount',
            'Source (contribution/investment_return/investment)',
            'Reason',
            'Notes'
        ];

        $csvContent = implode(',', $template) . "\n";
        
        $sampleData = [
            'FRSC/HMS/2024/001',
            '50000',
            'contribution',
            'Refund for overpayment',
            'Overpayment refund processed'
        ];
        
        $csvContent .= implode(',', $sampleData) . "\n";

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'refunds_upload_template.csv'
        ]);
    }

    public function uploadBulk(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

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
        DB::beginTransaction();

        try {
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
                    $member = Member::with('user.wallet')->where('member_id', $data['Member ID (UUID or Staff ID)'])
                        ->orWhere('staff_id', $data['Member ID (UUID or Staff ID)'])
                        ->first();

                    if (!$member) {
                        $errors[] = "Line {$lineNumber}: Member not found";
                        $failed++;
                        continue;
                    }

                    $wallet = $member->user->wallet ?? Wallet::create(['user_id' => $member->user_id, 'balance' => 0]);

                    $amount = floatval($data['Amount'] ?? 0);
                    $source = $data['Source (contribution/investment_return/investment)'] ?? 'contribution';

                    if ($amount <= 0) {
                        $errors[] = "Line {$lineNumber}: Invalid amount";
                        $failed++;
                        continue;
                    }

                    // Deduct from wallet
                    $wallet->decrement('balance', $amount);

                    // Create transaction record
                    WalletTransaction::create([
                        'wallet_id' => $wallet->id,
                        'type' => 'debit',
                        'amount' => $amount,
                        'description' => "Refund: {$data['Reason']} ({$source})",
                        'reference' => 'REF-' . strtoupper(uniqid()),
                        'status' => 'completed',
                        'metadata' => [
                            'source' => $source,
                            'reason' => $data['Reason'] ?? null,
                            'notes' => $data['Notes'] ?? null,
                            'processed_by' => $user->id,
                            'bulk_upload' => true,
                        ],
                    ]);

                    $successful++;
                } catch (\Exception $e) {
                    $errors[] = "Line {$lineNumber}: " . $e->getMessage();
                    $failed++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            return response()->json([
                'success' => false,
                'message' => 'Bulk upload failed',
                'error' => $e->getMessage()
            ], 500);
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

