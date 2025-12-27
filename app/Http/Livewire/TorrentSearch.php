<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.tx
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Http\Livewire;

use App\DTO\TorrentSearchFiltersDTO;
use App\Models\Category;
use App\Models\Torrent;
use App\Models\Type;
use App\Traits\CastLivewireProperties;
use App\Traits\LivewireSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Closure;

class TorrentSearch extends Component
{
    use CastLivewireProperties;
    use LivewireSort;
    use WithPagination;

    #TODO: Update URL attributes once Livewire 3 fixes upstream bug. See: https://github.com/livewire/livewire/discussions/7746

    #[Url(history: true)]
    public string $name = '';

    #[Url(history: true)]
    public string $description = '';

    #[Url(history: true)]
    public string $uploader = '';

    #[Url(history: true)]
    public ?int $minSize = null;

    #[Url(history: true)]
    public int $minSizeMultiplier = 1;

    #[Url(history: true)]
    public ?int $maxSize = null;

    #[Url(history: true)]
    public int $maxSizeMultiplier = 1;

    /**
     * @var array<int>
     */
    #[Url(history: true)]
    public array $categoryIds = [];

    /**
     * @var array<int>
     */
    #[Url(history: true)]
    public array $typeIds = [];

    /**
     * @var string[]
     */
    #[Url(history: true)]
    public array $free = [];

    #[Url(history: true)]
    public bool $doubleup = false;

    #[Url(history: true)]
    public bool $featured = false;

    #[Url(history: true)]
    public bool $highspeed = false;

    #[Url(history: true)]
    public bool $bookmarked = false;

    #[Url(history: true)]
    public bool $alive = false;

    #[Url(history: true)]
    public bool $dying = false;

    #[Url(history: true)]
    public bool $dead = false;

    #[Url(history: true)]
    public bool $graveyard = false;

    #[Url(history: true)]
    public bool $notDownloaded = false;

    #[Url(history: true)]
    public bool $downloaded = false;

    #[Url(history: true)]
    public bool $seeding = false;

    #[Url(history: true)]
    public bool $leeching = false;

    #[Url(history: true)]
    public bool $incomplete = false;

    #[Url(history: true)]
    public int $perPage = 25;

    #[Url(except: 'bumped_at')]
    public string $sortField = 'bumped_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    #[Url(except: 'list')]
    public string $view = 'list';

    final public function mount(Request $request): void
    {
        if ($request->missing('sortField')) {
            $this->sortField = auth()->user()->settings?->torrent_sort_field ?? 'bumped_at';
        }

        if ($request->missing('view')) {
            auth()->user()->settings?->torrent_layout;
            $this->view = 'list';
        }
    }

    final public function updating(string $field, mixed &$value): void
    {
        $this->castLivewireProperties($field, $value);
    }

    final public function updatingName(): void
    {
        $this->resetPage();
    }

    final public function updatedView(): void
    {
        $this->perPage = 25;
    }

    #[Computed]
    final public function personalFreeleech(): bool
    {
        return cache()->get('personal_freeleech:'.auth()->id()) ?? false;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    #[Computed(cache: true, seconds: 3600)]
    final public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::query()->orderBy('position')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Type>
     */
    #[Computed(cache: true, seconds: 3600)]
    final public function types(): \Illuminate\Database\Eloquent\Collection
    {
        return Type::query()->orderBy('position')->get();
    }

    /**
     * @return Closure(Builder<Torrent>): Builder<Torrent>
     */
    final public function filters(): Closure
    {
        return (new TorrentSearchFiltersDTO(
            name: $this->name,
            description: $this->description,
            uploader: $this->uploader,
            minSize: $this->minSize === null ? null : $this->minSize * $this->minSizeMultiplier,
            maxSize: $this->maxSize === null ? null : $this->maxSize * $this->maxSizeMultiplier,
            categoryIds: $this->categoryIds,
            typeIds: $this->typeIds,
            free: $this->free,
            doubleup: $this->doubleup,
            featured: $this->featured,
            highspeed: $this->highspeed,
            alive: $this->alive,
            dying: $this->dying,
            dead: $this->dead,
            graveyard: $this->graveyard,
            userBookmarked: $this->bookmarked,
            userDownloaded: match (true) {
                $this->downloaded    => true,
                $this->notDownloaded => false,
                default              => null,
            },
            userSeeder: match (true) {
                $this->seeding  => true,
                $this->leeching => false,
                default         => null,
            },
            userActive: match (true) {
                $this->seeding  => true,
                $this->leeching => true,
                default         => null,
            },
        ))->toSqlQueryBuilder();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<Torrent>
     */
    #[Computed]
    final public function torrents(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $user = auth()->user();

        // Whitelist which columns are allowed to be ordered by
        if (!\in_array($this->sortField, [
            'name',
            'size',
            'seeders',
            'leechers',
            'times_completed',
            'created_at',
            'bumped_at'
        ])) {
            $this->reset('sortField');
        }

        return Torrent::with(['user:id,username,group_id', 'user.group', 'category', 'type'])
            ->withCount([
                'thanks',
                'comments',
                'seeds'   => fn ($query) => $query->where('active', '=', true)->where('visible', '=', true),
                'leeches' => fn ($query) => $query->where('active', '=', true)->where('visible', '=', true),
            ])
            ->withExists([
                'bookmarks'          => fn ($query) => $query->where('user_id', '=', $user->id),
                'freeleechTokens'    => fn ($query) => $query->where('user_id', '=', $user->id),
                'history as seeding' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 1)
                    ->where('seeder', '=', 1),
                'history as leeching' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 1)
                    ->where('seeder', '=', 0),
                'history as not_completed' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 0)
                    ->where('seeder', '=', 0)
                    ->whereNull('completed_at'),
                'history as not_seeding' => fn ($query) => $query->where('user_id', '=', $user->id)
                    ->where('active', '=', 0)
                    ->where(
                        fn ($query) => $query
                            ->where('seeder', '=', 1)
                            ->orWhereNotNull('completed_at')
                    ),
            ])
            ->where($this->filters())
            ->latest('sticky')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(min($this->perPage, 100));
    }

    final public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.torrent-search', [
            'categories'        => $this->categories,
            'types'             => $this->types,
            'user'              => auth()->user()->load('group'),
            'personalFreeleech' => $this->personalFreeleech,
            'torrents'          => $this->torrents,
        ]);
    }
}
