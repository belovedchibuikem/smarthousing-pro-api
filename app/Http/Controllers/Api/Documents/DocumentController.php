<?php

namespace App\Http\Controllers\Api\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentRequest;
use App\Http\Resources\Documents\DocumentResource;
use App\Models\Tenant\Document;
use App\Services\Communication\NotificationService;
use App\Services\Tenant\TenantAuditLogService;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected TenantAuditLogService $auditLogService,
        protected ActivityLogService $activityLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Document::with(['member.user']);

        // Filter by user if not admin
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        if ($user->role !== 'admin') {
            $query->whereHas('member', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by member_id
        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        $documents = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'documents' => DocumentResource::collection($documents),
            'pagination' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ]
        ]);
    }

    public function store(DocumentRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        $member = $user->member;
        
        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $document = Document::create([
            'member_id' => $member->id,
            'type' => $request->type,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $request->file_path,
            'file_size' => $request->file_size,
            'mime_type' => $request->mime_type,
            'status' => 'pending',
            'uploaded_by' => $user->id,
        ]);

        // Notify admins about new document submission
        $memberName = $member->first_name . ' ' . $member->last_name;
        $this->notificationService->notifyAdminsNewDocument(
            $document->id,
            $memberName,
            $request->type
        );

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'document' => new DocumentResource($document->load('member.user'))
        ], 201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        // Check if user can view this document
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        if ($user->role !== 'admin' && $document->member->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $document->load(['member.user']);

        return response()->json([
            'document' => new DocumentResource($document)
        ]);
    }

    public function update(DocumentRequest $request, Document $document): JsonResponse
    {
        // Check if user can update this document
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        if ($user->role !== 'admin' && $document->member->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $document->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Document updated successfully',
            'document' => new DocumentResource($document->load('member.user'))
        ]);
    }

    public function approve(Request $request, Document $document): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        if ($document->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending documents can be approved'
            ], 400);
        }

        $document->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        // Log audit event
        $this->auditLogService->logApproval(
            $document,
            'Document',
            $user,
            [
                'document_type' => $document->type,
                'member_id' => $document->member_id,
            ]
        );
        
        // Log activity event
        $this->activityLogService->logModelAction(
            'approve',
            $document,
            $user,
            [
                'document_type' => $document->type,
                'member_id' => $document->member_id,
                'document_id' => $document->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Document approved successfully'
        ]);
    }

    public function reject(Request $request, Document $document): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if ($document->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending documents can be rejected'
            ], 400);
        }

        $document->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'rejected_at' => now(),
            'rejected_by' => $user->id,
        ]);

        // Log audit event
        $this->auditLogService->logRejection(
            $document,
            'Document',
            $request->reason,
            $user,
            [
                'document_type' => $document->type,
                'member_id' => $document->member_id,
            ]
        );
        
        // Log activity event
        $this->activityLogService->logModelAction(
            'reject',
            $document,
            $user,
            [
                'document_type' => $document->type,
                'member_id' => $document->member_id,
                'document_id' => $document->id,
                'reason' => $request->reason,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Document rejected successfully'
        ]);
    }

    public function download(Request $request, Document $document): JsonResponse
    {
        // Check if user can download this document
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        if ($user->role !== 'admin' && $document->member->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Storage::exists($document->file_path)) {
            return response()->json([
                'message' => 'File not found'
            ], 404);
        }

        return response()->json([
            'download_url' => Storage::url($document->file_path),
            'filename' => $document->title,
            'mime_type' => $document->mime_type,
        ]);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        // Check if user can delete this document
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        if ($user->role !== 'admin' && $document->member->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete file from storage
        if (Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully'
        ]);
    }

    /**
     * Public document upload during registration (no authentication required)
     */
    public function uploadPublic(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'file_path' => 'required|string',
            'file_size' => 'required|integer',
            'mime_type' => 'required|string|max:255',
            'member_id' => 'nullable|uuid|exists:members,id', // Optional for registration
            'session_id' => 'nullable|string|max:255', // For tracking during registration
        ]);

        $document = Document::create([
            'member_id' => $request->member_id, // Can be null during registration
            'type' => $request->type,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $request->file_path,
            'file_size' => $request->file_size,
            'mime_type' => $request->mime_type,
            'status' => 'pending',
            'uploaded_by' => null, // No user during registration
            'session_id' => $request->session_id, // Track registration session
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'document' => [
                'id' => $document->id,
                'type' => $document->type,
                'title' => $document->title,
                'status' => $document->status,
                'uploaded_at' => $document->created_at,
            ]
        ], 201);
    }

    /**
     * Public document view (no authentication required)
     */
    public function viewPublic(Document $document): JsonResponse
    {
        // Only allow viewing if document is approved or if it's a public document
        if ($document->status !== 'approved' && $document->status !== 'pending') {
            return response()->json(['message' => 'Document not available'], 404);
        }

        return response()->json([
            'document' => [
                'id' => $document->id,
                'type' => $document->type,
                'title' => $document->title,
                'description' => $document->description,
                'status' => $document->status,
                'file_size' => $document->file_size,
                'mime_type' => $document->mime_type,
                'uploaded_at' => $document->created_at,
                'download_url' => $document->status === 'approved' ? Storage::url($document->file_path) : null,
            ]
        ]);
    }
}
