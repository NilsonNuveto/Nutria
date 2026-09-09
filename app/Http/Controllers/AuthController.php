<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => mb_strtolower(trim($request->input('email', '')))]);
        $data = $request->validate(['name' => 'required|string|max:160', 'email' => 'required|email|max:255|unique:users', 'crn' => 'nullable|string|max:50', 'password' => ['required', 'confirmed', Password::min(12)]]);
        $adminEmail = mb_strtolower(trim((string) config('nutria.admin_email')));
        abort_if($adminEmail !== '' && $data['email'] === $adminEmail, 422, 'Este e-mail está reservado ao administrador.');
        $user = new User;
        $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'crn' => $data['crn'] ?? null, 'role' => 'nutritionist', 'status' => 'pending'])->save();

        return response()->json(['message' => 'Solicitação enviada. Aguarde a aprovação do administrador para fazer login.'], 201);
    }

    public function login(Request $request): array
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', mb_strtolower(trim($data['email'])))->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 422, 'E-mail ou senha inválidos.');
        abort_unless($user->status === 'approved' && $user->setup_token === null, 403, 'Cadastro aguardando aprovação ou acesso não liberado.');
        Auth::login($user);
        $request->session()->regenerate();

        return ['redirect' => $user->role === 'admin' ? route('admin') : route('home')];
    }

    public function setup(Request $request): array
    {
        $data = $request->validate(['email' => 'required|email', 'token' => 'required|string|max:100', 'password' => ['required', 'confirmed', Password::min(12)]]);
        DB::transaction(function () use ($data): void {
            $user = User::where('email', mb_strtolower(trim($data['email'])))->where('role', 'admin')->lockForUpdate()->first();
            abort_unless($user?->setup_token && hash_equals($user->setup_token, hash('sha256', $data['token'])), 422, 'Código inválido ou primeiro acesso já concluído.');
            $changed = User::whereKey($user->id)->where('setup_token', $user->setup_token)->update(['password' => Hash::make($data['password']), 'setup_token' => null]);
            abort_unless($changed, 409, 'Primeiro acesso já concluído.');
            Auth::login($user->fresh());
        });
        $request->session()->regenerate();

        return ['redirect' => route('admin')];
    }

    public function logout(Request $request): array
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ['redirect' => route('login')];
    }
}
