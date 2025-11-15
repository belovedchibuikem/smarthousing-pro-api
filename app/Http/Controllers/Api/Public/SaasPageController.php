<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Central\SaasPageSection;
use App\Models\Central\SaasCommunityDiscussion;
use App\Models\Central\SaasTestimonial;
use App\Models\Central\SaasTeamMember;
use App\Models\Central\SaasMilestone;
use App\Models\Central\SaasValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SaasPageController extends Controller
{
    /**
     * Get published content for a specific page type
     */
    public function getPage(string $pageType): JsonResponse
    {
        try {
            $sections = SaasPageSection::forPage($pageType)
                ->published()
                ->ordered()
                ->get()
                ->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'section_type' => $section->section_type,
                        'section_key' => $section->section_key,
                        'title' => $section->title,
                        'subtitle' => $section->subtitle,
                        'content' => $section->content,
                        'media' => $section->media,
                        'metadata' => $section->metadata,
                    ];
                });

            return response()->json([
                'success' => true,
                'page_type' => $pageType,
                'sections' => $sections,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch public page content', [
                'page_type' => $pageType,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch page content',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get header/navigation configuration
     */
    public function getHeader(): JsonResponse
    {
        try {
            $headerSection = SaasPageSection::forPage('header')
                ->published()
                ->first();

            return response()->json([
                'success' => true,
                'header' => $headerSection ? [
                    'logo_url' => $headerSection->content['logo_url'] ?? null,
                    'navigation_links' => $headerSection->content['navigation_links'] ?? [],
                    'cta_button_text' => $headerSection->content['cta_button_text'] ?? 'Start Free Trial',
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch header config', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch header',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get published community discussions
     */
    public function getDiscussions(): JsonResponse
    {
        try {
            $discussions = SaasCommunityDiscussion::active()
                ->ordered()
                ->get()
                ->map(function ($discussion) {
                    return [
                        'id' => $discussion->id,
                        'question' => $discussion->question,
                        'author' => [
                            'name' => $discussion->author_name,
                            'role' => $discussion->author_role,
                            'avatar' => $discussion->author_avatar_url,
                        ],
                        'responses' => $discussion->responses_count,
                        'likes' => $discussion->likes_count,
                        'views' => $discussion->views_count,
                        'tags' => $discussion->tags,
                        'top_answer' => $discussion->top_answer,
                        'other_answers' => $discussion->other_answers,
                    ];
                });

            return response()->json([
                'success' => true,
                'discussions' => $discussions,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch public discussions', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch discussions',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get published testimonials
     */
    public function getTestimonials(): JsonResponse
    {
        try {
            $testimonials = SaasTestimonial::active()
                ->ordered()
                ->get()
                ->map(function ($testimonial) {
                    return [
                        'id' => $testimonial->id,
                        'name' => $testimonial->name,
                        'role' => $testimonial->role,
                        'content' => $testimonial->content,
                        'rating' => $testimonial->rating,
                        'avatar_url' => $testimonial->avatar_url,
                        'company' => $testimonial->company,
                    ];
                });

            return response()->json([
                'success' => true,
                'testimonials' => $testimonials,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch public testimonials', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch testimonials',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get team members
     */
    public function getTeam(): JsonResponse
    {
        try {
            $members = SaasTeamMember::active()
                ->ordered()
                ->get()
                ->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'role' => $member->role,
                        'bio' => $member->bio,
                        'avatar_url' => $member->avatar_url,
                        'email' => $member->email,
                        'linkedin_url' => $member->linkedin_url,
                        'twitter_url' => $member->twitter_url,
                    ];
                });

            return response()->json([
                'success' => true,
                'team_members' => $members,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch public team members', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch team members',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get milestones
     */
    public function getMilestones(): JsonResponse
    {
        try {
            $milestones = SaasMilestone::active()
                ->ordered()
                ->get()
                ->map(function ($milestone) {
                    return [
                        'id' => $milestone->id,
                        'year' => $milestone->year,
                        'event' => $milestone->event,
                        'icon' => $milestone->icon,
                    ];
                });

            return response()->json([
                'success' => true,
                'milestones' => $milestones,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch public milestones', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch milestones',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get values
     */
    public function getValues(): JsonResponse
    {
        try {
            $values = SaasValue::active()
                ->ordered()
                ->get()
                ->map(function ($value) {
                    return [
                        'id' => $value->id,
                        'title' => $value->title,
                        'description' => $value->description,
                        'icon' => $value->icon,
                    ];
                });

            return response()->json([
                'success' => true,
                'values' => $values,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch public values', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch values',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
