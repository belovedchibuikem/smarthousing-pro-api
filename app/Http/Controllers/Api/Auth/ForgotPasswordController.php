<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
	public function sendResetLinkEmail(Request $request): JsonResponse
	{
		$request->validate(['email' => 'required|email']);

		$status = Password::broker()->sendResetLink(
			$request->only('email')
		);

		if ($status === Password::RESET_LINK_SENT) {
			return response()->json([
				'message' => __($status),
			]);
		}

		return response()->json([
			'message' => __($status),
		], 400);
	}
}


