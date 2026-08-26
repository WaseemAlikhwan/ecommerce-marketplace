<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->latest('id')
            ->paginate(30);

        $rows = $users->getCollection()->map(static function (User $user): array {
            return [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'roles' => $user->roles->pluck('name')->sort()->values()->all(),
            ];
        })->values()->all();

        return view('admin.users.index', [
            'users' => $users,
            'rows' => $rows,
        ]);
    }
}
