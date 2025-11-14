<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant\LandingPageConfig;
use App\Models\Tenant\LoanProduct;
use App\Models\Tenant\InvestmentPlan;
use App\Models\Tenant\Property;
use Illuminate\Http\JsonResponse;

class LandingPageController extends Controller
{
	public function show(): JsonResponse
	{
		// Refresh the model to ensure we get the latest data
		$page = LandingPageConfig::where('tenant_id', tenant('id'))->first();
		if (!$page || !$page->is_published) {
			return response()->json([
				'message' => 'Landing page is not published',
			], 404);
		}

		// Refresh to get latest data from database
		$page->refresh();
		
		$sections = $page->sections ?? [];
		$theme = $page->theme ?? [];
		
		// Ensure theme has all required fields with defaults
		$theme = array_merge([
			'primary_color' => '#FDB11E',
			'secondary_color' => '#276254',
			'accent_color' => '#10b981',
			'font_family' => 'Inter',
		], $theme);
		
		// Process sections to determine what data to fetch
		$loanSection = collect($sections)->firstWhere('type', 'loans');
		$investmentSection = collect($sections)->firstWhere('type', 'investments');
		$propertySection = collect($sections)->firstWhere('type', 'properties');

		// Fetch loan products based on section config
		$loanProducts = $this->fetchLoanProducts($loanSection);
		
		// Fetch investment plans based on section config
		$investmentPlans = $this->fetchInvestmentPlans($investmentSection);
		
		// Fetch properties based on section config
		$properties = $this->fetchProperties($propertySection);

		return response()->json([
			'page' => [
				'id' => $page->id,
				'template_id' => $page->template_id ?? 'default',
				'is_published' => (bool) $page->is_published,
				'sections' => $sections,
				'theme' => $theme,
				'seo' => $page->seo ?? [],
			],
			'plans' => [
				'loans' => $loanProducts,
				'investments' => $investmentPlans,
			],
			'properties' => $properties,
		])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
		  ->header('Pragma', 'no-cache')
		  ->header('Expires', '0');
	}

	/**
	 * Fetch loan products based on section configuration
	 */
	private function fetchLoanProducts(?array $section): array
	{
		$config = $section['config'] ?? [];
		$dataSource = $config['data_source'] ?? 'all_active';
		$limit = $config['limit'] ?? 6;
		$sortBy = $config['sort_by'] ?? 'name';
		$sortOrder = $config['sort_order'] ?? 'asc';

		$query = LoanProduct::where('is_active', true);

		// If specific IDs are selected, filter by them
		if ($dataSource === 'selected' && !empty($config['selected_ids'])) {
			$query->whereIn('id', $config['selected_ids']);
		}

		// Apply sorting
		$query->orderBy($sortBy, $sortOrder);

		// Apply limit
		if ($limit > 0) {
			$query->limit($limit);
		}

		return $query
			->select('id', 'name', 'description', 'min_amount', 'max_amount', 'interest_rate', 'min_tenure_months', 'max_tenure_months', 'interest_type', 'eligibility_criteria', 'required_documents')
			->get()
			->map(function ($product) {
				return [
					'id' => $product->id,
					'name' => $product->name,
					'description' => $product->description,
					'min_amount' => $product->min_amount,
					'max_amount' => $product->max_amount,
					'interest_rate' => $product->interest_rate,
					'min_tenure_months' => $product->min_tenure_months,
					'max_tenure_months' => $product->max_tenure_months,
					'tenure_range' => $product->min_tenure_months . '-' . $product->max_tenure_months . ' months',
					'interest_type' => $product->interest_type,
					'eligibility_criteria' => $product->eligibility_criteria ?? [],
					'required_documents' => $product->required_documents ?? [],
				];
			})
			->toArray();
	}

	/**
	 * Fetch investment plans based on section configuration
	 */
	private function fetchInvestmentPlans(?array $section): array
	{
		$config = $section['config'] ?? [];
		$dataSource = $config['data_source'] ?? 'all_active';
		$limit = $config['limit'] ?? 6;
		$sortBy = $config['sort_by'] ?? 'name';
		$sortOrder = $config['sort_order'] ?? 'asc';

		$query = InvestmentPlan::where('is_active', true);

		// If specific IDs are selected, filter by them
		if ($dataSource === 'selected' && !empty($config['selected_ids'])) {
			$query->whereIn('id', $config['selected_ids']);
		}

		// Apply sorting
		$query->orderBy($sortBy, $sortOrder);

		// Apply limit
		if ($limit > 0) {
			$query->limit($limit);
		}

		return $query
			->select('id', 'name', 'description', 'min_amount', 'max_amount', 'expected_return_rate', 'min_duration_months', 'max_duration_months', 'return_type', 'risk_level', 'features')
			->get()
			->map(function ($plan) {
				return [
					'id' => $plan->id,
					'name' => $plan->name,
					'description' => $plan->description,
					'min_amount' => $plan->min_amount,
					'max_amount' => $plan->max_amount,
					'expected_return_rate' => $plan->expected_return_rate,
					'min_duration_months' => $plan->min_duration_months,
					'max_duration_months' => $plan->max_duration_months,
					'duration_range' => $plan->min_duration_months . '-' . $plan->max_duration_months . ' months',
					'return_type' => $plan->return_type,
					'risk_level' => $plan->risk_level,
					'features' => $plan->features ?? [],
				];
			})
			->toArray();
	}

	/**
	 * Fetch properties based on section configuration
	 */
	private function fetchProperties(?array $section): array
	{
		$config = $section['config'] ?? [];
		$dataSource = $config['data_source'] ?? 'all_active';
		$limit = $config['limit'] ?? 6;
		$sortBy = $config['sort_by'] ?? 'created_at';
		$sortOrder = $config['sort_order'] ?? 'desc';

		$query = Property::where('status', 'available');

		// If specific IDs are selected, filter by them
		if ($dataSource === 'selected' && !empty($config['selected_ids'])) {
			$query->whereIn('id', $config['selected_ids']);
		}

		// Apply sorting - map sort_by to actual column names
		$sortColumnMap = [
			'name' => 'title',
			'created_at' => 'created_at',
			'price' => 'price',
		];
		$actualSortBy = $sortColumnMap[$sortBy] ?? ($sortBy === 'name' ? 'title' : $sortBy);
		$query->orderBy($actualSortBy, $sortOrder);

		// Apply limit
		if ($limit > 0) {
			$query->limit($limit);
		}

		return $query
			->with('images')
			->select('id', 'title', 'type', 'location', 'price', 'size', 'bedrooms', 'bathrooms', 'description')
			->get()
			->map(function ($property) {
				$primaryImage = $property->images()->where('is_primary', true)->first();
				return [
					'id' => $property->id,
					'name' => $property->title,
					'type' => $property->type,
					'location' => $property->location ?? 'Location not specified',
					'price' => $property->price,
					'size' => $property->size,
					'bedrooms' => $property->bedrooms,
					'bathrooms' => $property->bathrooms,
					'image' => $primaryImage?->url ?? $property->images()->first()?->url ?? null,
					'description' => $property->description,
				];
			})
			->toArray();
	}
}


