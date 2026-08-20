<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Client;
use App\Http\Middleware\EnsureServerIsAccessible;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [Auth\LoginController::class, 'create'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::middleware('registration.enabled')->group(function () {
        Route::get('/register', [Auth\RegisterController::class, 'create'])->name('register');
        Route::post('/register', [Auth\RegisterController::class, 'store'])->middleware('throttle:6,1');
    });

    Route::get('/forgot-password', [Auth\PasswordController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [Auth\PasswordController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:6,1');
    Route::get('/reset-password/{token}', [Auth\PasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [Auth\PasswordController::class, 'reset'])->name('password.update');

    Route::get('/auth/discord/redirect', [Auth\DiscordController::class, 'redirect'])->name('auth.discord.redirect');
    Route::get('/auth/discord/callback', [Auth\DiscordController::class, 'callback'])->name('auth.discord.callback');
});

/*
|--------------------------------------------------------------------------
| Two-factor challenge (post-password, pre-session)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/two-factor', [Auth\LoginController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor', [Auth\LoginController::class, 'verifyChallenge'])
        ->name('two-factor.verify')->middleware('throttle:10,1');
});

/*
|--------------------------------------------------------------------------
| Authenticated client area
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [Auth\LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', Client\DashboardController::class)->name('dashboard');

    Route::get('/account', [Client\AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account', [Client\AccountController::class, 'update'])->name('account.update');
    Route::patch('/account/preferences', [Client\AccountController::class, 'updatePreferences'])->name('account.preferences');
    Route::put('/account/password', [Auth\PasswordController::class, 'update'])->name('account.password');
    Route::post('/account/api-keys', [Client\AccountController::class, 'storeApiKey'])->name('account.api-keys.store');
    Route::delete('/account/api-keys/{apiKey}', [Client\AccountController::class, 'destroyApiKey'])->name('account.api-keys.destroy');
    Route::delete('/account/sessions/{session}', [Client\AccountController::class, 'destroySession'])->name('account.sessions.destroy');

    Route::prefix('account/two-factor')->name('account.2fa.')->group(function () {
        Route::post('/', [Client\TwoFactorController::class, 'create'])->name('create');
        Route::post('/confirm', [Client\TwoFactorController::class, 'confirm'])->name('confirm');
        Route::post('/recovery-codes', [Client\TwoFactorController::class, 'recoveryCodes'])->name('recovery');
        Route::delete('/', [Client\TwoFactorController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('servers/{server}')
        ->middleware(EnsureServerIsAccessible::class)
        ->name('servers.')
        ->group(function () {
            Route::get('/', [Client\ServerController::class, 'show'])->name('show');
            Route::get('/status', [Client\ServerController::class, 'status'])->name('status');
            Route::get('/metrics', [Client\ServerController::class, 'metrics'])->name('metrics');
            Route::post('/power', [Client\ServerController::class, 'power'])->name('power');

            Route::get('/console', [Client\ServerController::class, 'console'])->name('console');
            Route::get('/console/ticket', [Client\ServerController::class, 'consoleTicket'])->name('console.ticket');

            Route::get('/resources', [Client\ServerController::class, 'resources'])->name('resources');
            Route::patch('/resources', [Client\ServerController::class, 'updateHardware'])->name('resources.update');
            Route::patch('/cloud-init', [Client\ServerController::class, 'updateCloudInit'])->name('cloudinit.update');

            Route::get('/network', [Client\ServerController::class, 'network'])->name('network');
            Route::post('/network/sync', [Client\ServerController::class, 'syncNetwork'])->name('network.sync');
            Route::patch('/network/rate', [Client\ServerController::class, 'updateNetworkRate'])->name('network.rate');
            Route::patch('/network/firewall', [Client\ServerController::class, 'updateFirewall'])->name('network.firewall');
            Route::post('/network/firewall/rules', [Client\ServerController::class, 'storeFirewallRule'])->name('network.firewall.rules.store');
            Route::delete('/network/firewall/rules/{position}', [Client\ServerController::class, 'destroyFirewallRule'])->name('network.firewall.rules.destroy');

            Route::get('/media', [Client\ServerController::class, 'media'])->name('media');
            Route::post('/media/mount', [Client\ServerController::class, 'mountMedia'])->name('media.mount');
            Route::post('/media/unmount', [Client\ServerController::class, 'unmountMedia'])->name('media.unmount');
            Route::patch('/media/boot-order', [Client\ServerController::class, 'updateBootOrder'])->name('media.boot');

            Route::get('/backups', [Client\ServerController::class, 'backups'])->name('backups');
            Route::post('/backups', [Client\ServerController::class, 'createBackup'])->name('backups.store');
            Route::post('/backups/{backup}/restore', [Client\ServerController::class, 'restoreBackup'])->name('backups.restore');
            Route::delete('/backups/{backup}', [Client\ServerController::class, 'deleteBackup'])->name('backups.destroy');
            Route::post('/snapshots', [Client\ServerController::class, 'createSnapshot'])->name('snapshots.store');
            Route::post('/snapshots/{snapshot}/rollback', [Client\ServerController::class, 'rollbackSnapshot'])->name('snapshots.rollback');
            Route::delete('/snapshots/{snapshot}', [Client\ServerController::class, 'deleteSnapshot'])->name('snapshots.destroy');

            Route::get('/activity', [Client\ServerController::class, 'activity'])->name('activity');
            Route::get('/activity/task-log', [Client\ServerController::class, 'taskLog'])->name('activity.log');

            Route::get('/settings', [Client\ServerController::class, 'settings'])->name('settings');
            Route::patch('/settings', [Client\ServerController::class, 'rename'])->name('rename');
            Route::post('/reinstall', [Client\ServerController::class, 'reinstall'])->name('reinstall');

            Route::post('/subusers', [Client\SubuserController::class, 'store'])->name('subusers.store');
            Route::patch('/subusers/{user}', [Client\SubuserController::class, 'update'])->name('subusers.update');
            Route::delete('/subusers/{user}', [Client\SubuserController::class, 'destroy'])->name('subusers.destroy');
        });
});


/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin|moderator'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::get('/nodes/{node}/live', [Admin\DashboardController::class, 'nodeStatus'])->name('nodes.live');

        // Static segments must be registered before the {node}/{server} wildcards.
        Route::middleware('permission:node.create')->group(function () {
            Route::get('/nodes/create', [Admin\NodeController::class, 'create'])->name('nodes.create');
            Route::post('/nodes', [Admin\NodeController::class, 'store'])->name('nodes.store');
        });

        Route::middleware('permission:node.view')->group(function () {
            Route::get('/nodes', [Admin\NodeController::class, 'index'])->name('nodes.index');
            Route::get('/nodes/{node}', [Admin\NodeController::class, 'show'])->name('nodes.show');
        });

        Route::middleware('permission:node.update')->group(function () {
            Route::get('/nodes/{node}/edit', [Admin\NodeController::class, 'edit'])->name('nodes.edit');
            Route::patch('/nodes/{node}', [Admin\NodeController::class, 'update'])->name('nodes.update');
            Route::post('/nodes/{node}/test', [Admin\NodeController::class, 'test'])->name('nodes.test');
        });

        Route::delete('/nodes/{node}', [Admin\NodeController::class, 'destroy'])
            ->middleware('permission:node.delete')->name('nodes.destroy');

        Route::middleware('permission:node.allocations')->group(function () {
            Route::post('/nodes/{node}/allocations', [Admin\AllocationController::class, 'store'])->name('allocations.store');
            Route::delete('/allocations/{allocation}', [Admin\AllocationController::class, 'destroy'])->name('allocations.destroy');
        });

        Route::middleware('permission:server.create')->group(function () {
            Route::get('/servers/create', [Admin\ServerController::class, 'create'])->name('servers.create');
            Route::post('/servers', [Admin\ServerController::class, 'store'])->name('servers.store');
            Route::get('/nodes/{node}/available-allocations', [Admin\ServerController::class, 'availableAllocations'])->name('servers.allocations');
            Route::get('/user-search', [Admin\ServerController::class, 'searchUsers'])->name('servers.user-search');
        });

        Route::get('/servers', [Admin\ServerController::class, 'index'])->name('servers.index');
        Route::get('/servers/{server}', [Admin\ServerController::class, 'show'])->name('servers.show');



        Route::middleware('permission:server.update')->group(function () {
            Route::patch('/servers/{server}', [Admin\ServerController::class, 'update'])->name('servers.update');
            Route::post('/servers/{server}/resize', [Admin\ServerController::class, 'resize'])->name('servers.resize');
        });

        Route::middleware('permission:server.suspend')->group(function () {
            Route::post('/servers/{server}/suspend', [Admin\ServerController::class, 'suspend'])->name('servers.suspend');
            Route::post('/servers/{server}/unsuspend', [Admin\ServerController::class, 'unsuspend'])->name('servers.unsuspend');
        });

        Route::delete('/servers/{server}', [Admin\ServerController::class, 'destroy'])
            ->middleware('permission:server.delete')->name('servers.destroy');

        Route::middleware('permission:user.view')->group(function () {
            Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        });

        Route::post('/users', [Admin\UserController::class, 'store'])->middleware('permission:user.create')->name('users.store');
        Route::patch('/users/{user}', [Admin\UserController::class, 'update'])->middleware('permission:user.update')->name('users.update');
        Route::post('/users/{user}/password', [Admin\UserController::class, 'resetPassword'])->middleware('permission:user.password')->name('users.password');
        Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->middleware('permission:user.delete')->name('users.destroy');

        Route::middleware('permission:role.manage')->group(function () {
            Route::get('/roles', [Admin\RoleController::class, 'index'])->name('roles.index');
            Route::post('/roles', [Admin\RoleController::class, 'store'])->name('roles.store');
            Route::patch('/roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
            Route::delete('/roles/{role}', [Admin\RoleController::class, 'destroy'])->name('roles.destroy');
        });

        Route::middleware('permission:plan.manage')->group(function () {
            Route::get('/plans', [Admin\PlanController::class, 'index'])->name('plans.index');
            Route::post('/plans', [Admin\PlanController::class, 'store'])->name('plans.store');
            Route::patch('/plans/{plan}', [Admin\PlanController::class, 'update'])->name('plans.update');
            Route::delete('/plans/{plan}', [Admin\PlanController::class, 'destroy'])->name('plans.destroy');
        });

        Route::middleware('permission:location.manage')->group(function () {
            Route::get('/locations', [Admin\LocationController::class, 'index'])->name('locations.index');
            Route::post('/locations', [Admin\LocationController::class, 'store'])->name('locations.store');
            Route::patch('/locations/{location}', [Admin\LocationController::class, 'update'])->name('locations.update');
            Route::delete('/locations/{location}', [Admin\LocationController::class, 'destroy'])->name('locations.destroy');
        });

        Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])
            ->middleware('permission:audit.view')->name('audit.index');

        Route::middleware('permission:settings.manage')->prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [Admin\SettingsController::class, 'index'])->name('index');
            Route::post('/branding', [Admin\SettingsController::class, 'updateBranding'])->name('branding');
            Route::post('/logo', [Admin\SettingsController::class, 'updateLogo'])->name('logo');
            Route::post('/favicon', [Admin\SettingsController::class, 'updateFavicon'])->name('favicon');
            Route::delete('/asset', [Admin\SettingsController::class, 'removeAsset'])->name('asset.destroy');
            Route::post('/theme', [Admin\SettingsController::class, 'updateTheme'])->name('theme');
            Route::post('/layout', [Admin\SettingsController::class, 'updateLayout'])->name('layout');
            Route::post('/access', [Admin\SettingsController::class, 'updateAccess'])->name('access');
        });
    });
