<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register user baru.
     *
     * POST /api/v1/auth/register
     * Body: { name, email, password, password_confirmation }
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => User::ROLE_USER,
        ]);

        $token = $user->createToken(
            name: 'auth_token',
            abilities: ['*'],
        )->plainTextToken;

        return $this->createdResponse(
            data: [
                'user'         => new UserResource($user),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
            message: 'Registrasi berhasil. Selamat datang, ' . $user->name . '!',
        );
    }

    /**
     * Login user dan kembalikan Bearer Token.
     *
     * POST /api/v1/auth/login
     * Body: { email, password }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Verifikasi kredensial
        if (! Auth::attempt($request->only('email', 'password'))) {
            return $this->unauthorizedResponse('Email atau password salah.');
        }

        /** @var User $user */
        $user = Auth::user();

        // Hapus token lama agar tidak menumpuk (opsional: satu device satu token)
        $user->tokens()->delete();

        $token = $user->createToken(
            name: 'auth_token',
            abilities: ['*'],
        )->plainTextToken;

        return $this->successResponse(
            data: [
                'user'         => new UserResource($user),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
            message: 'Login berhasil. Selamat datang kembali, ' . $user->name . '!',
        );
    }

    /**
     * Logout — hapus token aktif user saat ini.
     *
     * POST /api/v1/auth/logout
     * Header: Authorization: Bearer {token}
     */
    public function logout(Request $request): JsonResponse
    {
        // Hapus hanya token yang digunakan untuk request ini
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(message: 'Logout berhasil. Sampai jumpa!');
    }

    /**
     * Kembalikan data user yang sedang login.
     *
     * GET /api/v1/auth/me
     * Header: Authorization: Bearer {token}
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            data: new UserResource($request->user()),
            message: 'Data user berhasil diambil.',
        );
    }
}
