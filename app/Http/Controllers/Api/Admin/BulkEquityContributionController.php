<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EquityContribution;
use App\Models\Tenant\EquityPlan;
use App\Models\Tenant\EquityWalletBalance;
use App\Models\Tenant\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BulkEquityContributionController extends Controller
{
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            ['member_number', 'amount', 'plan_id (optional)', 'payment_method', 'payment_reference (optional)', 'notes (optional)'],
            ['M001', '50000', '', 'manual', 'REF-001', 'Monthly equity contribution'],
            ['M002', '75000', '', 'bank_transfer', 'REF-002', ''],
        ];

        $csv = fopen('php://temp', 'r+');
        foreach ($template as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        return response()->json([
            'success' => true,
            'template' => $csvContent,
            'filename' => 'equity_contributions_template.csv'
        ]);
    }

    public function uploadBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $csvData = array_map('str_getcsv', file($file->getRealPath()));
            $header = array_shift($csvData);

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($csvData as $index => $row) {
                $rowNumber = $index + 2; // +2 because header is row 1, and array is 0-indexed

                try {
                    $memberNumber = trim($row[0] ?? '');
                    $amount = trim($row[1] ?? '');
                    $planId = !empty($row[2]) ? trim($row[2]) : null;
                    $paymentMethod = trim($row[3] ?? 'manual');
                    $paymentReference = !empty($row[4]) ? trim($row[4]) : 'BULK-' . time() . '-' . $rowNumber;
                    $notes = !empty($row[5]) ? trim($row[5]) : null;

                    // Validate row data
                    if (empty($memberNumber)) {
                        $errors[] = "Row {$rowNumber}: Member number is required";
                        $errorCount++;
                        continue;
                    }

                    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
                        $errors[] = "Row {$rowNumber}: Invalid amount";
                        $errorCount++;
                        continue;
                    }

                    // Find member
                    $member = Member::where('member_number', $memberNumber)->first();
                    if (!$member) {
                        $errors[] = "Row {$rowNumber}: Member with number '{$memberNumber}' not found";
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
                        if ($amount < $plan->min_amount) {
                            $errors[] = "Row {$rowNumber}: Amount below minimum for selected plan";
                            $errorCount++;
                            continue;
                        }
                        if ($plan->max_amount && $amount > $plan->max_amount) {
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

                    // Create contribution
                    $contribution = EquityContribution::create([
                        'member_id' => $member->id,
                        'plan_id' => $planId,
                        'amount' => $amount,
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference,
                        'status' => in_array($paymentMethod, ['paystack', 'remita', 'stripe']) ? 'approved' : 'pending',
                        'notes' => $notes,
                        'approved_at' => in_array($paymentMethod, ['paystack', 'remita', 'stripe']) ? now() : null,
                        'paid_at' => in_array($paymentMethod, ['paystack', 'remita', 'stripe']) ? now() : null,
                    ]);

                    // If auto-approved (payment gateway), add to wallet
                    if (in_array($paymentMethod, ['paystack', 'remita', 'stripe'])) {
                        $this->addToEquityWallet($contribution);
                    }

                    $successCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                    Log::error("Bulk equity contribution upload error - Row {$rowNumber}", [
                        'error' => $e->getMessage(),
                        'row' => $row,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk upload completed. {$successCount} successful, {$errorCount} errors.",
                'data' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => $errors,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk equity contribution upload failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk upload',
                'error' => $e->getMessage()
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

