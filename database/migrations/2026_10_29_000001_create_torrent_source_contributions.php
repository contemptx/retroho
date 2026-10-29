<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('torrent_source_ledger', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->char('instance_id', 64)->unique();
        });
        DB::table('torrent_source_ledger')->insert(['id' => 1, 'instance_id' => bin2hex(random_bytes(32))]);
        Schema::create('torrent_source_contributions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('caller_id');
            $table->char('source_key', 64);
            $table->string('contribution_id', 128);
            $table->unsignedInteger('user_id');
            $table->string('username', 255);
            $table->string('email', 254);
            $table->text('torrent_ids');
            $table->unsignedBigInteger('generation');
            $table->string('desired', 12);
            $table->timestamps();
            $table->unique(['caller_id', 'source_key'], 'torrent_source_caller_key');
            $table->index(['user_id', 'desired'], 'torrent_source_user_state');
        });
        Schema::create('torrent_source_resources', function (Blueprint $table): void {
            $table->unsignedBigInteger('contribution_pk');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('torrent_id');
            $table->primary(['contribution_pk', 'torrent_id'], 'torrent_source_resource_key');
            $table->index(['user_id', 'torrent_id'], 'torrent_source_resource_user');
        });
        Schema::create('torrent_source_independent', function (Blueprint $table): void {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('torrent_id');
            $table->boolean('independent_support');
            $table->primary(['user_id', 'torrent_id'], 'torrent_source_independent_key');
        });
        Schema::create('torrent_source_operations', function (Blueprint $table): void {
            $table->unsignedBigInteger('caller_id');
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->unsignedBigInteger('contribution_pk');
            $table->timestamp('created_at');
            $table->primary(['caller_id', 'idempotency_key_hash'], 'torrent_source_operation_key');
        });
    }

    public function down(): void
    {
        if (DB::table('torrent_source_contributions')->where('desired', 'active')->exists()) {
            throw new RuntimeException('Active torrent contributions must be reconciled before removing their ledger.');
        }
        Schema::dropIfExists('torrent_source_operations');
        Schema::dropIfExists('torrent_source_independent');
        Schema::dropIfExists('torrent_source_resources');
        Schema::dropIfExists('torrent_source_contributions');
        Schema::dropIfExists('torrent_source_ledger');
    }
};
