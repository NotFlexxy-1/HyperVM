<?php

namespace App\Console\Commands;

use App\Models\NodeMetric;
use Illuminate\Console\Command;

class PruneNodeMetrics extends Command
{
    protected $signature = 'hypervm:prune-metrics {--days=14}';

    protected $description = 'Delete node metric samples older than the retention window.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = NodeMetric::where('recorded_at', '<', now()->subDays($days))->delete();

        $this->info("Removed {$deleted} metric samples older than {$days} days.");

        return self::SUCCESS;
    }
}
