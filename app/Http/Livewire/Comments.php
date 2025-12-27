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

namespace App\Http\Livewire;

use App\Models\Article;
use App\Models\Ticket;
use App\Models\Torrent;
use App\Models\TorrentRequest;
use App\Models\User;
use App\Notifications\NewComment;
use App\Notifications\NewCommentTag;
use App\Traits\CastLivewireProperties;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Comments extends Component
{
    use CastLivewireProperties;

    use WithPagination;

    public ?User $user;

    public null|Article|Ticket|Torrent|TorrentRequest $model;

    public bool $anon = false;

    public int $perPage = 10;

    /**
     * @var array<string, string>
     */
    protected $listeners = [
        'refresh' => '$refresh',
    ];

    #[Validate('required')]
    public string $newCommentState = '';

    /**
     * @var array<string, string>
     */
    protected $validationAttributes = [
        'newCommentState' => 'comment',
    ];

    final public function mount(): void
    {
        $this->user = auth()->user();
    }

    final public function updating(string $field, mixed &$value): void
    {
        $this->castLivewireProperties($field, $value);
    }

    /**
     * @return array<string>
     */
    final public function taggedUsers(): array
    {
        preg_match_all('/@([\w\-]+)/', $this->newCommentState, $matches);

        return $matches[1];
    }

    final public function loadMore(): void
    {
        $this->perPage += 10;
    }

    /// TODO: Find a better data structure to avoid this mess of exception cases
    final public function postComment(): void
    {
        // Authorization
        abort_if(!$this->model instanceof Ticket && $this->user->can_comment === false, 403, __('comment.rights-revoked'));

        abort_if($this->model instanceof Torrent && $this->model->status !== Torrent::APPROVED, 403, __('comment.torrent-status'));

        // Validation
        $this->validate();

        $comment = $this->model->comments()->create([
            'content' => $this->newCommentState,
            'user_id' => auth()->id(),
            'anon'    => $this->anon,
        ]);

        // New Comment Notification
        switch (true) {
            case $this->model instanceof Ticket:
                $ticket = $this->model;

                if ($this->user->id !== $ticket->staff_id && $ticket->staff_id !== null) {
                    User::find($ticket->staff_id)?->notify(new NewComment($this->model, $comment));
                }

                if ($this->user->id !== $ticket->user_id) {
                    User::find($ticket->user_id)?->notify(new NewComment($this->model, $comment));
                }

                break;
            case $this->model instanceof Article:
            case $this->model instanceof TorrentRequest:
            case $this->model instanceof Torrent:
                if ($this->user->id !== $this->model->user_id) {
                    User::find($this->model->user_id)?->notify(new NewComment($this->model, $comment));
                }

                break;
        }

        // User Tagged Notification
        $users = User::whereIn('username', $this->taggedUsers())->get();
        Notification::sendNow($users, new NewCommentTag($this->model, $comment));

        $this->reset('newCommentState');

        $this->gotoPage(1);
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<\App\Models\Comment>
     */
    #[Computed]
    final public function comments(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->model
            ->comments()
            ->with(['user:id,username,group_id,image,title', 'user.group', 'children.user:id,username,group_id,image,title', 'children.user.group'])
            ->parent()
            ->latest()
            ->paginate($this->perPage);
    }

    final public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.comments', [
            'comments' => $this->comments,
        ]);
    }
}
