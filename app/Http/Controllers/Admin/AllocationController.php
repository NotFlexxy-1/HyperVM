<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Node;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AllocationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Accepts a single address or a CIDR/range expansion. */
    public function store(Request $request, Node $node): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:ipv4,ipv6'],
            'address' => ['required', 'string', 'max:45'],
            'range_end' => ['nullable', 'string', 'max:45'],
            'cidr' => ['required', 'integer', 'between:1,128'],
            'gateway' => ['nullable', 'string', 'max:45'],
            'vlan_id' => ['nullable', 'integer', 'between:1,4094'],
            'mac_address' => ['nullable', 'string', 'max:17'],
            'label' => ['nullable', 'string', 'max:120'],
            'address_pool_id' => ['nullable', 'exists:address_pools,id'],
        ]);

        $addresses = $this->expand($data['address'], $data['range_end'] ?? null, $data['type']);
        $created = 0;

        foreach ($addresses as $address) {
            $allocation = Allocation::firstOrCreate(
                ['node_id' => $node->id, 'address' => $address],
                [
                    'type' => $data['type'],
                    'cidr' => $data['cidr'],
                    'gateway' => $data['gateway'] ?? null,
                    'vlan_id' => $data['vlan_id'] ?? null,
                    'mac_address' => count($addresses) === 1 ? ($data['mac_address'] ?? null) : null,
                    'label' => $data['label'] ?? null,
                    'address_pool_id' => $data['address_pool_id'] ?? null,
                ],
            );

            $created += $allocation->wasRecentlyCreated ? 1 : 0;
        }

        $this->audit->log('admin.allocation.created', $node, ['count' => $created]);

        return back()->with('success', "{$created} allocation(s) created.");
    }

    public function destroy(Allocation $allocation): RedirectResponse
    {
        if ($allocation->server_id) {
            return back()->with('error', 'This address is assigned to a server.');
        }

        $this->audit->log('admin.allocation.deleted', $allocation, ['address' => $allocation->address]);
        $allocation->delete();

        return back()->with('success', 'Allocation removed.');
    }

    /** @return array<int,string> */
    private function expand(string $start, ?string $end, string $type): array
    {
        if (! $end || $type !== 'ipv4') {
            return [$start];
        }

        $startLong = ip2long($start);
        $endLong = ip2long($end);

        if ($startLong === false || $endLong === false || $endLong < $startLong) {
            return [$start];
        }

        $endLong = min($endLong, $startLong + 1023); // hard safety cap
        $addresses = [];

        for ($i = $startLong; $i <= $endLong; $i++) {
            $addresses[] = long2ip($i);
        }

        return $addresses;
    }
}
