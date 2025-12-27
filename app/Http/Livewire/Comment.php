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
use Livewire\Component;

class Comment extends Component
{
    use CastLivewireProperties;

    public null|Article|Ticket|Torrent|TorrentRequest $model;

    public \App\Models\Comment $comment;

    public bool $anon = false;

    public ?User $user;

    /**
     * @var array<string, string>
     */
    protected $listeners = [
        'refresh' => '$refresh',
    ];

    public bool $isReplying = false;

    public string $replyState = '';

    public bool $isEditing = false;

    public string $editState = '';

    final public function mount(): void
    {
        $this->user = auth()->user();
    }

    final public function updating(string $field, mixed &$value): void
    {
        $this->castLivewireProperties($field, $value);
    }

    /**
     * @return list<string>
     */
    final public function taggedUsers(): array
    {
        preg_match_all('/@([\w\-]+)/', $this->editState, $matches);

        return $matches[1];
    }

    final public function updatedIsEditing(bool $isEditing): void
    {
        if (!$isEditing) {
            return;
        }

        $this->editState = $this->comment->content;
    }

    final public function editComment(): void
    {
        abort_unless(auth()->id() === $this->comment->user_id || auth()->user()->group->is_modo, 403);

        $this->validate([
            'editState' => 'required'
        ]);

        $this->comment->update([
            'content' => $this->editState,
        ]);

        $this->isEditing = false;
    }

    final public function deleteComment(): void
    {
        abort_unless((auth()->id() === $this->comment->user_id || auth()->user()->group->is_modo), 403, 'Permission Denied!');

        abort_if($this->comment->children()->exists(), 403);

        $this->comment->delete();
    }

    final public function postReply(): void
    {
        abort_if(!$this->model instanceof Ticket && auth()->user()->can_comment === false, 403, __('comment.rights-revoked'));

        abort_if($this->model instanceof Torrent && $this->model->status !== Torrent::APPROVED, 403, __('comment.torrent-status'));

        abort_if($this->comment->isChild(), 403);

        $this->validate([
            'replyState' => 'required',
            'anon'       => 'bool',
        ]);

        $reply = $this->comment->children()->create([
            'content'          => $this->replyState,
            'user_id'          => auth()->id(),
            'anon'             => $this->anon,
            'commentable_id'   => $this->model->id,
            'commentable_type' => \get_class($this->model),
        ]);

        // New Comment Notification
        switch (true) {
            case $this->model instanceof Ticket:
                $ticket = $this->model;

                if ($this->user->id !== $ticket->staff_id && $ticket->staff_id !== null) {
                    User::find($ticket->staff_id)->notify(new NewComment($this->model, $reply));
                }

                if ($this->user->id !== $ticket->user_id) {
                    User::find($ticket->user_id)->notify(new NewComment($this->model, $reply));
                }

                if (!\in_array($this->comment->user_id, [$ticket->staff_id, $ticket->user_id, $this->user->id])) {
                    User::find($this->comment->user_id)->notify(new NewComment($this->model, $reply));
                }

                break;
            case $this->model instanceof Article:
            case $this->model instanceof TorrentRequest:
            case $this->model instanceof Torrent:
                if ($this->user->id !== $this->model->user_id) {
                    User::find($this->model->user_id)?->notify(new NewComment($this->model, $reply));
                }

                break;
        }

        // User Tagged Notification
        $users = User::whereIn('username', $this->taggedUsers())->get();
        Notification::sendNow($users, new NewCommentTag($this->model, $reply));

        $this->reset('replyState', 'isReplying');

        $this->dispatch('refresh')->self();
    }

    final public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.comment');
    }
}
