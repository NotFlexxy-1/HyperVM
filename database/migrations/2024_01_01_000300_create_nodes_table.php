<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('short_code')->unique();
            $table->string('name');
            $table->string('country_code', 2)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name');
            $table->string('fqdn');
            $table->unsignedSmallInteger('port')->default(8006);
            $table->string('cluster')->nullable();
            $table->string('proxmox_node_name'); // node name inside the PVE cluster (e.g. "pve01")
            $table->string('token_id');           // e.g. hypervm@pve!panel
            $table->text('token_secret');         // encrypted at rest by the model cast
            $table->boolean('verify_tls')->default(true);
            $table->string('storage_pool')->default('local-lvm');
            $table->string('backup_storage_pool')->nullable();
            $table->string('iso_storage_pool')->nullable();
            $table->string('network_bridge')->default('vmbr0');
            $table->unsignedBigInteger('memory_mb');          // total allocatable memory
            $table->unsignedBigInteger('memory_overallocate')->default(0); // percentage
            $table->unsignedBigInteger('disk_mb');
            $table->unsignedBigInteger('disk_overallocate')->default(0);
            $table->unsignedInteger('cpu_cores');
            $table->unsignedInteger('cpu_overallocate')->default(0);
            $table->unsignedInteger('vm_limit')->nullable();
            $table->boolean('is_maintenance')->default(false);
            $table->boolean('is_deployable')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('node_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->decimal('cpu_usage', 6, 3)->default(0);
            $table->unsignedBigInteger('memory_used_bytes')->default(0);
            $table->unsignedBigInteger('memory_total_bytes')->default(0);
            $table->unsignedBigInteger('disk_used_bytes')->default(0);
            $table->unsignedBigInteger('disk_total_bytes')->default(0);
            $table->unsignedBigInteger('uptime_seconds')->default(0);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_metrics');
        Schema::dropIfExists('nodes');
        Schema::dropIfExists('locations');
    }
};
