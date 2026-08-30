<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\User;

class AccountController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => User::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'username' => 'required|string|min:3|max:50|alpha_dash|unique:users,username',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,petugas,kiosk,tvdisplay',
            'counterNumber' => 'nullable|integer|min:1',
        ]);

        $user = User::create([
            'name' => trim($request->input('name')),
            'username' => strtolower(trim($request->input('username'))),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role'),
            'counter_number' => $request->input('counterNumber'),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dibuat',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak ditemukan',
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|required|in:admin,petugas,kiosk,tvdisplay',
            'counterNumber' => 'nullable|integer|min:1',
        ]);

        $changes = [];

        if ($request->has('name')) {
            $changes['name'] = trim($request->input('name'));
        }

        if ($request->has('username')) {
            $changes['username'] = strtolower(trim($request->input('username')));
        }

        if ($request->filled('password')) {
            $changes['password'] = Hash::make($request->input('password'));
        }

        if ($request->has('role')) {
            $changes['role'] = $request->input('role');
        }

        if (array_key_exists('counterNumber', $request->all())) {
            $changes['counter_number'] = $request->input('counterNumber');
        }

        $user->update($changes);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil diperbarui',
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if ((string) $request->user()->id === (string) $id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa menghapus akun sendiri',
            ], 400);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak ditemukan',
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dihapus',
        ]);
    }
}
