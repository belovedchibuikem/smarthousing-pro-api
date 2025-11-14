<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LandingPageRequest;
use App\Http\Resources\Admin\LandingPageResource;
use App\Models\Tenant\LandingPageConfig;
use App\Models\Tenant\LoanProduct;
use App\Models\Tenant\InvestmentPlan;
use App\Models\Tenant\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LandingPageController extends Controller
{
    public function index(): JsonResponse
    {
        $page = LandingPageConfig::where('tenant_id', tenant('id'))->first();

        if (!$page) {
            // Create default landing page configuration
            $page = LandingPageConfig::create([
                'tenant_id' => tenant('id'),
                'template_id' => 'default',
                'is_published' => false,
                'sections' => $this->getDefaultSections(),
                'theme' => $this->getDefaultTheme(),
                'seo' => $this->getDefaultSeo(),
            ]);
        }

        return response()->json([
            'page' => new LandingPageResource($page)
        ]);
    }

    public function store(LandingPageRequest $request): JsonResponse
    {
        try {
            $page = LandingPageConfig::updateOrCreate(
                ['tenant_id' => tenant('id')],
                [
                    'template_id' => $request->template_id ?? 'default',
                    'sections' => $request->sections,
                    'theme' => $request->theme,
                    'seo' => $request->seo,
                ]
            );

            // Refresh the model to get the latest data
            $page->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Landing page saved successfully',
                'page' => new LandingPageResource($page)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error saving landing page', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => tenant('id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save landing page',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while saving'
            ], 500);
        }
    }

    public function publish(Request $request): JsonResponse
    {
        $request->validate([
            'is_published' => 'required|boolean'
        ]);

        $page = LandingPageConfig::where('tenant_id', tenant('id'))->first();
        
        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Landing page not found'
            ], 404);
        }

        $page->update(['is_published' => $request->is_published]);

        return response()->json([
            'success' => true,
            'message' => $request->is_published ? 'Landing page published' : 'Landing page unpublished'
        ]);
    }

    /**
     * Get available items for selection in page builder
     */
    public function availableItems(): JsonResponse
    {
        try {
            $loanProducts = LoanProduct::where('is_active', true)
                ->select('id', 'name', 'description')
                ->orderBy('name')
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                    ];
                });

            $investmentPlans = InvestmentPlan::where('is_active', true)
                ->select('id', 'name', 'description')
                ->orderBy('name')
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'description' => $plan->description,
                    ];
                });

            // Get properties - only select columns that exist
            $properties = Property::where('status', 'available')
                ->select('id', 'title', 'type', 'location', 'price', 'description')
                ->orderBy('title')
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'name' => $property->title,
                        'type' => $property->type,
                        'location' => $property->location ?? 'Location not specified',
                        'price' => $property->price,
                    ];
                });

            return response()->json([
                'loans' => $loanProducts,
                'investments' => $investmentPlans,
                'properties' => $properties,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching available items for landing page builder', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => tenant('id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available items',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while fetching available items',
                'loans' => [],
                'investments' => [],
                'properties' => [],
            ], 500);
        }
    }

    private function getDefaultSections(): array
    {
        return [
            [
                'id' => 'hero-1',
                'type' => 'hero',
                'name' => 'Hero Section',
                'visible' => true,
                'position' => 0,
                'config' => [
                    'title' => 'Your Path to Homeownership Made Simple',
                    'subtitle' => 'Join the FRSC Housing Cooperative and start your journey to owning your dream home.',
                    'cta_text' => 'Become a Member',
                    'cta_link' => '/register',
                    'show_stats' => true
                ]
            ],
            [
                'id' => 'properties-1',
                'type' => 'properties',
                'name' => 'Properties',
                'visible' => true,
                'position' => 1,
                'config' => [
                    'title' => 'Available Properties',
                    'subtitle' => 'Explore our curated selection of properties',
                    'limit' => 6
                ]
            ],
            [
                'id' => 'investments-1',
                'type' => 'investments',
                'name' => 'Investment Opportunities',
                'visible' => true,
                'position' => 2,
                'config' => [
                    'title' => 'Investment Opportunities',
                    'subtitle' => 'Grow your wealth with our investment plans',
                    'limit' => 6
                ]
            ],
            [
                'id' => 'loans-1',
                'type' => 'loans',
                'name' => 'Loan Products',
                'visible' => true,
                'position' => 3,
                'config' => [
                    'title' => 'Loan Products',
                    'subtitle' => 'Flexible loan options for all your needs',
                    'limit' => 6
                ]
            ],
            [
                'id' => 'features-1',
                'type' => 'features',
                'name' => 'Features',
                'visible' => true,
                'position' => 4,
                'config' => [
                    'title' => 'Everything You Need',
                    'subtitle' => 'Comprehensive tools to manage your housing cooperative membership',
                    'features' => [
                        ['icon' => 'Users', 'title' => 'Member Management', 'description' => 'Complete KYC verification and member profiles'],
                        ['icon' => 'Wallet', 'title' => 'Contribution Tracking', 'description' => 'Automated payment collection and history'],
                        ['icon' => 'TrendingUp', 'title' => 'Loan Management', 'description' => 'Flexible loan products and repayment tracking'],
                        ['icon' => 'Building2', 'title' => 'Property Management', 'description' => 'Property listings and allotment tracking'],
                    ]
                ]
            ],
            [
                'id' => 'how-it-works-1',
                'type' => 'how-it-works',
                'name' => 'How It Works',
                'visible' => true,
                'position' => 5,
                'config' => [
                    'title' => 'How It Works',
                    'subtitle' => 'Simple steps to get started',
                    'steps' => [
                        ['step' => 1, 'title' => 'Register', 'description' => 'Create your account and complete KYC'],
                        ['step' => 2, 'title' => 'Contribute', 'description' => 'Start making regular contributions'],
                        ['step' => 3, 'title' => 'Apply', 'description' => 'Apply for loans or investments'],
                        ['step' => 4, 'title' => 'Benefit', 'description' => 'Enjoy housing and financial benefits'],
                    ]
                ]
            ],
            [
                'id' => 'stats-1',
                'type' => 'stats',
                'name' => 'Statistics',
                'visible' => true,
                'position' => 6,
                'config' => [
                    'title' => 'Our Impact',
                    'subtitle' => 'Numbers that matter',
                    'stats' => [
                        ['label' => 'Active Members', 'value' => '0', 'icon' => 'Users'],
                        ['label' => 'Properties Available', 'value' => '0', 'icon' => 'Building2'],
                        ['label' => 'Total Loans Disbursed', 'value' => '₦0', 'icon' => 'TrendingUp'],
                        ['label' => 'Member Satisfaction', 'value' => '98%', 'icon' => 'Shield'],
                    ]
                ]
            ],
            [
                'id' => 'cta-1',
                'type' => 'cta',
                'name' => 'Call to Action',
                'visible' => true,
                'position' => 7,
                'config' => [
                    'title' => 'Ready to Start Your Homeownership Journey?',
                    'description' => 'Join thousands of members who are building their future',
                    'cta_text' => 'Register Now',
                    'cta_link' => '/register',
                ]
            ],
        ];
    }

    private function getDefaultTheme(): array
    {
        return [
            'primary_color' => '#FDB11E',
            'secondary_color' => '#276254',
            'accent_color' => '#10b981',
            'font_family' => 'Inter'
        ];
    }

    private function getDefaultSeo(): array
    {
        return [
            'title' => 'FRSC Housing Management System',
            'description' => 'Your trusted partner in housing solutions',
            'keywords' => 'housing, cooperative, FRSC, properties'
        ];
    }
}
