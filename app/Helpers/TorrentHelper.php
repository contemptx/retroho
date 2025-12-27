<?php

declare(strict_types=1);

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

namespace App\Helpers;

use App\Models\AutomaticTorrentFreeleech;
use App\Models\Scopes\ApprovedScope;
use App\Models\Torrent;
use App\Notifications\NewUpload;
use App\Services\Unit3dAnnounce;
use Illuminate\Support\Carbon;

class TorrentHelper
{
    public static function approveHelper(int $id): void
    {
        $appurl = config('app.url');

        $torrent = Torrent::with('user')->withoutGlobalScope(ApprovedScope::class)->findOrFail($id);
        $torrent->created_at = Carbon::now();
        $torrent->bumped_at = Carbon::now();
        $torrent->status = Torrent::APPROVED;
        $torrent->moderated_at = now();
        $torrent->moderated_by = (int) auth()->id();

        if (!$torrent->free) {
            $autoFreeleechs = AutomaticTorrentFreeleech::query()
                ->orderBy('position')
                ->where(fn ($query) => $query->whereNull('category_id')->orWhere('category_id', '=', $torrent->category_id))
                ->where(fn ($query) => $query->whereNull('type_id')->orWhere('type_id', '=', $torrent->type_id))
                ->where(fn ($query) => $query->whereNull('resolution_id')->orWhere('resolution_id', '=', $torrent->resolution_id))
                ->where(fn ($query) => $query->whereNull('size')->orWhere('size', '<', $torrent->size))
                ->get();

            foreach ($autoFreeleechs as $autoFreeleech) {
                if ($autoFreeleech->name_regex === null || preg_match($autoFreeleech->name_regex, $torrent->name)) {
                    $torrent->free = $autoFreeleech->freeleech_percentage;

                    break;
                }
            }
        }

        $torrent->save();

        $uploader = $torrent->user;

        if ($torrent->anon == 0 && $uploader !== null) {
            foreach ($uploader->followers()->get() as $follower) {
                if ($follower->acceptsNotification($uploader, $follower, 'following', 'show_following_upload')) {
                    $follower->notify(new NewUpload('follower', $torrent));
                }
            }
        }

        cache()->forget('announce-torrents:by-infohash:'.$torrent->info_hash);

        Unit3dAnnounce::addTorrent($torrent);
    }
}
