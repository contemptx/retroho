<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $table->binary('info_hash_v2', length: 32, fixed: true)->nullable()->after('info_hash');
            $table->unsignedTinyInteger('torrent_version')->default(1)->after('info_hash_v2');
            $table->index('info_hash_v2');
        });
    }

    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropIndex(['info_hash_v2']);
            $table->dropColumn(['info_hash_v2', 'torrent_version']);
        });
    }
};
