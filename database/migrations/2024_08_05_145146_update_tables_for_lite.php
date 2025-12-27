<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('requests', function (Blueprint $table): void {
            $table->dropColumn(['imdb', 'tvdb', 'tmdb', 'mal', 'igdb', 'bounty', 'votes', 'resolution_id']);
        });

        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropColumn(['imdb', 'tvdb', 'tmdb', 'mal', 'igdb', 'mediainfo', 'bdinfo', 'season_number', 'episode_number', 'stream', 'sd', 'internal', 'resolution_id', 'distributor_id', 'region_id']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['no_meta', 'music_meta', 'game_meta', 'tv_meta', 'movie_meta']);
        });

        Schema::table('achievement_details', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('achievement_progress', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('bon_exchanges', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('bon_transactions', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('bots', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('chat_statuses', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('chatrooms', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('collection_movie', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('collections', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('company_movie', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('company_tv', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('credits', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('distributors', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('episodes', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('genre_movie', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('genre_tv', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('genres', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('gifts', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('internal_user', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('internals', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('keywords', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('media_languages', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('movies', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('network_tv', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('networks', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('occupations', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('playlist_torrents', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('playlists', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('post_tips', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('recommendations', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('regions', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('request_bounty', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('resolutions', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('seasons', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('subtitles', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('torrent_tips', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('tv', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('user_audibles', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('user_echoes', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::table('wishes', function (Blueprint $table): void {
            $table->drop();
        });

        Schema::enableForeignKeyConstraints();
    }
};
