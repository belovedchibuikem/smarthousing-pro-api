<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\Member;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminDocumentsController extends Controller
{
    /**
     * Get all documents (admin view)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search', '');
            $typeFilter = $request->get('type', 'all');
            $statusFilter = $request->get('status', 'all');

            $query = Document::with(['member.user:id,first_name,last_name,email', 'member:id,member_number']);

            // Search filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('type', 'like', "%{$search}%")
                      ->orWhereHas('member.user', function($userQ) use ($search) {
                          $userQ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                      })
                      ->orWhereHas('member', function($memberQ) use ($search) {
                          $memberQ->where('member_number', 'like', "%{$search}%");
                      });
                });
            }

            // Type filter
            if ($typeFilter !== 'all') {
                $query->where('type', $typeFilter);
            }

            // Status filter
            if ($statusFilter !== 'all') {
                $query->where('status', $statusFilter);
            }

            $documents = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            // Stats
            $stats = [
                'total' => Document::count(),
                'pending' => Document::where('status', 'pending')->count(),
                'approved' => Document::where('status', 'approved')->count(),
                'rejected' => Document::where('status', 'rejected')->count(),
                'storage_used' => Document::sum('file_size'),
            ];

            $documentsData = $documents->map(function($doc) {
                $memberName = 'N/A';
                $memberId = 'N/A';
                
                if ($doc->member && $doc->member->user) {
                    $memberName = $doc->member->user->first_name . ' ' . $doc->member->user->last_name;
                    $memberId = $doc->member->member_number ?? 'N/A';
                }

                // Determine file type from mime type
                $fileType = 'PDF';
                if (str_contains($doc->mime_type, 'image')) {
                    $fileType = 'Image';
                } elseif (str_contains($doc->mime_type, 'word')) {
                    $fileType = 'DOC';
                }

                return [
                    'id' => $doc->id,
                    'name' => $doc->title,
                    'type' => $doc->type,
                    'member' => $memberName,
                    'member_id' => $memberId,
                    'upload_date' => $doc->created_at->format('M d, Y'),
                    'status' => $doc->status,
                    'file_type' => $fileType,
                    'size' => $this->formatFileSize($doc->file_size),
                    'mime_type' => $doc->mime_type,
                    'description' => $doc->description,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $documentsData,
                'stats' => [
                    'total' => $stats['total'],
                    'pending' => $stats['pending'],
                    'approved' => $stats['approved'],
                    'rejected' => $stats['rejected'],
                    'storage_used' => $this->formatFileSize($stats['storage_used']),
                ],
                'pagination' => [
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve documents',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Upload document (admin can upload for any member)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
                'type' => 'required|string|max:255',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'member_id' => 'nullable|uuid|exists:members,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $file = $request->file('file');
            $memberId = $request->member_id;

            // If member_id provided (could be UUID or member_number), verify it exists
            if ($memberId) {
                $member = Member::where('id', $memberId)
                    ->orWhere('member_number', $memberId)
                    ->first();
                
                if (!$member) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Member not found'
                    ], 404);
                }
                
                $memberId = $member->id; // Use UUID
            }

            // Store file
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('documents', $fileName, 'public');

            $document = Document::create([
                'member_id' => $memberId,
                'type' => $request->type,
                'title' => $request->title,
                'description' => $request->description,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'status' => 'pending',
                'uploaded_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'type' => $document->type,
                    'status' => $document->status,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get single document
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $document = Document::with(['member.user', 'member:id,member_number'])
                ->findOrFail($id);

            $memberName = 'N/A';
            $memberId = 'N/A';
            
            if ($document->member && $document->member->user) {
                $memberName = $document->member->user->first_name . ' ' . $document->member->user->last_name;
                $memberId = $document->member->member_number ?? 'N/A';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'type' => $document->type,
                    'description' => $document->description,
                    'member' => $memberName,
                    'member_id' => $memberId,
                    'status' => $document->status,
                    'file_size' => $this->formatFileSize($document->file_size),
                    'mime_type' => $document->mime_type,
                    'upload_date' => $document->created_at->format('Y-m-d H:i:s'),
                    'approved_at' => $document->approved_at ? $document->approved_at->format('Y-m-d H:i:s') : null,
                    'rejected_at' => $document->rejected_at ? $document->rejected_at->format('Y-m-d H:i:s') : null,
                    'rejection_reason' => $document->rejection_reason,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 404);
        }
    }

    /**
     * Download document
     */
    public function download(Request $request, string $id): JsonResponse
    {
        try {
            $document = Document::findOrFail($id);

            if (!Storage::disk('public')->exists($document->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $url = Storage::disk('public')->url($document->file_path);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $url,
                    'filename' => $document->title . '.' . pathinfo($document->file_path, PATHINFO_EXTENSION),
                    'mime_type' => $document->mime_type,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get download URL',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * View document (get view URL)
     */
    public function view(Request $request, string $id): JsonResponse
    {
        try {
            $document = Document::findOrFail($id);

            if (!Storage::disk('public')->exists($document->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $url = Storage::disk('public')->url($document->file_path);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'view_url' => $url,
                    'filename' => $document->title,
                    'mime_type' => $document->mime_type,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get view URL',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Approve document
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $document = Document::findOrFail($id);
            $user = $request->user();

            if ($document->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending documents can be approved'
                ], 400);
            }

            $document->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve document',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Reject document
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $document = Document::findOrFail($id);
            $user = $request->user();

            if ($document->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending documents can be rejected'
                ], 400);
            }

            $document->update([
                'status' => 'rejected',
                'rejection_reason' => $request->reason,
                'rejected_at' => now(),
                'rejected_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject document',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Delete document
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $document = Document::findOrFail($id);

            // Delete file from storage
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Format file size to human readable
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

