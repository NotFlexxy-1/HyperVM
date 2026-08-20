<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('uuid_short', 12)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->unsignedInteger('vmid');
            $table->enum('status', ['installing', 'install_failed', 'suspended', 'restoring', 'deleting', 'ready'])
                ->default('installing');
            $table->unsignedInteger('cpu_cores');
            $table->unsignedBigInteger('memory_mb');
            $table->unsignedBigInteger('disk_mb');
            $table->unsignedBigInteger('bandwidth_gb')->nullable();
            $table->unsignedBigInteger('bandwidth_used_bytes')->default(0);
            $table->unsignedInteger('snapshot_limit')->default(2);
            $table->unsignedInteger('backup_limit')->default(2);
            $table->unsignedInteger('network_speed_mbps')->nullable();
            $table->string('template')->nullable();      // e.g. ubuntu-22.04
            $table->string('os_type')->default('l26');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
            $table->unique(['node_id', 'vmid']);
        });

        Schema::table('allocations', function (Blueprint $table) {
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
        });

        Schema::create('server_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('permissions')->nullable();
            $table->timestamps();
            $table->unique(['server_id', 'user_id']);
        });

        Schema::create('server_backups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('name');
            $table->string('volume_id')->nullable(); // PVE volid
            $table->string('compression_type')->default('zstd');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('is_successful')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('server_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('include_ram')->default(false);
            $table->boolean('is_successful')->default(false);
            $table->timestamps();
            $table->unique(['server_id', 'name']);
        });

        Schema::create('server_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');            // power.start, backup.create, ...
            $table->string('upid')->nullable();  // Proxmox unique process id
            $table->string('status')->default('queued');
            $table->json('payload')->nullable();
            $table->text('output')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_tasks');
        Schema::dropIfExists('server_snapshots');
        Schema::dropIfExists('server_backups');
        Schema::dropIfExists('server_user');
        Schema::dropIfExists('servers');
    }
};
