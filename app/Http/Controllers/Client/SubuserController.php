<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ServerPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubuserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    private function authorizeManage(Request $request, Server $server): void
    {
        abort_unless(
            ServerPermissions::allows($request->user(), $server, 'subuser.manage'),
            403,
            'You cannot manage sub-users on this server.',
        );
    }

    public function store(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeManage($request, $server);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(ServerPermissions::all())],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        abort_if($user->id === $server->owner_id, 422, 'The owner already has full access to this server.');

        $server->subusers()->syncWithoutDetaching([
            $user->id => ['permissions' => json_encode(array_values($data['permissions']))],
        ]);

        $this->audit->log('server.subuser.added', $server, ['user' => $user->email]);

        return back()->with('success', "{$user->email} now has access to this server.");
    }

    public function update(Request $request, Server $server, User $user): RedirectResponse
    {
        $this->authorizeManage($request, $server);

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in(ServerPermissions::all())],
        ]);

        abort_unless($server->subusers()->whereKey($user->id)->exists(), 404);

        $server->subusers()->updateExistingPivot($user->id, [
            'permissions' => json_encode(array_values($data['permissions'])),
        ]);

        $this->audit->log('server.subuser.updated', $server, ['user' => $user->email]);

        return back()->with('success', 'Sub-user permissions updated.');
    }

    public function destroy(Request $request, Server $server, User $user): RedirectResponse
    {
        $this->authorizeManage($request, $server);

        $server->subusers()->detach($user->id);

        $this->audit->log('server.subuser.removed', $server, ['user' => $user->email]);

        return back()->with('success', 'Sub-user removed.');
    }
}
