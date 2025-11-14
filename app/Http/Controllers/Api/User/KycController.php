<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\KycRequest;
use App\Http\Resources\User\KycResource;
use App\Models\Tenant\Member;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class KycController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $member = $request->user()->member;
        
        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        return response()->json([
            'kyc' => new KycResource($member)
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $member = $request->user()->member;
       
        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $member->update([
            'kyc_status' => 'submitted',
            'kyc_submitted_at' => now(),
            'kyc_rejection_reason' => null,
        ]);

        // Notify admins about new KYC submission
        $memberName = trim(
            sprintf(
                '%s %s',
                $member->user?->first_name ?? '',
                $member->user?->last_name ?? ''
            )
        );
        $this->notificationService->notifyAdminsNewKycSubmission(
            $member->id,
            $memberName
        );

        return response()->json([
            'success' => true,
            'message' => 'KYC documents submitted successfully',
            'kyc' => new KycResource($member)
        ]);
    }

    public function uploadDocuments(Request $request): JsonResponse
    {
        try {
            if ($request->hasFile('documents') && !is_array($request->file('documents'))) {
                $request->files->set('documents', [$request->file('documents')]);
            }

            if ($request->has('document_types') && !is_array($request->input('document_types'))) {
                $request->merge([
                    'document_types' => [$request->input('document_types')],
                ]);
            }
            
            $request->validate([
                'documents' => 'required|array',
                'documents.*' => 'file|mimes:pdf,jpeg,png,jpg|max:5120', // 5MB max
                'document_types' => 'required|array',
                'document_types.*' => 'string|in:national_id,passport,drivers_license,bank_statement,employment_letter,utility_bill'
            ]);
          
            if (!$request->hasFile('documents')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No documents provided',
                ], 400);
            }

            $member = $request->user()->member;
            
            if (!$member) {
                return response()->json([
                    'message' => 'Member profile not found'
                ], 404);
            }

            $uploadedDocuments = [];
            
            foreach ($request->file('documents') as $index => $document) {
                $path = $document->store('kyc-documents', 'public');
                $uploadedDocuments[] = [
                    'type' => $request->document_types[$index] ?? null,
                    'path' => $path,
                    'uploaded_at' => now(),
                ];
            }

            $member->update([
                'kyc_documents' => array_merge($member->kyc_documents ?? [], $uploadedDocuments)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Documents uploaded successfully',
                'documents' => $uploadedDocuments
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('KycController::uploadDocuments failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
                'tenant_id' => tenant('id'),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to upload documents at the moment. Please try again later.'
                    : $exception->getMessage(),
            ], 500);
        }
    }
}
