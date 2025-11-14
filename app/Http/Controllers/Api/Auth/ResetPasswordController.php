<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
	public function reset(Request $request): JsonResponse
	{
		$request->validate([
			'token' => 'required',
			'email' => 'required|email',
			'password' => 'required|confirmed|min:8',
		]);

		$status = Password::reset(
			$request->only('email', 'password', 'password_confirmation', 'token'),
			function ($user, $password) {
				$user->forceFill([
					'password' => Hash::make($password),
					'remember_token' => Str::random(60),
				])->save();
			}
		);

		if ($status === Password::PASSWORD_RESET) {
			return response()->json([
				'message' => __($status),
			]);
		}

		return response()->json([
			'message' => __($status),
		], 400);
	}
}


