<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('username', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah'
            ], 401);
        }

        $user = User::where('username', $request->username)->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    public function adminExists()
    {
        $exists = User::where('role', 'admin')->exists();

        return response()->json([
            'success' => true,
            'data' => ['exists' => $exists],
        ]);
    }

    public function register(Request $request)
    {
        if (User::where('role', 'admin')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Admin sudah ada. Silakan login atau hubungi admin untuk membuat akun baru.',
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'username' => 'required|string|min:3|max:50|alpha_dash|unique:users,username',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => trim($request->input('name')),
            'username' => strtolower(trim($request->input('username'))),
            'password' => Hash::make($request->input('password')),
            'role' => 'admin',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi admin berhasil',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }
}
