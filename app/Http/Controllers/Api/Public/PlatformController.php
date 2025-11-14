<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;

class PlatformController extends Controller
{
	public function stats(): JsonResponse
	{
		$tenants = Tenant::count();
		return response()->json([
			'stats' => [
				'total_tenants' => $tenants,
				// Extend with more platform-wide stats as needed
			],
		]);
	}
}


