<?php

namespace App\Domain\Identity\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ApiTokenService
{
    /**
     * Verify credentials and issue a Sanctum personal access token.
     *
     * @return array{token: string, user: array{id: int, name: string, email: string}}
     *
     * @throws ValidationException
     */
    public function issue(string $email, string $password, ?string $deviceName = null): array
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        return [
            'token' => $user->createToken($deviceName ?? 'mobile-app')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }
}
