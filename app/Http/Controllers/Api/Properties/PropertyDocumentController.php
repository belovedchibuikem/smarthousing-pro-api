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

class PropertyDocumentController extends Controller
{
    public function index(Request $request, string $propertyId): JsonResponse
    {
        $user = $request->user();

        $property = Property::findOrFail($propertyId);

        $query = PropertyDocument::with(['uploader', 'member.user'])
            ->where('property_id', $property->id)
            ->orderByDesc('created_at');

        if ($user->isAdmin()) {
            // admins can see all documents
        } else {
            $member = $user->member;
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member profile not found',
                ], 404);
            }

            $query->where(function ($builder) use ($member) {
                $builder->whereNull('member_id')
                    ->orWhere('member_id', $member->id);
            });
        }

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

    public function destroy(Request $request, PropertyDocument $document): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && $document->uploaded_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this document.',
            ], 403);
        }

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully.',
        ]);
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
            'file_url' => Storage::disk('public')->url($document->file_path),
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




