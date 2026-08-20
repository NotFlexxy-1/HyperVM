<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles:id,name')
            ->withCount('servers')
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('username', 'like', "%{$s}%")
                    ->orWhere('discord_id', $s);
            }))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'alpha_dash', 'max:40', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['nullable', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'send_invite' => ['boolean'],
        ]);

        $password = $data['password'] ?? Str::password(20);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $password,
            'force_password_change' => empty($data['password']),
            'password_changed_at' => now(),
        ]);

        $user->syncRoles($data['roles'] ?? ['user']);
        $this->audit->log('admin.user.created', $user);

        return back()->with('success', 'User created.');
    }

    public function show(User $user): Response
    {
        return Inertia::render('Admin/Users/Show', [
            'user' => $user->load('roles:id,name'),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'servers' => $user->servers()->with('node:id,name')->paginate(10),
            'activity' => $user->auditLogs()->latest()->limit(25)->get(),
            'apiKeys' => $user->apiKeys()->get(['id', 'memo', 'identifier', 'last_used_at', 'expires_at']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'alpha_dash', 'max:40', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'is_suspended' => ['boolean'],
        ]);

        $user->update([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'is_suspended' => $data['is_suspended'] ?? false,
        ]);

        $user->syncRoles($data['roles'] ?? []);
        $this->audit->log('admin.user.updated', $user);

        return back()->with('success', 'User updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', Password::defaults()],
            'force_change' => ['boolean'],
            'revoke_sessions' => ['boolean'],
        ]);

        $user->forceFill([
            'password' => $data['password'],
            'password_changed_at' => now(),
            'force_password_change' => (bool) ($data['force_change'] ?? true),
        ])->save();

        if ($data['revoke_sessions'] ?? true) {
            \DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
        }

        $this->audit->log('admin.user.password_reset', $user);

        return back()->with('success', 'Password reset.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->servers()->exists()) {
            return back()->with('error', 'Transfer or delete this user\'s servers first.');
        }

        $this->audit->log('admin.user.deleted', $user, ['email' => $user->email]);
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
