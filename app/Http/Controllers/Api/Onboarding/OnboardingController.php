<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\OnboardingRequest;
use App\Http\Resources\Onboarding\OnboardingResource;
use App\Models\Tenant\OnboardingStep;
use App\Models\Tenant\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $steps = OnboardingStep::where('member_id', $member->id)
            ->orderBy('step_number')
            ->get();

        return response()->json([
            'steps' => OnboardingResource::collection($steps),
            'completion_percentage' => $this->calculateCompletionPercentage($steps),
            'is_complete' => $this->isOnboardingComplete($steps),
        ]);
    }

    public function updateStep(OnboardingRequest $request, OnboardingStep $step): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member || $step->member_id !== $member->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $step->update([
            'status' => $request->status,
            'completed_at' => $request->status === 'completed' ? now() : null,
            'data' => $request->data,
        ]);

        // Check if all steps are completed
        $allSteps = OnboardingStep::where('member_id', $member->id)->get();
        if ($this->isOnboardingComplete($allSteps)) {
            $member->update([
                'onboarding_completed_at' => now(),
                'status' => 'active',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Step updated successfully',
            'step' => new OnboardingResource($step),
            'completion_percentage' => $this->calculateCompletionPercentage($allSteps),
            'is_complete' => $this->isOnboardingComplete($allSteps),
        ]);
    }

    public function completeStep(Request $request, OnboardingStep $step): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member || $step->member_id !== $member->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $step->update([
            'status' => 'completed',
            'completed_at' => now(),
            'data' => $request->get('data', []),
        ]);

        // Check if all steps are completed
        $allSteps = OnboardingStep::where('member_id', $member->id)->get();
        if ($this->isOnboardingComplete($allSteps)) {
            $member->update([
                'onboarding_completed_at' => now(),
                'status' => 'active',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Step completed successfully',
            'step' => new OnboardingResource($step),
            'completion_percentage' => $this->calculateCompletionPercentage($allSteps),
            'is_complete' => $this->isOnboardingComplete($allSteps),
        ]);
    }

    public function skipStep(Request $request, OnboardingStep $step): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member || $step->member_id !== $member->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $step->update([
            'status' => 'skipped',
            'skipped_at' => now(),
            'skip_reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Step skipped successfully',
            'step' => new OnboardingResource($step),
        ]);
    }

    public function reset(): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        // Reset all steps
        OnboardingStep::where('member_id', $member->id)->update([
            'status' => 'pending',
            'completed_at' => null,
            'skipped_at' => null,
            'skip_reason' => null,
            'data' => null,
        ]);

        $member->update([
            'onboarding_completed_at' => null,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding reset successfully'
        ]);
    }

    public function getNextStep(): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $nextStep = OnboardingStep::where('member_id', $member->id)
            ->where('status', 'pending')
            ->orderBy('step_number')
            ->first();

        if (!$nextStep) {
            return response()->json([
                'message' => 'No pending steps found',
                'next_step' => null
            ]);
        }

        return response()->json([
            'next_step' => new OnboardingResource($nextStep)
        ]);
    }

    private function calculateCompletionPercentage($steps): int
    {
        if ($steps->isEmpty()) {
            return 0;
        }

        $completedSteps = $steps->where('status', 'completed')->count();
        $totalSteps = $steps->count();

        return round(($completedSteps / $totalSteps) * 100);
    }

    private function isOnboardingComplete($steps): bool
    {
        return $steps->where('status', 'completed')->count() === $steps->count();
    }
}
