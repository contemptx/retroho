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

namespace App\Http\Controllers\API;

use App\DTO\TorrentSearchFiltersDTO;
use App\Helpers\Bencode;
use App\Helpers\TorrentHelper;
use App\Helpers\TorrentTools;
use App\Http\Resources\TorrentResource;
use App\Http\Resources\TorrentsResource;
use App\Models\Category;
use App\Models\FeaturedTorrent;
use App\Models\Torrent;
use App\Models\TorrentFile;
use App\Models\User;
use App\Services\Unit3dAnnounce;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TorrentController extends BaseController
{
    public int $perPage = 25;

    public string $sortField = 'bumped_at';

    public string $sortDirection = 'desc';

    /**
     * Display a listing of the resource.
     */
    public function index(): TorrentsResource
    {
        $torrents = cache()->remember('torrent-api-index', 300, function () {
            return Torrent::with(
                ['user:id,username', 'category', 'type', 'files']
            )
                ->latest('sticky')
                ->latest('bumped_at')
                ->cursorPaginate(25);
        });

        return new TorrentsResource($torrents);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        abort_if($user->can_upload === false || $user->group->can_upload == 0, 403, __('torrent.cant-upload').' '.__('torrent.cant-upload-desc'));

        $requestFile = $request->file('torrent');

        if (!$request->hasFile('torrent')) {
            return $this->sendError('Validation Error.', 'You Must Provide A Torrent File For Upload!');
        }

        if ($requestFile->getError() !== 0 || $requestFile->getClientOriginalExtension() !== 'torrent') {
            return $this->sendError('Validation Error.', 'You Must Provide A Valid Torrent File For Upload!');
        }

        // Deplace and decode the torrent temporarily
        $decodedTorrent = TorrentTools::normalizeTorrent($requestFile);
        $infohash = Bencode::get_infohash($decodedTorrent);

        try {
            $meta = Bencode::get_meta($decodedTorrent);
        } catch (Exception) {
            return $this->sendError('Validation Error.', 'You Must Provide A Valid Torrent File For Upload!');
        }

        foreach (TorrentTools::getFilenameArray($decodedTorrent) as $name) {
            if (!TorrentTools::isValidFilename($name)) {
                return $this->sendError('Validation Error.', 'Invalid Filenames In Torrent Files!');
            }
        }

        $fileName = sprintf('%s.torrent', uniqid('', true)); // Generate a unique name
        Storage::disk('torrents')->put($fileName, Bencode::bencode($decodedTorrent));

        // Find the right category
        $category = Category::withCount('torrents')->findOrFail($request->integer('category_id'));

        // Create the torrent (DB)
        $torrent = app()->make(Torrent::class);
        $torrent->name = $request->input('name');
        $torrent->description = $request->input('description');
        $torrent->mediainfo = TorrentTools::anonymizeMediainfo($request->filled('mediainfo') ? $request->string('mediainfo') : null);
        $torrent->bdinfo = $request->input('bdinfo');
        $torrent->info_hash = $infohash;
        $torrent->file_name = $fileName;
        $torrent->num_file = $meta['count'];
        $torrent->folder = Bencode::get_name($decodedTorrent);
        $torrent->size = $meta['size'];
        $torrent->nfo = ($request->hasFile('nfo')) ? TorrentTools::getNfo($request->file('nfo')) : '';
        $torrent->category_id = $category->id;
        $torrent->type_id = $request->input('type_id');
        $torrent->user_id = $user->id;
        $torrent->anon = $request->input('anonymous');
        $torrent->stream = $request->input('stream');
        $torrent->sd = $request->input('sd');
        $torrent->internal = $user->group->is_modo || $user->group->is_internal ? ($request->input('internal') ?? 0) : 0;
        $torrent->featured = $user->group->is_modo || $user->group->is_internal ? ($request->input('featured') ?? false) : false;
        $torrent->doubleup = $user->group->is_modo || $user->group->is_internal ? ($request->input('doubleup') ?? 0) : 0;
        $du_until = $request->input('du_until');

        if (($user->group->is_modo || $user->group->is_internal) && isset($du_until)) {
            $torrent->du_until = Carbon::now()->addDays($request->integer('du_until'));
        }
        $torrent->free = $user->group->is_modo || $user->group->is_internal ? ($request->input('free') ?? 0) : 0;
        $fl_until = $request->input('fl_until');

        if (($user->group->is_modo || $user->group->is_internal) && isset($fl_until)) {
            $torrent->fl_until = Carbon::now()->addDays($request->integer('fl_until'));
        }
        $torrent->sticky = $user->group->is_modo || $user->group->is_internal ? ($request->input('sticky') ?? 0) : 0;
        $torrent->moderated_at = Carbon::now();
        $torrent->moderated_by = User::where('username', 'System')->first()->id; //System ID

        // Set freeleech and doubleup if featured
        if ($torrent->featured === true) {
            $torrent->free = 100;
            $torrent->doubleup = true;
        }

        // Validation
        $v = validator($torrent->toArray(), [
            'name' => [
                'required',
                Rule::unique('torrents')->whereNull('deleted_at'),
                'max:255',
            ],
            'description' => [
                'required',
            ],
            'info_hash' => [
                'required',
                Rule::unique('torrents')->whereNull('deleted_at'),
            ],
            'file_name' => [
                'required',
            ],
            'num_file' => [
                'required',
                'numeric',
            ],
            'size' => [
                'required',
            ],
            'category_id' => [
                'required',
                'exists:categories,id',
            ],
            'type_id' => [
                'required',
                'exists:types,id',
            ],
            'user_id' => [
                'required',
                'exists:users,id',
            ],
            'anon' => [
                'required',
            ],
            'stream' => [
                'required',
            ],
            'sd' => [
                'required',
            ],
            'internal' => [
                'required',
            ],
            'featured' => [
                'required',
            ],
            'free' => [
                'required',
                'between:0,100',
            ],
            'doubleup' => [
                'required',
            ],
            'sticky' => [
                'required',
            ],
        ]);

        if ($v->fails()) {
            if (Storage::disk('torrents')->exists($fileName)) {
                Storage::disk('torrents')->delete($fileName);
            }

            return $this->sendError('Validation Error.', $v->errors());
        }

        // Save The Torrent
        $torrent->save();

        // Populate the status/seeders/leechers/times_completed fields for the external tracker
        $torrent->refresh();

        Unit3dAnnounce::addTorrent($torrent);

        if ($torrent->getAttribute('featured')) {
            Unit3dAnnounce::addFeaturedTorrent($torrent->id);
        }

        // Set torrent to featured
        if ($torrent->getAttribute('featured')) {
            $featuredTorrent = new FeaturedTorrent();
            $featuredTorrent->user_id = $user->id;
            $featuredTorrent->torrent_id = $torrent->id;
            $featuredTorrent->save();
        }

        // Count and save the torrent number in this category
        $category->num_torrent = $category->torrents_count;
        $category->save();

        // Backup the files contained in the torrent
        $files = TorrentTools::getTorrentFiles($decodedTorrent);

        foreach ($files as &$file) {
            $file['torrent_id'] = $torrent->id;
        }

        // Can't insert them all at once since some torrents have more files than mysql supports placeholders.
        // Divide by 3 since we're inserting 3 fields: name, size and torrent_id
        foreach (collect($files)->chunk(intdiv(65_000, 3)) as $files) {
            TorrentFile::insert($files->toArray());
        }

        // check for trusted user and update torrent
        if ($user->group->is_trusted) {
            TorrentHelper::approveHelper($torrent->id);
        }

        return $this->sendResponse(route('torrent.download.rsskey', ['id' => $torrent->id, 'rsskey' => auth('api')->user()->rsskey]), 'Torrent uploaded successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): TorrentResource
    {
        $torrent = Torrent::with(['user:id,username', 'category', 'type', 'files'])
            ->findOrFail($id);

        TorrentResource::withoutWrapping();

        return new TorrentResource($torrent);
    }

    /**
     * Uses Input's To Put Together A Search.
     */
    public function filter(Request $request): TorrentsResource|\Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $isRegexAllowed = $user->group->is_modo;

        // Caching
        $url = $request->url();
        $queryParams = $request->query();

        // Don't cache the api_token so that multiple users can share the cache
        unset($queryParams['api_token']);
        $queryParams['isRegexAllowed'] = $isRegexAllowed;

        // Sorting query params by key (acts by reference)
        ksort($queryParams);

        // Transforming the query array to query string
        $queryString = http_build_query($queryParams);
        $cacheKey = $url.'?'.$queryString;

        $torrents = cache()->remember($cacheKey, 300, function () use ($request) {
            return Torrent::with(['user:id,username', 'category', 'type', 'files'])
                ->where((new TorrentSearchFiltersDTO(
                    name: $request->filled('name') ? $request->string('name')->toString() : '',
                    description: $request->filled('description') ? $request->string('description')->toString() : '',
                    uploader: $request->filled('uploader') ? $request->string('uploader')->toString() : '',
                    categoryIds: $request->filled('categories') ? array_map('intval', $request->categories) : [],
                    typeIds: $request->filled('types') ? array_map('intval', $request->types) : [],
                    free: $request->filled('free') ? array_map('intval', (array) $request->free) : [],
                    doubleup: $request->filled('doubleup'),
                    featured: $request->filled('featured'),
                    highspeed: $request->filled('highspeed'),
                    alive: $request->filled('alive'),
                    dying: $request->filled('dying'),
                    dead: $request->filled('dead'),
                    filename: $request->filled('file_name') ? $request->string('file_name')->toString() : '',
                ))->toSqlQueryBuilder())
                ->latest('sticky')
                ->orderBy($request->input('sortField') ?? $this->sortField, $request->input('sortDirection') ?? $this->sortDirection)
                ->cursorPaginate(min($request->input('perPage') ?? $this->perPage, 100));
        });

        if ($torrents !== null) {
            return new TorrentsResource($torrents);
        }

        return $this->sendResponse('404', 'No Torrents Found');
    }
}
