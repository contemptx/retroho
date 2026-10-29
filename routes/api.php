<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
if (config('unit3d.proxy_scheme')) {
    URL::forceScheme(config('unit3d.proxy_scheme'));
}

if (config('unit3d.root_url_override')) {
    URL::forceRootUrl(config('unit3d.root_url_override'));
}

// Torrents System
Route::middleware(['auth:api', 'banned'])->prefix('torrents')->group(function (): void {
    Route::get('/', [App\Http\Controllers\API\TorrentController::class, 'index'])->name('api.torrents.index');
    Route::get('/filter', [App\Http\Controllers\API\TorrentController::class, 'filter']);
    Route::get('/{id}', [App\Http\Controllers\API\TorrentController::class, 'show'])->where('id', '[0-9]+');
    Route::post('/upload', [App\Http\Controllers\API\TorrentController::class, 'store']);
});

// User Management API
Route::middleware(['auth:api', 'banned'])->group(function (): void {
    // Get authenticated user info
    Route::get('/user', [App\Http\Controllers\API\UserController::class, 'show']);
    
    // Create new user account
    Route::post('/user', [App\Http\Controllers\API\UserController::class, 'store']);
    
    // Attach torrents to a user
    Route::post('/user/torrents', [App\Http\Controllers\API\UserController::class, 'attachTorrents']);
    
    // Verify user account exists and get status
    Route::post('/user/verify', [App\Http\Controllers\API\UserController::class, 'verify']);
    
    // Verify torrent access for a user
    Route::post('/user/verify-torrents', [App\Http\Controllers\API\UserController::class, 'verifyTorrents']);
    
    // Update user password (set custom or generate random)
    Route::post('/user/update-password', [App\Http\Controllers\API\UserController::class, 'updatePassword']);
    
    // Activate user account (move from validating group to active member group)
    Route::post('/user/activate', [App\Http\Controllers\API\UserController::class, 'activate']);
    
    // Generate auto-login token for a user
    Route::post('/user/auto-login-token', [App\Http\Controllers\API\UserController::class, 'generateAutoLoginToken']);
    
    // Remove all torrents from a user
    Route::post('/user/remove-all-torrents', [App\Http\Controllers\API\UserController::class, 'removeAllTorrents']);
    
    // Restore a soft-deleted user account
    Route::post('/user/restore', [App\Http\Controllers\API\UserController::class, 'restore']);
    
    // Enable downloads for a user (fix can_download = false)
    Route::post('/user/enable-downloads', [App\Http\Controllers\API\UserController::class, 'enableDownloads']);
});
