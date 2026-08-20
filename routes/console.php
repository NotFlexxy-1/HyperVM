<?php

use App\Console\Commands\CollectNodeMetrics;
use App\Console\Commands\PruneNodeMetrics;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CollectNodeMetrics::class)->everyMinute()->withoutOverlapping();
Schedule::command(PruneNodeMetrics::class)->dailyAt('03:15');
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
