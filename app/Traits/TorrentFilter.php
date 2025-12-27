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
 * @author     Roardom <roardom@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Traits;

use App\Models\Bookmark;
use App\Models\Torrent;
use App\Models\TorrentRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait TorrentFilter
{
    /**
     * @param Builder<Torrent|TorrentRequest> $query
     */
    public function scopeOfName(Builder $query, string $name, bool $isRegex = false): void
    {
        $query->when(
            $isRegex,
            fn ($query) => $query->where('name', 'REGEXP', substr($name, 1, -1)),
            fn ($query) => $query->where('name', 'LIKE', '%'.str_replace(' ', '%', $name).'%')
        );
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeOfDescription(Builder $query, string $description, bool $isRegex = false): void
    {
        $query->when(
            $isRegex,
            fn ($query) => $query->where('description', 'REGEXP', substr($description, 1, -1)),
            fn ($query) => $query->where('description', 'LIKE', '%'.$description.'%')
        );
    }

    /**
     * @param Builder<Torrent|TorrentRequest> $query
     */
    public function scopeOfUploader(Builder $query, string $username, User $authenticatedUser = null): void
    {
        $authenticatedUser ??= auth()->user();

        $query
            ->whereRelation('user', 'username', '=', $username)
            ->when(
                $authenticatedUser === null,
                fn ($query) => $query->where('anon', '=', false),
                fn ($query) => $query->when(
                    !$authenticatedUser->group->is_modo,
                    fn ($query) => $query->where(fn ($query) => $query->where('anon', '=', false)->orWhere('user_id', '=', $authenticatedUser->id))
                )
            );
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeOfSizeGreaterOrEqualTo(Builder $query, int $size): void
    {
        $query->where('size', '>=', $size);
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeOfSizeLesserOrEqualTo(Builder $query, int $size): void
    {
        $query->where('size', '<=', $size);
    }

    /**
     * @param Builder<Torrent|TorrentRequest> $query
     * @param array<int>                      $categories
     */
    public function scopeOfCategory(Builder $query, array $categories): void
    {
        $query->whereIntegerInRaw('category_id', $categories);
    }

    /**
     * @param Builder<Torrent|TorrentRequest> $query
     * @param array<int>                      $types
     */
    public function scopeOfType(Builder $query, array $types): void
    {
        $query->whereIntegerInRaw('type_id', $types);
    }

    /**
     * @param Builder<Torrent> $query
     * @param int|array<int>   $free
     */
    public function scopeOfFreeleech(Builder $query, int|array $free): void
    {
        $query->when(
            config('other.freeleech'),
            fn ($query) => $query->whereBetween('free', [0, 100]),
            fn ($query) => $query->whereIntegerInRaw('free', (array) $free)
        );
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeDoubleup(Builder $query): void
    {
        $query->where('doubleup', '=', 1);
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeFeatured(Builder $query): void
    {
        $query->where('featured', '=', 1);
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeHighSpeed(Builder $query): void
    {
        $query->where('highspeed', '=', 1);
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeBookmarkedBy(Builder $query, User $user): void
    {
        $query->whereIn('id', Bookmark::select('torrent_id')->where('user_id', '=', $user->id));
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeAlive(Builder $query): void
    {
        $query->where('seeders', '>', 0);
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeDying(Builder $query): void
    {
        $query
            ->where('seeders', '=', 1)
            ->where('times_completed', '>=', 3);
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeDead(Builder $query): void
    {
        $query->where('seeders', '=', 0);
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeGraveyard(Builder $query): void
    {
        $query->where('seeders', '=', 0)->where('created_at', '<', Carbon::now()->subDays(30));
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeNotDownloadedBy(Builder $query, User $user): void
    {
        $query
            ->whereDoesntHave(
                'history',
                fn ($query) => $query
                    ->where('user_id', '=', $user->id)
            );
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeDownloadedBy(Builder $query, User $user): void
    {
        $query
            ->whereHas(
                'history',
                fn (Builder $query) => $query
                    ->where('user_id', '=', $user->id)
            );
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeSeededBy(Builder $query, User $user): void
    {
        $query
            ->whereHas(
                'history',
                fn ($query) => $query
                    ->where('user_id', '=', $user->id)
                    ->where('active', '=', 1)
                    ->where('seeder', '=', 1)
            );
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeLeechedby(Builder $query, User $user): void
    {
        $query
            ->whereHas(
                'history',
                fn ($query) => $query
                    ->where('user_id', '=', $user->id)
                    ->where('active', '=', 1)
                    ->where('seeder', '=', 0)
            );
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeUncompletedBy(Builder $query, User $user): void
    {
        $query
            ->whereHas(
                'history',
                fn ($query) => $query
                    ->where('user_id', '=', $user->id)
                    ->where('active', '=', 0)
                    ->where('seeder', '=', 0)
                    ->where('seedtime', '=', 0)
            );
    }

    /**
     * @param Builder<Torrent> $query
     */
    public function scopeOfFilename(Builder $query, string $filename): void
    {
        $query
            ->whereHas(
                'files',
                fn ($query) => $query
                    ->where('name', $filename)
            );
    }
}
