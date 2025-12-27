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

namespace App\DTO;

use App\Models\User;
use App\Traits\TorrentFilter;
use Illuminate\Database\Eloquent\Builder;
use Closure;

readonly class TorrentSearchFiltersDTO
{
    use TorrentFilter;

    public function __construct(
        private ?User $user = null,
        private string $name = '',
        private string $description = '',
        private string $uploader = '',
        private ?int $minSize = null,
        private ?int $maxSize = null,
        /** @var array<mixed> */
        private array $categoryIds = [],
        /** @var array<mixed> */
        private array $typeIds = [],
        /** @var array<mixed> */
        private array $free = [],
        private bool $doubleup = false,
        private bool $featured = false,
        private bool $highspeed = false,
        private bool $alive = false,
        private bool $dying = false,
        private bool $dead = false,
        private string $filename = '',
        private bool $graveyard = false,
        private bool $userBookmarked = false,
        private ?bool $userDownloaded = null,
        private ?bool $userSeeder = null,
        private ?bool $userActive = null,
    ) {
    }

    /**
     * @return Closure(Builder<\App\Models\Torrent>): Builder<\App\Models\Torrent>
     */
    final public function toSqlQueryBuilder(): Closure
    {
        $user = $this->user ?? auth()->user();
        $isRegexAllowed = $user->group->is_modo || $user->group->is_editor;
        $isRegex = fn ($field) => $isRegexAllowed
            && \strlen((string) $field) > 2
            && $field[0] === '/'
            && $field[-1] === '/'
            && @preg_match($field, 'Validate regex') !== false;

        return fn ($query) => $query
            ->when($this->name !== '', fn ($query) => $query->ofName($this->name, $isRegex($this->name)))
            ->when($this->description !== '', fn ($query) => $query->ofDescription($this->description, $isRegex($this->description)))
            ->when($this->uploader !== '', fn ($query) => $query->ofUploader($this->uploader))
            ->when($this->minSize !== null, fn ($query) => $query->ofSizeGreaterOrEqualto($this->minSize))
            ->when($this->maxSize !== null, fn ($query) => $query->ofSizeLesserOrEqualTo($this->maxSize))
            ->when($this->categoryIds !== [], fn ($query) => $query->ofCategory($this->categoryIds))
            ->when($this->typeIds !== [], fn ($query) => $query->ofType($this->typeIds))
            ->when($this->free !== [], fn ($query) => $query->ofFreeleech($this->free))
            ->when($this->filename !== '', fn ($query) => $query->ofFilename($this->filename))
            ->when($this->doubleup, fn ($query) => $query->doubleup())
            ->when($this->featured, fn ($query) => $query->featured())
            ->when($this->highspeed, fn ($query) => $query->highspeed())
            ->when($this->userBookmarked, fn ($query) => $query->bookmarkedBy($user))
            ->when($this->alive, fn ($query) => $query->alive())
            ->when($this->dying, fn ($query) => $query->dying())
            ->when($this->dead, fn ($query) => $query->dead())
            ->when($this->graveyard, fn ($query) => $query->graveyard())
            ->when($this->userDownloaded === false, fn ($query) => $query->notDownloadedBy($user))
            ->when($this->userDownloaded === true, fn ($query) => $query->downloadedBy($user))
            ->when($this->userSeeder === true && $this->userActive === true, fn ($query) => $query->seededBy($user))
            ->when($this->userSeeder === false && $this->userActive === true, fn ($query) => $query->leechedBy($user))
            ->when($this->userSeeder === false && $this->userActive === false, fn ($query) => $query->uncompletedBy($user));
    }
}
