<?php

namespace App\Http\Controllers\Api\Properties;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Property;
use App\Models\Tenant\PropertyAllocation;
use App\Models\Tenant\PropertyMaintenanceRecord;
use App\Models\Tenant\Member;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MemberPropertyManagementController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get member's estates (properties grouped by location)
     */
    public function getMyEstates(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found'
            ], 404);
        }

        $memberId = $user->member->id;

        // Get properties allocated to this member
        $allocatedPropertyIds = PropertyAllocation::where('member_id', $memberId)
            ->where('status', 'active')
            ->pluck('property_id');

        // Group properties by location/estate
        $estates = Property::whereIn('id', $allocatedPropertyIds)
            ->select(
                'location',
                DB::raw('COALESCE(city, "") as city'),
                DB::raw('COALESCE(state, "") as state'),
                DB::raw('COUNT(*) as total_properties'),
                DB::raw('COUNT(CASE WHEN status = "allocated" OR status = "sold" THEN 1 END) as my_properties')
            )
            ->whereNotNull('location')
            ->groupBy('location', 'city', 'state')
            ->orderBy('location')
            ->get()
            ->map(function($item) use ($memberId) {
                $city = $item->city ?? '';
                $state = $item->state ?? '';
                $location = $item->location ?? 'Unnamed Estate';
                
                // Get total units in this estate (all properties at this location)
                $totalUnits = Property::where('location', $item->location)
                    ->where(function($q) use ($item) {
                        $q->where('city', $item->city ?? null)
                          ->where('state', $item->state ?? null);
                    })
                    ->count();

                // Get occupied units
                $occupiedUnits = PropertyAllocation::whereHas('property', function($q) use ($item) {
                    $q->where('location', $item->location)
                      ->where(function($q2) use ($item) {
                          $q2->where('city', $item->city ?? null)
                             ->where('state', $item->state ?? null);
                      });
                })
                ->where('status', 'active')
                ->count();

                return [
                    'id' => md5($location . $city . $state),
                    'name' => $location,
                    'location' => trim($city . ', ' . $state, ', ') ?: 'Unknown',
                    'total_units' => $totalUnits,
                    'occupied_units' => $occupiedUnits,
                    'my_properties' => (int) ($item->my_properties ?? 0),
                    'status' => 'active',
                    'description' => "Properties located at {$location}",
                ];
            });

        return response()->json([
            'success' => true,
            'estates' => $estates
        ]);
    }

    /**
     * Get member's allottee status (property allocations)
     */
    public function getAllotteeStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found'
            ], 404);
        }

        $memberId = $user->member->id;

        // Get all allocations for this member
        $allocations = PropertyAllocation::where('member_id', $memberId)
            ->with(['property.images'])
            ->orderBy('allocation_date', 'desc')
            ->get();

        // Determine overall status
        $hasApproved = $allocations->where('status', 'active')->count() > 0;
        $status = $hasApproved ? 'approved' : ($allocations->count() > 0 ? 'pending' : 'none');

        // Get first allocation date
        $firstAllocation = $allocations->sortBy('allocation_date')->first();
        $allotteeId = 'ALLOT-' . date('Y') . '-' . str_pad($memberId, 3, '0', STR_PAD_LEFT);

        $properties = $allocations->map(function($allocation) {
            return [
                'id' => $allocation->id,
                'property_id' => $allocation->property_id,
                'type' => ucfirst($allocation->property->type ?? 'Property'),
                'estate' => $allocation->property->location ?? 'Unknown',
                'unit' => $allocation->property->title ?? 'N/A',
                'allocation_date' => $allocation->allocation_date?->toDateString(),
                'status' => $allocation->status === 'active' ? 'allocated' : 'pending_documentation',
            ];
        });

        return response()->json([
            'success' => true,
            'allottee_info' => [
                'status' => $status,
                'allottee_id' => $allotteeId,
                'date_allocated' => $firstAllocation?->allocation_date?->toDateString(),
                'properties' => $properties,
            ]
        ]);
    }

    /**
     * Get member's maintenance requests
     */
    public function getMyMaintenanceRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found'
            ], 404);
        }

        $memberId = $user->member->id;

        $query = PropertyMaintenanceRecord::where('reported_by', $memberId)
            ->with(['property', 'assignee']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('issue_type', 'like', "%{$search}%")
                  ->orWhereHas('property', function($q2) use ($search) {
                      $q2->where('title', 'like', "%{$search}%")
                         ->orWhere('location', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $requests = $query->orderBy('reported_date', 'desc')
            ->orderBy('priority', 'desc')
            ->get()
            ->map(function($record) {
                return [
                    'id' => $record->id,
                    'request_id' => 'MNT-' . str_pad(substr($record->id, 0, 3), 3, '0', STR_PAD_LEFT),
                    'title' => $record->issue_type ?? 'Maintenance Request',
                    'description' => $record->description,
                    'property' => $record->property->title ?? 'Unknown',
                    'estate' => $record->property->location ?? 'Unknown',
                    'status' => $record->status,
                    'priority' => $record->priority,
                    'category' => $record->issue_type,
                    'date_submitted' => $record->reported_date?->toDateString(),
                    'date_assigned' => $record->started_date?->toDateString(),
                    'estimated_completion' => $record->completed_date?->toDateString(),
                    'assigned_to' => $record->assignee ? 
                        ($record->assignee->first_name ?? '') . ' ' . ($record->assignee->last_name ?? '') : 
                        null,
                ];
            });

        return response()->json([
            'success' => true,
            'requests' => $requests
        ]);
    }

    /**
     * Get single maintenance request
     */
    public function getMaintenanceRequest(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found'
            ], 404);
        }

        $memberId = $user->member->id;

        $record = PropertyMaintenanceRecord::where('id', $id)
            ->where('reported_by', $memberId)
            ->with(['property', 'assignee', 'reporter.user'])
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Maintenance request not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'request' => [
                'id' => $record->id,
                'request_id' => 'MNT-' . str_pad(substr($record->id, 0, 3), 3, '0', STR_PAD_LEFT),
                'title' => $record->issue_type ?? 'Maintenance Request',
                'description' => $record->description,
                'property' => $record->property->title ?? 'Unknown',
                'estate' => $record->property->location ?? 'Unknown',
                'status' => $record->status,
                'priority' => $record->priority,
                'category' => $record->issue_type,
                'date_submitted' => $record->reported_date?->toDateString(),
                'date_assigned' => $record->started_date?->toDateString(),
                'estimated_completion' => $record->completed_date?->toDateString(),
                'assigned_to' => $record->assignee ? 
                    ($record->assignee->first_name ?? '') . ' ' . ($record->assignee->last_name ?? '') : 
                    null,
                'resolution_notes' => $record->resolution_notes,
                'estimated_cost' => $record->estimated_cost,
                'actual_cost' => $record->actual_cost,
            ]
        ]);
    }

    /**
     * Create maintenance request
     */
    public function createMaintenanceRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found'
            ], 404);
        }

        $memberId = $user->member->id;

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|uuid|exists:properties,id',
            'issue_type' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high,urgent',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify property belongs to member
        $property = Property::find($request->property_id);
        $hasAccess = PropertyAllocation::where('property_id', $request->property_id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Property does not belong to you'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $record = PropertyMaintenanceRecord::create([
                'property_id' => $request->property_id,
                'reported_by' => $memberId,
                'issue_type' => $request->issue_type,
                'priority' => $request->priority,
                'description' => $request->description,
                'status' => 'pending',
                'reported_date' => now(),
            ]);

            // Handle file uploads if any
            $attachmentUrls = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('maintenance-attachments', 'public');
                    $attachmentUrls[] = Storage::url($path);
                }
            }

            DB::commit();

            $record->load(['property', 'reporter.user']);

            // Notify admins about new maintenance request
            if ($record->reporter && $record->reporter->user) {
                $memberName = trim($record->reporter->first_name . ' ' . $record->reporter->last_name);
                $propertyTitle = $record->property->title ?? 'property';
                
                $this->notificationService->notifyAdmins(
                    'info',
                    'New Maintenance Request',
                    "A new {$record->priority} priority maintenance request for {$propertyTitle} has been submitted by {$memberName}",
                    [
                        'maintenance_id' => $record->id,
                        'property_id' => $record->property_id,
                        'property_title' => $propertyTitle,
                        'member_id' => $record->reported_by,
                        'member_name' => $memberName,
                        'issue_type' => $record->issue_type,
                        'priority' => $record->priority,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Maintenance request created successfully',
                'request' => [
                    'id' => $record->id,
                    'request_id' => 'MNT-' . str_pad(substr($record->id, 0, 3), 3, '0', STR_PAD_LEFT),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create maintenance request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member's properties for dropdown
     */
    public function getMyProperties(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found'
            ], 404);
        }

        $memberId = $user->member->id;

        $properties = PropertyAllocation::where('member_id', $memberId)
            ->where('status', 'active')
            ->with(['property'])
            ->get()
            ->map(function($allocation) {
                return [
                    'id' => $allocation->property_id,
                    'title' => $allocation->property->title ?? 'Unknown',
                    'location' => $allocation->property->location ?? 'Unknown',
                    'type' => $allocation->property->type ?? 'property',
                ];
            });

        return response()->json([
            'success' => true,
            'properties' => $properties
        ]);
    }
}

