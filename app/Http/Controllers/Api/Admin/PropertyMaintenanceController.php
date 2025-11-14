<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PropertyMaintenanceRecord;
use App\Models\Tenant\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PropertyMaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = PropertyMaintenanceRecord::with(['property', 'reporter.user', 'assignee']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('issue_type', 'like', "%{$search}%")
                  ->orWhereHas('property', function($q2) use ($search) {
                      $q2->where('title', 'like', "%{$search}%")
                         ->orWhere('location', 'like', "%{$search}%");
                  })
                  ->orWhereHas('reporter.user', function($q3) use ($search) {
                      $q3->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('maintenance_status') && $request->maintenance_status !== 'all') {
            $query->where('status', $request->maintenance_status);
        }

        $records = $query->orderBy('reported_date', 'desc')
                        ->orderBy('priority', 'desc')
                        ->paginate($request->get('per_page', 15));

        $data = $records->map(function($record) {
            $reporter = $record->reporter->user ?? null;
            return [
                'id' => $record->id,
                'property' => [
                    'id' => $record->property->id,
                    'title' => $record->property->title,
                    'location' => $record->property->location,
                ],
                'reported_by' => $reporter ? [
                    'id' => $reporter->id,
                    'name' => ($reporter->first_name ?? '') . ' ' . ($reporter->last_name ?? ''),
                    'member_id' => $record->reporter->member_id ?? $record->reporter->staff_id ?? '—',
                ] : null,
                'issue_type' => $record->issue_type,
                'priority' => $record->priority,
                'description' => $record->description,
                'status' => $record->status,
                'assigned_to' => $record->assignee ? [
                    'id' => $record->assignee->id,
                    'name' => ($record->assignee->first_name ?? '') . ' ' . ($record->assignee->last_name ?? ''),
                ] : null,
                'estimated_cost' => $record->estimated_cost,
                'actual_cost' => $record->actual_cost,
                'reported_date' => $record->reported_date,
                'started_date' => $record->started_date,
                'completed_date' => $record->completed_date,
                'resolution_notes' => $record->resolution_notes,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|uuid|exists:properties,id',
            'reported_by' => 'nullable|uuid|exists:members,id',
            'issue_type' => 'nullable|string|max:255',
            'priority' => 'sometimes|string|in:low,medium,high,critical',
            'description' => 'required|string',
            'status' => 'sometimes|string|in:pending,in_progress,completed,cancelled',
            'assigned_to' => 'nullable|uuid|exists:users,id',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'reported_date' => 'nullable|date',
            'started_date' => 'nullable|date',
            'completed_date' => 'nullable|date',
            'resolution_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = PropertyMaintenanceRecord::create(array_merge(
                $request->only([
                    'property_id', 'reported_by', 'issue_type', 'priority',
                    'description', 'status', 'assigned_to', 'estimated_cost',
                    'actual_cost', 'reported_date', 'started_date', 'completed_date',
                    'resolution_notes'
                ]),
                [
                    'reported_date' => $request->reported_date ?? now(),
                ]
            ));

            $record->load(['property', 'reporter.user', 'assignee']);

            return response()->json([
                'success' => true,
                'message' => 'Maintenance record created successfully',
                'data' => $record
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error creating maintenance record: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create maintenance record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $record = PropertyMaintenanceRecord::with(['property', 'reporter.user', 'assignee'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Maintenance record not found'
            ], 404);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'sometimes|uuid|exists:properties,id',
            'reported_by' => 'nullable|uuid|exists:members,id',
            'issue_type' => 'nullable|string|max:255',
            'priority' => 'sometimes|string|in:low,medium,high,critical',
            'description' => 'sometimes|string',
            'status' => 'sometimes|string|in:pending,in_progress,completed,cancelled',
            'assigned_to' => 'nullable|uuid|exists:users,id',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'reported_date' => 'nullable|date',
            'started_date' => 'nullable|date',
            'completed_date' => 'nullable|date',
            'resolution_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = PropertyMaintenanceRecord::findOrFail($id);

            // Update started_date when status changes to in_progress
            if ($request->has('status') && $request->status === 'in_progress' && !$record->started_date) {
                $request->merge(['started_date' => now()]);
            }

            // Update completed_date when status changes to completed
            if ($request->has('status') && $request->status === 'completed' && !$record->completed_date) {
                $request->merge(['completed_date' => now()]);
            }

            $record->update($request->only([
                'property_id', 'reported_by', 'issue_type', 'priority',
                'description', 'status', 'assigned_to', 'estimated_cost',
                'actual_cost', 'reported_date', 'started_date', 'completed_date',
                'resolution_notes'
            ]));

            $record->load(['property', 'reporter.user', 'assignee']);

            return response()->json([
                'success' => true,
                'message' => 'Maintenance record updated successfully',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating maintenance record: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update maintenance record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $record = PropertyMaintenanceRecord::findOrFail($id);
            $record->delete();

            return response()->json([
                'success' => true,
                'message' => 'Maintenance record deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error deleting maintenance record: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete maintenance record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $stats = [
            'total_records' => PropertyMaintenanceRecord::count(),
            'pending_records' => PropertyMaintenanceRecord::where('status', 'pending')->count(),
            'in_progress_records' => PropertyMaintenanceRecord::where('status', 'in_progress')->count(),
            'completed_records' => PropertyMaintenanceRecord::where('status', 'completed')->count(),
            'cancelled_records' => PropertyMaintenanceRecord::where('status', 'cancelled')->count(),
            'total_estimated_cost' => PropertyMaintenanceRecord::whereIn('status', ['pending', 'in_progress'])->sum('estimated_cost'),
            'total_actual_cost' => PropertyMaintenanceRecord::where('status', 'completed')->sum('actual_cost'),
            'by_priority' => [
                'critical' => PropertyMaintenanceRecord::where('priority', 'critical')->count(),
                'high' => PropertyMaintenanceRecord::where('priority', 'high')->count(),
                'medium' => PropertyMaintenanceRecord::where('priority', 'medium')->count(),
                'low' => PropertyMaintenanceRecord::where('priority', 'low')->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
