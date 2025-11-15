<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminPaymentEvidenceController extends Controller
{
    /**
     * Upload payment evidence file for tenant admin
     */
    public function uploadPaymentEvidence(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
            ]);

            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file provided',
                ], 400);
            }

            $file = $request->file('file');
            $tenantId = tenant('id');

            $baseDirectory = 'admin-payments/evidence';
            if ($tenantId) {
                $baseDirectory = "tenants/{$tenantId}/{$baseDirectory}";
            }

            $storedPath = Storage::disk('public')->putFile($baseDirectory, $file);
            $fileUrl = Storage::disk('public')->url($storedPath);

            return response()
                ->json([
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'url' => $fileUrl,
                    'path' => $storedPath,
                ], 201);
        } catch (\Throwable $exception) {
            Log::error('AdminPaymentEvidenceController::uploadPaymentEvidence failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
                'tenant_id' => tenant('id'),
                'user_id' => optional($request->user()->id),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to upload payment evidence at the moment. Please try again later.'
                    : $exception->getMessage(),
            ], 500);
        }
    }
}

