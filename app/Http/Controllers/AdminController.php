<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(): Collection
    {
        return User::where('role', 'nutritionist')->orderByDesc('created_at')->get(['id', 'name', 'email', 'crn', 'status', 'approved_at', 'created_at']);
    }

    public function update(Request $request, User $user): User
    {
        abort_if($user->role !== 'nutritionist', 403);
        $data = $request->validate(['status' => 'required|in:approved,rejected,pending']);
        $user->forceFill(['status' => $data['status'], 'approved_at' => $data['status'] === 'approved' ? now() : null])->save();

        return $user;
    }
}
