<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::withCount('servers')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));

        $plan = Plan::create($data);
        $this->audit->log('admin.plan.created', $plan);

        return back()->with('success', 'Plan created.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));
        $this->audit->log('admin.plan.updated', $plan);

        return back()->with('success', 'Plan updated.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->servers()->exists()) {
            return back()->with('error', 'Servers are still using this plan.');
        }

        $this->audit->log('admin.plan.deleted', $plan, ['name' => $plan->name]);
        $plan->delete();

        return back()->with('success', 'Plan deleted.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cpu_cores' => ['required', 'integer', 'min:1', 'max:256'],
            'memory_mb' => ['required', 'integer', 'min:256'],
            'disk_mb' => ['required', 'integer', 'min:1024'],
            'bandwidth_gb' => ['nullable', 'integer', 'min:0'],
            'disk_read_bps' => ['nullable', 'integer', 'min:0'],
            'disk_write_bps' => ['nullable', 'integer', 'min:0'],
            'network_speed_mbps' => ['nullable', 'integer', 'min:1'],
            'snapshot_limit' => ['required', 'integer', 'min:0'],
            'backup_limit' => ['required', 'integer', 'min:0'],
            'allocation_limit' => ['required', 'integer', 'min:1'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_public' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }
}
