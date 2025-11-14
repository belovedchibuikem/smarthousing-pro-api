<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PropertyInterest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenant\WhiteLabelSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;

class EoiFormController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $query = PropertyInterest::with(['member.user', 'property']);
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->whereHas('member.user', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('property', function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%");
                });
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $eoiForms = $query->latest()->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $eoiForms->items(),
                'pagination' => [
                    'current_page' => $eoiForms->currentPage(),
                    'last_page' => $eoiForms->lastPage(),
                    'per_page' => $eoiForms->perPage(),
                    'total' => $eoiForms->total(),
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Unable to load Expression of Interest forms at this time.', ['error' => $exception->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $eoiForm = PropertyInterest::with(['member.user', 'property', 'approver', 'rejecter'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => array_merge($eoiForm->toArray(), [
                'signature_url' => $this->resolveImageSource($eoiForm->signature_path),
            ])
        ]);
    }

    public function download(Request $request, String $id): Response | JsonResponse
{
    try {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $eoiForm = PropertyInterest::with(['member.user', 'property'])->findOrFail($id);

        // Optional: Check if user has permission to download this form
        // if ($user->id !== $eoiForm->member->user_id && !$user->hasRole(['admin', 'super_admin'])) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $whiteLabel = WhiteLabelSetting::first();

        // Render the view
        $html = view('pdf.eoi-form', [
            'form' => $eoiForm,
            'logoSrc' => $this->resolveImageSource($whiteLabel?->logo_url),
            'whiteLabel' => $whiteLabel,
            'signatureSrc' => $this->resolveImageSource($eoiForm->signature_path),
        ])->render();

        // Create PDF with correct facade usage
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        
        // Set options
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'dpi' => 150,
            'defaultFont' => 'sans-serif',
            'chroot' => public_path(),
        ]);

        $filename = sprintf('eoi-form-%s.pdf', Str::lower($eoiForm->id));

        // Use stream instead of download for better compatibility
        return $pdf->download($filename);
        
    } catch (\Exception $e) {
        Log::error('PDF Download Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Failed to generate PDF',
            'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
        ], 500);
    }
}

    public function approve(Request $request, String $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $interest = PropertyInterest::with(['member.user', 'property'])->findOrFail($id);

        if ($interest->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'EOI has already been approved.',
            ], 422);
        }

        if ($interest->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Rejected EOI cannot be approved. Please request a new submission.',
            ], 422);
        }

        $interest->status = 'approved';
        $interest->approved_at = Carbon::now();
        $interest->approved_by = $user->id;
        $interest->rejection_reason = null;
        $interest->save();

        return response()->json([
            'success' => true,
            'message' => 'EOI form approved successfully.',
            'data' => $interest->fresh(),
        ]);
    }

    public function reject(Request $request, String $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $interest = PropertyInterest::with(['member.user', 'property'])->findOrFail($id);

        if ($interest->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'EOI has already been rejected.',
            ], 422);
        }

        if ($interest->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Approved EOI cannot be rejected. Please withdraw instead.',
            ], 422);
        }

        $interest->status = 'rejected';
        $interest->rejection_reason = $validated['reason'];
        $interest->rejected_at = Carbon::now();
        $interest->rejected_by = $user->id;
        $interest->save();

        return response()->json([
            'success' => true,
            'message' => 'EOI form rejected successfully.',
            'data' => $interest->fresh(),
        ]);
    }

    

    private function resolveImageSource(?string $path): ?string
{
    if (!$path) {
        return null;
    }

    // If already a data URI, return it
    if (Str::startsWith($path, 'data:')) {
        return $path;
    }

    // Check if URL is from the same application (local storage)
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        $appUrl = config('app.url');
        
        // If it's a local storage URL, convert to file path
        if (Str::startsWith($path, $appUrl) || 
            Str::startsWith($path, 'http://127.0.0.1') || 
            Str::startsWith($path, 'http://localhost')) {
            
            // Extract the storage path from URL
            // e.g., http://127.0.0.1:8000/storage/white-label/logos/file.jpg
            // becomes white-label/logos/file.jpg
            if (preg_match('/\/storage\/(.+)$/', $path, $matches)) {
                $path = $matches[1]; // Get everything after /storage/
                $fullPath = storage_path('app/public/' . $path);
                
                if (!file_exists($fullPath)) {
                    Log::warning('Local image not found: ' . $fullPath);
                    return null;
                }
                
                try {
                    $imageData = file_get_contents($fullPath);
                    $mimeType = mime_content_type($fullPath);
                    return "data:{$mimeType};base64," . base64_encode($imageData);
                } catch (\Exception $e) {
                    Log::error('Local image conversion error: ' . $e->getMessage());
                    return null;
                }
            }
        }
        
        // For external URLs only
        try {
            $response = Http::timeout(5)
                ->retry(2, 100)
                ->get($path);

            if (!$response->successful()) {
                Log::warning('Failed to fetch external URL image: ' . $path);
                return null;
            }

            $imageData = $response->body();
            $contentType = $response->header('Content-Type');
            
            if (!$contentType) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $contentType = $finfo->buffer($imageData);
            }

            return "data:{$contentType};base64," . base64_encode($imageData);
        } catch (\Exception $e) {
            Log::error('Failed to fetch external URL image: ' . $e->getMessage());
            return null;
        }
    }

    // Handle storage path
    if (Str::startsWith($path, 'storage/')) {
        $path = Str::after($path, 'storage/');
    }

    $fullPath = storage_path('app/public/' . $path);

    if (!file_exists($fullPath)) {
        Log::warning('Image not found: ' . $fullPath);
        return null;
    }

    try {
        $imageData = file_get_contents($fullPath);
        $mimeType = mime_content_type($fullPath);
        return "data:{$mimeType};base64," . base64_encode($imageData);
    } catch (\Exception $e) {
        Log::error('Image conversion error: ' . $e->getMessage());
        return null;
    }
}

}

