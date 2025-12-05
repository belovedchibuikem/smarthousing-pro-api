<?php

namespace App\Http\Controllers\Api\Properties;

use App\Http\Controllers\Controller;
use App\Http\Requests\Properties\StorePropertyDocumentRequest;
use App\Models\Tenant\Property;
use App\Models\Tenant\Member;
use App\Models\Tenant\PropertyDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PropertyDocumentController extends Controller
{
    public function index(Request $request, string $propertyId): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $property = Property::findOrFail($propertyId);

            $query = PropertyDocument::with(['uploader', 'member.user'])
                ->where('property_id', $property->id);

            // Filter by document type if provided
            if ($request->has('document_type') && !empty($request->document_type)) {
                $query->where('document_type', $request->document_type);
            }

            // Filter by member if provided (admin only)
            if ($request->has('member_id') && !empty($request->member_id) && $user->isAdmin()) {
                $query->where('member_id', $request->member_id);
            }

            // Access control: bidirectional visibility
            // - Admins see all documents (including those uploaded by members)
            // - Members see documents uploaded by admins AND their own documents
            if (!$user->isAdmin()) {
                $member = $user->member;
                if (!$member) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Member profile not found',
                    ], 404);
                }

                // Members can see:
                // 1. Documents uploaded by admins (uploaded_by_role = 'admin')
                // 2. Documents they uploaded themselves (uploaded_by matches their user id)
                // 3. Documents assigned to them (member_id matches their member id)
                // 4. Public documents (member_id is null)
                $query->where(function ($builder) use ($member, $user) {
                    $builder->where('uploaded_by_role', 'admin') // Documents uploaded by admins
                        ->orWhere('uploaded_by', $user->id) // Documents they uploaded
                        ->orWhere('member_id', $member->id) // Documents assigned to them
                        ->orWhereNull('member_id'); // Public documents (no specific member assignment)
                });
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($builder) use ($search) {
                    $builder->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%")
                        ->orWhere('document_type', 'like', "%{$search}%");
                });
            }

            $query->orderByDesc('created_at');

            $documents = $query->paginate($request->get('per_page', 20));
            $documents->getCollection()->transform(function (PropertyDocument $document) {
                return $this->transformDocument($document);
            });

            return response()->json([
                'success' => true,
                'data' => $documents->items(),
                'pagination' => [
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found',
            ], 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PropertyDocumentController index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch documents',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StorePropertyDocumentRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $property = Property::findOrFail($data['property_id']);

        $member = null;
        if (!empty($data['member_id'])) {
            $member = Member::findOrFail($data['member_id']);
        } elseif (!$user->isAdmin()) {
            $member = $user->member;
        }

        $file = $request->file('file');

        $path = $file->storePubliclyAs(
            'property-documents/' . $property->id,
            Str::uuid() . '_' . $file->getClientOriginalName(),
            ['disk' => 'public']
        );

        $document = PropertyDocument::create([
            'property_id' => $property->id,
            'member_id' => $member?->id,
            'uploaded_by' => $user->id,
            'uploaded_by_role' => $user->isAdmin() ? 'admin' : 'member',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'document_type' => $data['document_type'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'metadata' => [
                'uploaded_from' => $request->server('HTTP_USER_AGENT'),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'data' => $this->transformDocument($document->load(['uploader', 'member.user'])),
        ], 201);
    }

    /**
     * Show a single property document
     */
    public function show(Request $request, PropertyDocument $document): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // Check access permissions
            if (!$user->isAdmin()) {
                $member = $user->member;
                if (!$member) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Member profile not found',
                    ], 404);
                }

                // Members can view:
                // 1. Documents uploaded by admins (uploaded_by_role = 'admin')
                // 2. Documents they uploaded themselves
                // 3. Documents assigned to them
                // 4. Public documents (member_id is null)
                $canView = $document->uploaded_by_role === 'admin'
                    || $document->uploaded_by === $user->id
                    || $document->member_id === $member->id
                    || $document->member_id === null;

                if (!$canView) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to view this document.',
                    ], 403);
                }
            }

            $document->load(['uploader', 'member.user', 'property']);

            return response()->json([
                'success' => true,
                'data' => $this->transformDocument($document),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PropertyDocumentController show error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a property document (metadata only, not the file)
     */
    public function update(Request $request, PropertyDocument $document): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // Check authorization
            if (!$user->isAdmin() && $document->uploaded_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update this document.',
                ], 403);
            }

            // Validate update data
            $validated = $request->validate([
                'title' => ['sometimes', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:1000'],
                'document_type' => ['nullable', 'string', 'max:100'],
            ]);

            $document->update($validated);
            $document->load(['uploader', 'member.user']);

            return response()->json([
                'success' => true,
                'message' => 'Document updated successfully.',
                'data' => $this->transformDocument($document),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PropertyDocumentController update error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download/view a property document
     */
    public function download(Request $request, PropertyDocument $document): BinaryFileResponse|JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // Check access permissions
            if (!$user->isAdmin()) {
                $member = $user->member;
                if (!$member) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Member profile not found',
                    ], 404);
                }

                // Members can download:
                // 1. Documents uploaded by admins (uploaded_by_role = 'admin')
                // 2. Documents they uploaded themselves
                // 3. Documents assigned to them
                // 4. Public documents (member_id is null)
                $canDownload = $document->uploaded_by_role === 'admin'
                    || $document->uploaded_by === $user->id
                    || $document->member_id === $member->id
                    || $document->member_id === null;

                if (!$canDownload) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to download this document.',
                    ], 403);
                }
            }

            // Check if file exists
            if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document file not found.',
                ], 404);
            }

            // Return file download response
            $filePath = Storage::disk('public')->path($document->file_path);
            
            return response()->download(
                $filePath,
                $document->file_name ?? 'document',
                [
                    'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                ]
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PropertyDocumentController download error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, PropertyDocument $document): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // Check authorization
            if (!$user->isAdmin() && $document->uploaded_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to delete this document.',
                ], 403);
            }

            // Delete file from storage
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            // Delete document record
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully.',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PropertyDocumentController destroy error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    protected function transformDocument(PropertyDocument $document): array
    {
        return [
            'id' => $document->id,
            'property_id' => $document->property_id,
            'member_id' => $document->member_id,
            'uploaded_by' => $document->uploaded_by,
            'uploaded_by_role' => $document->uploaded_by_role,
            'title' => $document->title,
            'description' => $document->description,
            'document_type' => $document->document_type,
            'file_name' => $document->file_name,
            'file_size' => $document->file_size,
            'mime_type' => $document->mime_type,
            'file_path' => $document->file_path,
            'file_url' => $document->file_path ? asset('storage/' . $document->file_path) : null,
            'metadata' => $document->metadata,
            'created_at' => optional($document->created_at)->toDateTimeString(),
            'updated_at' => optional($document->updated_at)->toDateTimeString(),
            'uploader' => $document->uploader ? [
                'id' => $document->uploader->id,
                'first_name' => $document->uploader->first_name ?? null,
                'last_name' => $document->uploader->last_name ?? null,
                'email' => $document->uploader->email ?? null,
            ] : null,
            'member' => $document->member ? [
                'id' => $document->member->id,
                'member_number' => $document->member->member_number ?? null,
                'user' => $document->member->user ? [
                    'first_name' => $document->member->user->first_name ?? null,
                    'last_name' => $document->member->user->last_name ?? null,
                    'email' => $document->member->user->email ?? null,
                ] : null,
            ] : null,
        ];
    }
}




