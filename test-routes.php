<?php

// Temporary test routes without authentication
Route::get('/test-members', function() {
    $members = App\Models\Tenant\Member::with('user')->get();
    return response()->json([
        'members' => App\Http\Resources\Members\MemberResource::collection($members)
    ]);
});

Route::get('/test-members/{id}', function($id) {
    $member = App\Models\Tenant\Member::with(['user', 'wallet', 'loans', 'investments', 'contributions'])->find($id);
    if (!$member) {
        return response()->json(['error' => 'Member not found'], 404);
    }
    return response()->json([
        'member' => new App\Http\Resources\Members\MemberResource($member)
    ]);
});

Route::get('/test-documents', function(Request $request) {
    $query = App\Models\Tenant\Document::with(['member.user']);
    
    if ($request->has('member_id')) {
        $query->where('member_id', $request->member_id);
    }
    
    $documents = $query->paginate(15);
    
    return response()->json([
        'documents' => App\Http\Resources\Documents\DocumentResource::collection($documents),
        'pagination' => [
            'current_page' => $documents->currentPage(),
            'last_page' => $documents->lastPage(),
            'per_page' => $documents->perPage(),
            'total' => $documents->total(),
        ]
    ]);
});






























