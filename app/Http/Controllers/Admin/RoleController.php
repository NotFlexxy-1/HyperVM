<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Roles/Index', [
            'roles' => Role::with('permissions:id,name')->withCount('users')->orderBy('name')->get(),
            'availablePermissions' => Permissions::grouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'description' => $data['description'] ?? null,
            'colour' => $data['colour'] ?? null,
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        $this->audit->log('admin.role.created', $role);

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);

        if ($role->is_protected && $role->name !== $data['name']) {
            return back()->with('error', 'Protected roles cannot be renamed.');
        }

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'colour' => $data['colour'] ?? null,
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        $this->audit->log('admin.role.updated', $role);

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_protected) {
            return back()->with('error', 'This role is protected and cannot be deleted.');
        }

        $this->audit->log('admin.role.deleted', $role, ['name' => $role->name]);
        $role->delete();

        return back()->with('success', 'Role deleted.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'colour' => ['nullable', 'string', 'max:9'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ]);
    }
}
