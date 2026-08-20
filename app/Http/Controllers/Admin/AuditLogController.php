<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->with('user:id,name,username')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', "%{$a}%"))
            ->when($request->query('user'), fn ($q, $u) => $q->where('user_id', $u))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only('action', 'user'),
        ]);
    }
}
