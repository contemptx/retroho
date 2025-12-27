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

namespace App\Http\Controllers;

use App\Models\FeaturedTorrent;
use App\Models\FreeleechToken;
use App\Models\Scopes\ApprovedScope;
use App\Models\Torrent;
use App\Services\Unit3dAnnounce;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TorrentBuffController extends Controller
{
    /**
     * Bump A Torrent.
     */
    public function bumpTorrent(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->group->is_modo || $user->group->is_internal, 403);
        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);
        $torrent->bumped_at = Carbon::now();
        $torrent->save();

        return to_route('torrents.show', ['id' => $torrent->id])
            ->withSuccess('Torrent Has Been Bumped To The Top Successfully!');
    }

    /**
     * Sticky A Torrent.
     */
    public function sticky(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->group->is_modo || $user->group->is_internal, 403);
        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);
        $torrent->sticky = !$torrent->sticky;
        $torrent->save();

        return to_route('torrents.show', ['id' => $torrent->id])
            ->withSuccess('Torrent Sticky Status Has Been Adjusted!');
    }

    /**
     * Freeleech A Torrent (1% to 100% Free).
     */
    public function grantFL(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->group->is_modo || $user->group->is_internal, 403);
        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);
        $torrentUrl = href_torrent($torrent);

        $request->validate([
            'freeleech' => 'numeric|min:0|max:100',
            'fl_until'  => 'nullable|numeric'
        ]);

        $torrent->free = $request->freeleech;
        $torrent->save();

        cache()->forget('announce-torrents:by-infohash:'.$torrent->info_hash);

        Unit3dAnnounce::addTorrent($torrent);

        return to_route('torrents.show', ['id' => $torrent->id])
            ->withSuccess('Torrent FL Has Been Adjusted!');
    }

    /**
     * Feature A Torrent.
     */
    public function grantFeatured(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->group->is_modo || $user->group->is_internal, 403);
        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);

        if ($torrent->featured === false) {
            $torrent->featured = true;
            $torrent->save();

            Unit3dAnnounce::addFeaturedTorrent($torrent->id);

            $featured = new FeaturedTorrent();
            $featured->user_id = $user->id;
            $featured->torrent_id = $torrent->id;
            $featured->save();

            cache()->forget('featured-torrent-ids');

            return to_route('torrents.show', ['id' => $torrent->id])
                ->withSuccess('Torrent Is Now Featured!');
        }

        return to_route('torrents.show', ['id' => $torrent->id])
            ->withErrors('Torrent Is Already Featured!');
    }

    /**
     * UnFeature A Torrent.
     */
    public function revokeFeatured(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->group->is_modo, 403);

        $featured_torrent = FeaturedTorrent::where('torrent_id', '=', $id)->sole();

        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);
        $torrent->featured = false;
        $torrent->save();

        Unit3dAnnounce::removeFeaturedTorrent($torrent->id);

        $featured_torrent->delete();

        cache()->forget('featured-torrent-ids');

        return to_route('torrents.show', ['id' => $torrent->id])
            ->withSuccess('Revoked featured from Torrent!');
    }

    /**
     * Double Upload A Torrent.
     */
    public function grantDoubleUp(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->group->is_modo || $user->group->is_internal, 403);
        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);
        $torrentUrl = href_torrent($torrent);

        if (!$torrent->doubleup) {
            $torrent->doubleup = true;
            $du_until = $request->input('du_until');

            if ($du_until !== null) {
                $torrent->du_until = Carbon::now()->addDays($request->integer('du_until'));
            }
        } else {
            $torrent->doubleup = false;
        }

        $torrent->save();

        cache()->forget('announce-torrents:by-infohash:'.$torrent->info_hash);

        Unit3dAnnounce::addTorrent($torrent);

        return to_route('torrents.show', ['id' => $torrent->id])
            ->withSuccess('Torrent DoubleUpload Has Been Adjusted!');
    }

    /**
     * Use Freeleech Token On A Torrent.
     */
    public function freeleechToken(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);

        $activeToken = cache()->get('freeleech_token:'.$user->id.':'.$torrent->id);

        if ($user->fl_tokens >= 1 && !$activeToken) {
            $freeleechToken = new FreeleechToken();
            $freeleechToken->user_id = $user->id;
            $freeleechToken->torrent_id = $torrent->id;
            $freeleechToken->save();

            Unit3dAnnounce::addFreeleechToken($user->id, $torrent->id);

            $user->fl_tokens -= '1';
            $user->save();

            cache()->put('freeleech_token:'.$user->id.':'.$torrent->id, true);

            return to_route('torrents.show', ['id' => $torrent->id])
                ->withSuccess('You Have Successfully Activated A Freeleech Token For This Torrent!');
        }

        return to_route('torrents.show', ['id' => $torrent->id])
            ->withErrors('You Dont Have Enough Freeleech Tokens Or Already Have One Activated On This Torrent.');
    }
}
