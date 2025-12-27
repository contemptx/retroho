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

use App\Helpers\Bencode;
use App\Helpers\TorrentHelper;
use App\Helpers\TorrentTools;
use App\Http\Requests\StoreTorrentRequest;
use App\Http\Requests\UpdateTorrentRequest;
use App\Models\Audit;
use App\Models\Category;
use App\Models\FeaturedTorrent;
use App\Models\History;
use App\Models\Scopes\ApprovedScope;
use App\Models\Torrent;
use App\Models\TorrentFile;
use App\Models\Type;
use App\Models\User;
use App\Services\Unit3dAnnounce;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Exception;

class TorrentController extends Controller
{
    /**
     * Display a listing of the Torrent resource.
     */
    public function index(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('torrent.index');
    }

    /**
     * Display The Torrent reasource.
     */
    public function show(Request $request, int|string $id): \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)
            ->with(['user', 'comments', 'category', 'type'])
            ->withCount([
                'bookmarks',
                'seeds'   => fn ($query) => $query->where('active', '=', true)->where('visible', '=', true),
                'leeches' => fn ($query) => $query->where('active', '=', true)->where('visible', '=', true),
            ])
            ->withExists(['bookmarks' => fn ($query) => $query->where('user_id', '=', $user->id)])
            ->withExists(['freeleechTokens' => fn ($query) => $query->where('user_id', '=', $user->id)])
            ->findOrFail($id);

        if (!$torrent->attachedUsers->contains($user) && !($torrent->user_id === $user->id) && !($user->group->is_owner || $user->group->is_internal)) {
            return redirect()->back()->withErrors('Your are not permitted to view this torrent!');
        }

        return view('torrent.show', [
            'torrent' => $torrent,
            'user'    => $user,
            'canEdit' => $user->group->is_modo
                || (
                    $user->id === $torrent->user_id
                    && (
                        $torrent->status !== Torrent::APPROVED
                        || now()->isBefore($torrent->created_at->addDay())
                    )
                ),
            'personal_freeleech' => cache()->get('personal_freeleech:'.$user->id),
            'featured'           => $torrent->featured === true ? FeaturedTorrent::where('torrent_id', '=', $id)->first() : null,
            'last_seed_activity' => History::where('torrent_id', '=', $torrent->id)->where('seeder', '=', 1)->latest('updated_at')->first(),
            'audits'             => Audit::with('user')->where('model_entry_id', '=', $torrent->id)->where('model_name', '=', 'Torrent')->latest()->get(),
        ]);
    }

    /**
     * Show the form for editing the specified Torrent resource.
     */
    public function edit(Request $request, int $id): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        $user = $request->user();
        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);

        abort_unless($user->group->is_modo || $user->id === $torrent->user_id, 403);

        return view('torrent.edit', [
            'categories' => Category::query()
                ->orderBy('position')
                ->get(),
            'types'   => Type::orderBy('position')->get()->mapWithKeys(fn ($type) => [$type['id'] => ['name' => $type['name']]]),
            'torrent' => $torrent,
            'user'    => $user,
        ]);
    }

    /**
     * Update the specified Torrent resource in storage.
     */
    public function update(UpdateTorrentRequest $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);

        abort_unless(
            $user->group->is_modo
            || (
                $user->id === $torrent->user_id
                && (
                    $torrent->status !== Torrent::APPROVED
                    || now()->isBefore($torrent->created_at->addDay())
                )
            ),
            403
        );

        $torrent->update($request->validated());

        return to_route('torrents.show', ['id' => $id])
            ->withSuccess('Successfully Edited!');
    }

    /**
     * Delete A Torrent.
     *
     * @throws Exception
     */
    public function destroy(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'message' => [
                'required',
                'min:1',
            ],
        ]);

        $user = $request->user();
        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);

        abort_unless($user->group->is_modo || ($user->id === $torrent->user_id && Carbon::now()->lt($torrent->created_at->addDay())), 403);

        foreach (History::where('torrent_id', '=', $torrent->id)->pluck('user_id') as $user_id) {
            User::sendSystemNotificationTo(
                userId: $user_id,
                subject: 'Torrent Deleted! - '.$torrent->name,
                message: '[b]Attention:[/b] Torrent '.$torrent->name." has been removed from our site. Our system shows that you were either the uploader, a seeder or a leecher on said torrent. We just wanted to let you know you can safely remove it from your client.\n\n[b]Removal Reason:[/b] ".$request->message,
            );
        }

        // Reset Requests
        $torrent->requests()->update([
            'torrent_id' => null,
        ]);

        //Remove Torrent related info
        cache()->forget(\sprintf('torrent:%s', $torrent->info_hash));

        $torrent->comments()->delete();
        $torrent->peers()->delete();
        $torrent->history()->delete();
        $torrent->hitrun()->delete();
        $torrent->files()->delete();
        $torrent->resurrections()->delete();
        $torrent->featured()->delete();

        $freeleechTokens = $torrent->freeleechTokens();

        foreach ($freeleechTokens->get() as $freeleechToken) {
            cache()->forget('freeleech_token:'.$freeleechToken->user_id.':'.$torrent->id);
        }

        $freeleechTokens->delete();

        cache()->forget('announce-torrents:by-infohash:'.$torrent->info_hash);

        Unit3dAnnounce::removeTorrent($torrent);

        $torrent->delete();

        return to_route('torrents.index')
            ->withSuccess('Torrent Has Been Deleted!');
    }

    /**
     * Torrent Upload Form.
     */
    public function create(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        $user = $request->user();
        abort_if($user->can_upload === false || $user->group->can_upload == 0, 403, __('torrent.cant-upload').' '.__('torrent.cant-upload-desc'));

        return view('torrent.create', [
            'categories'  => Category::orderBy('position')->get(),
            'types'       => Type::orderBy('position')->get(),
            'user'        => $request->user(),
            'category_id' => $request->category_id,
            'title'       => urldecode((string) $request->title),
        ]);
    }

    /**
     * Upload A Torrent.
     */
    public function store(StoreTorrentRequest $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        abort_if($user->can_upload === false || $user->group->can_upload == 0, 403, __('torrent.cant-upload').' '.__('torrent.cant-upload-desc'));

        abort_if(\is_array($request->file('torrent')), 400);

        abort_if(\is_array($request->file('nfo')), 400);

        $decodedTorrent = TorrentTools::normalizeTorrent($request->file('torrent'));

        $meta = Bencode::get_meta($decodedTorrent);

        $fileName = uniqid('', true).'.torrent'; // Generate a unique name
        file_put_contents(getcwd().'/files/torrents/'.$fileName, Bencode::bencode($decodedTorrent));

        $torrent = Torrent::create([
            'info_hash'    => Bencode::get_infohash($decodedTorrent),
            'file_name'    => $fileName,
            'num_file'     => $meta['count'],
            'folder'       => Bencode::get_name($decodedTorrent),
            'size'         => $meta['size'],
            'nfo'          => $request->hasFile('nfo') ? TorrentTools::getNfo($request->file('nfo')) : '',
            'user_id'      => $user->id,
            'moderated_at' => now(),
            'moderated_by' => User::SYSTEM_USER_ID,
        ] + $request->safe()->except(['torrent']));

        // Populate the status/seeders/leechers/times_completed fields for the external tracker
        $torrent->refresh();

        Unit3dAnnounce::addTorrent($torrent);

        if ($torrent->getAttribute('featured')) {
            Unit3dAnnounce::addFeaturedTorrent($torrent->id);
        }

        // Count and save the torrent number in this category
        $category = Category::findOrFail($request->integer('category_id'));
        $category->num_torrent = $category->torrents()->count();
        $category->save();

        // Backup the files contained in the torrent
        //$files = TorrentTools::getTorrentFiles($decodedTorrent);

        //foreach ($files as &$file) {
            //$file['torrent_id'] = $torrent->id;
        //}

        // Can't insert them all at once since some torrents have more files than mysql supports placeholders.
        // Divide by 3 since we're inserting 3 fields: name, size and torrent_id
        //foreach (collect($files)->chunk(intdiv(65_000, 3)) as $files) {
            //TorrentFile::insert($files->toArray());
        //}

        // check for trusted user and update torrent
        if ($user->group->is_trusted && !$request->boolean('mod_queue_opt_in')) {
            TorrentHelper::approveHelper($torrent->id);
        }

        return to_route('download_check', ['id' => $torrent->id])
            ->withSuccess('Your torrent file is ready to be downloaded and seeded!');
    }

    public function manageUsers(Torrent $torrent)
    {
        $users = User::orderBy('username')->get();

        return view('torrent.manage_users', compact('torrent', 'users'));
    }

    public function attachUser(Request $request, Torrent $torrent)
    {
        $user = User::find($request->user_id);

        if (!$torrent->attachedUsers->contains($user)) {
            $torrent->attachedUsers()->attach($user);
            return to_route('torrents.show', ['id' => $torrent->id])
                ->withSuccess('User Attached!');
        }

        return to_route('torrents.show', ['id' => $torrent->id])
            ->withErrors('User is already attached!');
    }

    public function detachUser(Request $request, Torrent $torrent)
    {
        $user = User::find($request->user_id);
        $torrent->attachedUsers()->detach($user);

        return to_route('torrents.show', ['id' => $torrent->id])
            ->withSuccess('User Detatched!');
    }
}
