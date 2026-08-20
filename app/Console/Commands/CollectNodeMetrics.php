<?php

namespace App\Console\Commands;

use App\Models\Node;
use App\Services\Proxmox\NodeService;
use Illuminate\Console\Command;

class CollectNodeMetrics extends Command
{
    protected $signature = 'hypervm:collect-metrics';

    protected $description = 'Poll every Proxmox node and store a metrics sample.';

    public function handle(NodeService $nodes): int
    {
        $failures = 0;

        foreach (Node::all() as $node) {
            try {
                $nodes->recordMetrics($node);
                $this->line("Collected metrics for {$node->name}.");
            } catch (\Throwable $e) {
                $failures++;
                $this->error("{$node->name}: {$e->getMessage()}");
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
