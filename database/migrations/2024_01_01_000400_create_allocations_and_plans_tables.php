<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('address_pool_node', function (Blueprint $table) {
            $table->foreignId('address_pool_id')->constrained('address_pools')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->primary(['address_pool_id', 'node_id']);
        });

        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('address_pool_id')->nullable()->constrained('address_pools')->nullOnDelete();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignId('server_id')->nullable();
            $table->enum('type', ['ipv4', 'ipv6'])->default('ipv4');
            $table->string('address', 45);
            $table->unsignedTinyInteger('cidr')->default(24);
            $table->string('gateway', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->unsignedSmallInteger('vlan_id')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
            $table->unique(['node_id', 'address']);
            $table->index('server_id');
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('cpu_cores');
            $table->unsignedBigInteger('memory_mb');
            $table->unsignedBigInteger('disk_mb');
            $table->unsignedBigInteger('bandwidth_gb')->nullable();
            $table->unsignedInteger('disk_read_bps')->nullable();
            $table->unsignedInteger('disk_write_bps')->nullable();
            $table->unsignedInteger('network_speed_mbps')->nullable();
            $table->unsignedInteger('snapshot_limit')->default(2);
            $table->unsignedInteger('backup_limit')->default(2);
            $table->unsignedInteger('allocation_limit')->default(1);
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('address_pool_node');
        Schema::dropIfExists('address_pools');
    }
};
