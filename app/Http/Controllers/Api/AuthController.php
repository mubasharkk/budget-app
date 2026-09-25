<?php

namespace App\Http\Controllers\Api;

use App\Domain\Identity\Services\ApiTokenService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private ApiTokenService $apiTokenService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json($this->apiTokenService->issue(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }
}
