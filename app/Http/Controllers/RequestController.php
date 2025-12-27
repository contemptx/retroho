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

use App\Http\Requests\StoreTorrentRequestRequest;
use App\Http\Requests\UpdateTorrentRequestRequest;
use App\Models\Category;
use App\Models\TorrentRequest;
use App\Models\Type;
use Illuminate\Http\Request;
use Exception;

class RequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('requests.index');
    }

    /**
     * Display The Torrent Request.
     */
    public function show(Request $request, TorrentRequest $torrentRequest): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('requests.show', [
            'torrentRequest' => $torrentRequest->load(['category', 'claim' => ['user'], 'torrent']),
            'user'           => $request->user(),
            'canEdit'        => $request->user()->group->is_modo || TorrentRequest::query()
                ->whereNull('claimed')
                ->whereNull('filled_by')
                ->whereKey($torrentRequest)
                ->exists(),
        ]);
    }

    /**
     * Torrent Request Add Form.
     */
    public function create(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('requests.create', [
            'categories' => Category::orderBy('position')
                ->get()
                ->toArray(),
            'types'       => Type::orderBy('position')->get(),
            'user'        => $request->user(),
            'category_id' => $request->category_id ?? Category::first('id')->id,
            'title'       => urldecode((string) $request->title),
        ]);
    }

    /**
     * Store A New Torrent Request.
     */
    public function store(StoreTorrentRequestRequest $request): \Illuminate\Http\RedirectResponse
    {
        TorrentRequest::create(['user_id' => $request->user()->id, 'votes' => 1] + $request->validated());

        return to_route('requests.index')
            ->withSuccess(trans('request.added-request'));
    }

    /**
     * Torrent Request Edit Form.
     */
    public function edit(Request $request, TorrentRequest $torrentRequest): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('requests.edit', [
            'categories'     => Category::orderBy('position')->get(),
            'types'          => Type::orderBy('position')->get(),
            'user'           => $request->user(),
            'torrentRequest' => $torrentRequest,
        ]);
    }

    /**
     * Edit A Torrent Request.
     */
    public function update(UpdateTorrentRequestRequest $request, TorrentRequest $torrentRequest): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            (
                $user->group->is_modo
                || (
                    $user->id === $torrentRequest->user_id
                    && TorrentRequest::query()
                        ->whereNull('claimed')
                        ->whereNull('filled_by')
                        ->whereKey($torrentRequest)
                        ->exists()
                )
            ),
            403
        );

        return to_route('requests.show', ['torrentRequest' => $torrentRequest])
            ->withSuccess(trans('request.edited-request'));
    }

    /**
     * Delete A Torrent Request.
     *
     * @throws Exception
     */
    public function destroy(Request $request, TorrentRequest $torrentRequest): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->group->is_modo || $torrentRequest->user_id === $user->id, 403);

        $torrentRequest->delete();

        return to_route('requests.index')
            ->withSuccess(sprintf(trans('request.deleted'), $torrentRequest->name));
    }
}
