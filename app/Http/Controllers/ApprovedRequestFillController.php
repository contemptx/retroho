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

use App\Models\TorrentRequest;
use App\Notifications\NewRequestFillApprove;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ApprovedRequestFillController extends Controller
{
    /**
     * Approve A Torrent Request.
     */
    public function store(Request $request, TorrentRequest $torrentRequest): \Illuminate\Http\RedirectResponse
    {
        abort_unless(($request->user()->id === $torrentRequest->user_id || $request->user()->group->is_modo) && $torrentRequest->approved_by === null, 403);

        $approver = $request->user();
        $filler = $torrentRequest->filler()->sole();

        $torrentRequest->update([
            'approved_by'   => $approver->id,
            'approved_when' => Carbon::now(),
        ]);

        if ($filler->acceptsNotification($approver, $filler, 'request', 'show_request_fill_approve')) {
            $filler->notify(new NewRequestFillApprove($torrentRequest));
        }

        return to_route('requests.show', ['torrentRequest' => $torrentRequest])
            ->withSuccess(sprintf(trans('request.approved-user'), $torrentRequest->name, $torrentRequest->filled_anon ? 'Anonymous' : $filler->username));
    }

    /**
     * Destroy A Torrent Request Fill.
     */
    public function destroy(Request $request, TorrentRequest $torrentRequest): \Illuminate\Http\RedirectResponse
    {
        abort_unless((bool) $request->user()->group->is_modo, 403);

        $filler = $torrentRequest->filler;

        $torrentRequest->update([
            'approved_by'   => null,
            'approved_when' => null,
        ]);

        return to_route('requests.show', ['torrentRequest' => $torrentRequest])
            ->withSuccess(trans('request.request-reset'));
    }
}
