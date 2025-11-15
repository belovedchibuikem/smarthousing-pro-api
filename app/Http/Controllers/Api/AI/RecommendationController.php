<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\Investment;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Property;
use App\Models\Tenant\PropertyInterest;
use App\Models\Tenant\PropertyPaymentTransaction;
use App\Models\Tenant\InvestmentPlan;
use App\Models\Tenant\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RecommendationController extends Controller
{
    /**
     * Get AI-powered investment recommendations for authenticated member
     */
    public function index(Request $request): JsonResponse
    {
        // Increase memory limit for this endpoint
        ini_set('memory_limit', '256M');
        set_time_limit(60);

        try {
            $user = $request->user();
            $member = $user->member;

            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            // Fetch comprehensive member financial data
            $financialProfile = $this->getFinancialProfile($member);
            
            // Fetch available investment opportunities
            $availableProperties = $this->getAvailableProperties();
            $availableInvestmentPlans = $this->getAvailableInvestmentPlans();
            
            // Generate AI recommendations
            $recommendations = $this->generateAIRecommendations(
                $financialProfile,
                $availableProperties,
                $availableInvestmentPlans
            );

            // Calculate investment profile metrics
            $investmentProfile = $this->calculateInvestmentProfile($financialProfile);

            return response()->json([
                'success' => true,
                'investment_profile' => $investmentProfile,
                'recommendations' => $recommendations,
                'financial_summary' => [
                    'total_assets' => $financialProfile['total_assets'],
                    'available_capital' => $financialProfile['wallet_balance'],
                    'current_investments' => $financialProfile['total_investments'],
                    'property_equity' => $financialProfile['property_equity'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AI Recommendation error: ' . $e->getMessage(), [
                'member_id' => $member->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback to rule-based recommendations if AI fails
            return response()->json([
                'success' => true,
                'investment_profile' => $this->getFallbackInvestmentProfile($member),
                'recommendations' => $this->getFallbackRecommendations($member),
                'financial_summary' => [],
                'note' => 'Using fallback recommendations due to AI service unavailability',
            ]);
        }
    }

    /**
     * Get comprehensive financial profile for member
     */
    private function getFinancialProfile($member): array
    {
        // Contributions - use aggregate query
        $totalContributions = (float) Contribution::where('member_id', $member->id)
            ->where('status', 'approved')
            ->sum('amount');

        // Recent contribution pattern (last 6 months) - use aggregate query
        $recentContributionsTotal = (float) Contribution::where('member_id', $member->id)
            ->where('status', 'approved')
            ->where('contribution_date', '>=', Carbon::now()->subMonths(6))
            ->sum('amount');
        
        $avgMonthlyContribution = $recentContributionsTotal / 6;

        // Investments - use aggregate queries
        $totalInvestments = (float) Investment::where('member_id', $member->id)
            ->where('status', 'active')
            ->sum('amount');
        
        $investmentCount = Investment::where('member_id', $member->id)
            ->where('status', 'active')
            ->count();
        
        // Get investment IDs efficiently without loading all records
        $investmentIds = Investment::where('member_id', $member->id)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
        
        $investmentReturns = !empty($investmentIds) 
            ? (float) DB::table('investment_returns')
                ->whereIn('investment_id', $investmentIds)
                ->sum('amount')
            : 0;

        // Loans - use aggregate queries
        $totalLoans = (float) Loan::where('member_id', $member->id)
            ->where('status', 'approved')
            ->sum('amount');
        
        $loanCount = Loan::where('member_id', $member->id)
            ->where('status', 'approved')
            ->count();
        
        // Get loan IDs efficiently
        $loanIds = Loan::where('member_id', $member->id)
            ->where('status', 'approved')
            ->pluck('id')
            ->toArray();
        
        $totalRepaid = !empty($loanIds)
            ? (float) DB::table('loan_repayments')
                ->whereIn('loan_id', $loanIds)
                ->where('status', 'paid')
                ->sum('principal_paid')
            : 0;
        
        $loanBalance = $totalLoans - $totalRepaid;

        // Properties - optimize to avoid loading all records
        $propertyCount = PropertyInterest::where('member_id', $member->id)
            ->where('status', 'approved')
            ->count();
        
        // Get property IDs efficiently
        $propertyIds = PropertyInterest::where('member_id', $member->id)
            ->where('status', 'approved')
            ->pluck('property_id')
            ->filter()
            ->unique()
            ->toArray();

        $totalPropertyValue = !empty($propertyIds)
            ? (float) DB::table('properties')
                ->whereIn('id', $propertyIds)
                ->sum('price')
            : 0;

        $propertyEquity = !empty($propertyIds)
            ? (float) PropertyPaymentTransaction::whereIn('property_id', $propertyIds)
                ->where('member_id', $member->id)
                ->where('direction', 'credit')
                ->where('status', 'completed')
                ->sum('amount')
            : 0;

        // Wallet
        $wallet = $member->wallet;
        $walletBalance = $wallet ? (float) $wallet->balance : 0;

        // Calculate totals
        $totalAssets = $totalContributions + $totalInvestments + $propertyEquity + $walletBalance;
        $netWorth = $totalAssets - $loanBalance;

        return [
            'total_contributions' => $totalContributions,
            'avg_monthly_contribution' => $avgMonthlyContribution,
            'total_investments' => $totalInvestments,
            'investment_returns' => $investmentReturns,
            'total_loans' => $totalLoans,
            'loan_balance' => $loanBalance,
            'total_property_value' => $totalPropertyValue,
            'property_equity' => $propertyEquity,
            'wallet_balance' => $walletBalance,
            'total_assets' => $totalAssets,
            'net_worth' => $netWorth,
            'investment_count' => $investmentCount,
            'property_count' => $propertyCount,
            'loan_count' => $loanCount,
        ];
    }

    /**
     * Get available properties for investment
     */
    private function getAvailableProperties(): array
    {
        // Limit to top 10 properties and optimize image loading
        $properties = Property::where('status', 'available')
            ->select(['id', 'title', 'type', 'location', 'price', 'size', 'description'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Pre-fetch images efficiently (only first image per property)
        $propertyIds = $properties->pluck('id')->toArray();
        $imageMap = [];
        
        if (!empty($propertyIds)) {
            // Get primary images first
            $primaryImages = DB::table('property_images')
                ->whereIn('property_id', $propertyIds)
                ->where('is_primary', true)
                ->select('property_id', 'url')
                ->get();
            
            foreach ($primaryImages as $img) {
                $imageMap[$img->property_id] = $img->url;
            }
            
            // Get first non-primary image for properties without primary
            $propertiesWithoutPrimary = array_diff($propertyIds, array_keys($imageMap));
            if (!empty($propertiesWithoutPrimary)) {
                $fallbackImages = DB::table('property_images')
                    ->whereIn('property_id', $propertiesWithoutPrimary)
                    ->select('property_id', 'url')
                    ->orderBy('created_at')
                    ->get()
                    ->groupBy('property_id');
                
                foreach ($fallbackImages as $propertyId => $images) {
                    if (!isset($imageMap[$propertyId]) && $images->isNotEmpty()) {
                        $imageMap[$propertyId] = $images->first()->url;
                    }
                }
            }
        }

        return $properties->map(function ($property) use ($imageMap) {
            return [
                'id' => $property->id,
                'title' => $property->title,
                'type' => $property->type,
                'location' => $property->location,
                'price' => (float) $property->price,
                'size' => $property->size,
                'description' => substr($property->description ?? '', 0, 200), // Limit description length
                'image_url' => $imageMap[$property->id] ?? null,
            ];
        })->toArray();
    }

    /**
     * Get available investment plans
     */
    private function getAvailableInvestmentPlans(): array
    {
        $plans = InvestmentPlan::where('is_active', true)
            ->select(['id', 'name', 'description', 'min_amount', 'max_amount', 'expected_return_rate', 'risk_level', 'min_duration_months', 'max_duration_months'])
            ->orderBy('expected_return_rate', 'desc')
            ->get();

        return $plans->map(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'min_amount' => (float) $plan->min_amount,
                'max_amount' => (float) $plan->max_amount,
                'expected_return_rate' => (float) $plan->expected_return_rate,
                'risk_level' => $plan->risk_level,
                'min_duration_months' => $plan->min_duration_months,
                'max_duration_months' => $plan->max_duration_months,
            ];
        })->toArray();
    }

    /**
     * Generate AI recommendations using OpenAI API
     */
    private function generateAIRecommendations(array $financialProfile, array $properties, array $investmentPlans): array
    {
        $openaiApiKey = env('OPENAI_API_KEY');
        
        if (!$openaiApiKey) {
            Log::warning('OpenAI API key not configured, using fallback recommendations');
            return [];
        }

        try {
            // Prepare context for AI
            $context = $this->prepareAIContext($financialProfile, $properties, $investmentPlans);

            // Call OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini', // Using cost-effective model
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional financial advisor specializing in real estate and investment recommendations. Provide personalized, data-driven investment advice based on the user\'s financial profile. Always prioritize risk management and diversification. Format your response as a JSON array of recommendations.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $context,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->successful()) {
                $aiResponse = $response->json();
                $content = $aiResponse['choices'][0]['message']['content'] ?? null;
                
                if ($content) {
                    $recommendations = json_decode($content, true);
                    return $this->formatAIRecommendations($recommendations['recommendations'] ?? [], $properties, $investmentPlans);
                }
            }

            Log::warning('OpenAI API response invalid', ['response' => $response->json()]);
            return [];
        } catch (\Exception $e) {
            Log::error('OpenAI API error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Prepare context for AI analysis
     */
    private function prepareAIContext(array $financialProfile, array $properties, array $investmentPlans): string
    {
        $context = "Analyze this member's financial profile and provide personalized investment recommendations.\n\n";
        $context .= "FINANCIAL PROFILE:\n";
        $context .= "- Total Assets: ₦" . number_format($financialProfile['total_assets'], 2) . "\n";
        $context .= "- Available Capital (Wallet): ₦" . number_format($financialProfile['wallet_balance'], 2) . "\n";
        $context .= "- Current Investments: ₦" . number_format($financialProfile['total_investments'], 2) . "\n";
        $context .= "- Property Equity: ₦" . number_format($financialProfile['property_equity'], 2) . "\n";
        $context .= "- Loan Balance: ₦" . number_format($financialProfile['loan_balance'], 2) . "\n";
        $context .= "- Net Worth: ₦" . number_format($financialProfile['net_worth'], 2) . "\n";
        $context .= "- Average Monthly Contribution: ₦" . number_format($financialProfile['avg_monthly_contribution'], 2) . "\n";
        $context .= "- Number of Active Investments: " . $financialProfile['investment_count'] . "\n";
        $context .= "- Number of Properties: " . $financialProfile['property_count'] . "\n\n";

        $context .= "AVAILABLE PROPERTIES (Top 5):\n";
        foreach (array_slice($properties, 0, 5) as $property) {
            $context .= "- {$property['title']} ({$property['location']}): ₦" . number_format($property['price'], 2) . " - {$property['type']}\n";
        }

        $context .= "\nAVAILABLE INVESTMENT PLANS:\n";
        foreach ($investmentPlans as $plan) {
            $context .= "- {$plan['name']}: Min ₦" . number_format($plan['min_amount'], 2) . ", Expected Return: {$plan['expected_return_rate']}%, Risk: {$plan['risk_level']}\n";
        }

        $context .= "\nProvide 3-5 personalized investment recommendations in JSON format:\n";
        $context .= '{"recommendations": [{"type": "property|investment_plan", "id": "property_or_plan_id", "title": "Recommendation title", "reasoning": "Why this is recommended", "risk_level": "low|medium|high", "projected_roi": "X%", "confidence": "X%", "min_investment": "₦X", "time_horizon": "X years"}]}';

        return $context;
    }

    /**
     * Format AI recommendations with property/plan details
     */
    private function formatAIRecommendations(array $aiRecommendations, array $properties, array $investmentPlans): array
    {
        $formatted = [];

        foreach ($aiRecommendations as $rec) {
            $type = $rec['type'] ?? '';
            $id = $rec['id'] ?? null;

            if ($type === 'property') {
                $property = collect($properties)->firstWhere('id', $id);
                if ($property) {
                    $formatted[] = [
                        'type' => 'property',
                        'id' => $property['id'],
                        'title' => $property['title'],
                        'location' => $property['location'],
                        'price' => $property['price'],
                        'type_label' => $property['type'],
                        'reasoning' => $rec['reasoning'] ?? 'Recommended based on your financial profile',
                        'risk_level' => $rec['risk_level'] ?? 'medium',
                        'projected_roi' => $rec['projected_roi'] ?? '15-20%',
                        'confidence' => (float) ($rec['confidence'] ?? 85),
                        'min_investment' => $rec['min_investment'] ?? '₦' . number_format($property['price'] * 0.1, 2),
                        'time_horizon' => $rec['time_horizon'] ?? '3-5 years',
                        'image_url' => $property['image_url'],
                    ];
                }
            } elseif ($type === 'investment_plan') {
                $plan = collect($investmentPlans)->firstWhere('id', $id);
                if ($plan) {
                    $formatted[] = [
                        'type' => 'investment_plan',
                        'id' => $plan['id'],
                        'title' => $plan['name'],
                        'description' => $plan['description'],
                        'min_amount' => $plan['min_amount'],
                        'max_amount' => $plan['max_amount'],
                        'expected_return_rate' => $plan['expected_return_rate'],
                        'risk_level' => $plan['risk_level'],
                        'reasoning' => $rec['reasoning'] ?? 'Matches your investment capacity and risk tolerance',
                        'projected_roi' => $rec['projected_roi'] ?? $plan['expected_return_rate'] . '%',
                        'confidence' => (float) ($rec['confidence'] ?? 80),
                        'min_investment' => $rec['min_investment'] ?? '₦' . number_format($plan['min_amount'], 2),
                        'time_horizon' => $rec['time_horizon'] ?? ($plan['min_duration_months'] / 12) . '-' . ($plan['max_duration_months'] / 12) . ' years',
                    ];
                }
            }
        }

        // Sort by confidence score
        usort($formatted, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return $formatted;
    }

    /**
     * Calculate investment profile metrics
     */
    private function calculateInvestmentProfile(array $financialProfile): array
    {
        // Calculate risk tolerance based on current portfolio
        $riskScore = 50; // Default moderate
        
        if ($financialProfile['investment_count'] > 0) {
            // If user has investments, analyze their risk level
            $riskScore = 60; // Slightly more risk-tolerant
        }

        if ($financialProfile['loan_balance'] > $financialProfile['total_assets'] * 0.5) {
            // High debt-to-asset ratio = lower risk tolerance
            $riskScore -= 20;
        }

        if ($financialProfile['wallet_balance'] > $financialProfile['total_assets'] * 0.3) {
            // High cash holding = conservative
            $riskScore -= 10;
        }

        $riskScore = max(20, min(80, $riskScore)); // Clamp between 20-80

        $riskTolerance = 'moderate';
        if ($riskScore < 40) {
            $riskTolerance = 'conservative';
        } elseif ($riskScore > 60) {
            $riskTolerance = 'aggressive';
        }

        // Calculate investment capacity
        $availableCapital = $financialProfile['wallet_balance'];
        $recommendedAllocation = min($availableCapital * 0.7, $financialProfile['total_assets'] * 0.2);

        return [
            'risk_tolerance' => $riskTolerance,
            'risk_score' => $riskScore,
            'investment_capacity' => $availableCapital,
            'recommended_allocation' => $recommendedAllocation,
        ];
    }

    /**
     * Fallback investment profile (when AI is unavailable)
     */
    private function getFallbackInvestmentProfile($member): array
    {
        $wallet = $member->wallet;
        $walletBalance = $wallet ? (float) $wallet->balance : 0;

        return [
            'risk_tolerance' => 'moderate',
            'risk_score' => 50,
            'investment_capacity' => $walletBalance,
            'recommended_allocation' => $walletBalance * 0.7,
        ];
    }

    /**
     * Fallback recommendations (rule-based when AI is unavailable)
     */
    private function getFallbackRecommendations($member): array
    {
        $recommendations = [];
        $wallet = $member->wallet;
        $walletBalance = $wallet ? (float) $wallet->balance : 0;

        // Get top properties
        $properties = Property::where('status', 'available')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($properties as $index => $property) {
            if ($walletBalance >= $property->price * 0.1) {
            $recommendations[] = [
                    'type' => 'property',
                    'id' => $property->id,
                    'title' => $property->title,
                    'location' => $property->location,
                    'price' => (float) $property->price,
                    'type_label' => $property->type,
                    'reasoning' => 'This property matches your investment capacity and offers good growth potential.',
                    'risk_level' => 'medium',
                    'projected_roi' => '15-20%',
                    'confidence' => 75.0 - ($index * 5),
                    'min_investment' => '₦' . number_format($property->price * 0.1, 2),
                    'time_horizon' => '3-5 years',
                    'image_url' => null,
                ];
            }
        }

        // Get investment plans
        $plans = InvestmentPlan::where('is_active', true)
            ->where('min_amount', '<=', $walletBalance)
            ->orderBy('expected_return_rate', 'desc')
            ->limit(2)
            ->get();

        foreach ($plans as $plan) {
            $recommendations[] = [
                'type' => 'investment_plan',
                'id' => $plan->id,
                'title' => $plan->name,
                'description' => $plan->description,
                'min_amount' => (float) $plan->min_amount,
                'max_amount' => (float) $plan->max_amount,
                'expected_return_rate' => (float) $plan->expected_return_rate,
                'risk_level' => $plan->risk_level,
                'reasoning' => 'This investment plan aligns with your financial goals.',
                'projected_roi' => $plan->expected_return_rate . '%',
                'confidence' => 70.0,
                'min_investment' => '₦' . number_format($plan->min_amount, 2),
                'time_horizon' => ($plan->min_duration_months / 12) . '-' . ($plan->max_duration_months / 12) . ' years',
            ];
        }

        return $recommendations;
    }
}
