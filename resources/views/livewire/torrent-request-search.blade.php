<div class="sidebar2 sidebar--inverse">
    <div>
        <section class="panelV2">
            <header class="panel__header">
                <h2 class="panel__heading">{{ __('request.requests') }}</h2>
                <div class="panel__actions">
                    <div class="panel__action">
                        <a
                            href="{{ route('requests.create') }}"
                            class="form__button form__button--text"
                        >
                            {{ __('request.add-request') }}
                        </a>
                    </div>
                </div>
            </header>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th wire:click="sortBy('name')" role="columnheader button">
                                {{ __('common.name') }}
                                @include('livewire.includes._sort-icon', ['field' => 'name'])
                            </th>
                            <th wire:click="sortBy('category_id')" role="columnheader button">
                                {{ __('common.category') }}
                                @include('livewire.includes._sort-icon', ['field' => 'category_id'])
                            </th>
                            <th wire:click="sortBy('type_id')" role="columnheader button">
                                {{ __('common.type') }}
                                @include('livewire.includes._sort-icon', ['field' => 'type_id'])
                            </th>
                            <th wire:click="sortBy('user_id')" role="columnheader button">
                                {{ __('common.author') }}
                                @include('livewire.includes._sort-icon', ['field' => 'user_id'])
                            </th>
                            <th>
                                <i
                                    class="{{ config('other.font-awesome') }} fa-comment-alt-lines"
                                ></i>
                            </th>
                            <th wire:click="sortBy('created_at')" role="columnheader button">
                                {{ __('common.created_at') }}
                                @include('livewire.includes._sort-icon', ['field' => 'created_at'])
                            </th>
                            <th>{{ __('common.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($torrentRequests as $torrentRequest)
                            <tr>
                                <td>
                                    <a
                                        href="{{ route('requests.show', ['torrentRequest' => $torrentRequest]) }}"
                                    >
                                        {{ $torrentRequest->name }}
                                    </a>
                                </td>
                                <td>{{ $torrentRequest->category->name }}</td>
                                <td>{{ $torrentRequest->type->name }}</td>
                                <td>
                                    <x-user_tag
                                        :user="$torrentRequest->user"
                                        :anon="$torrentRequest->anon"
                                    />
                                </td>
                                <td>{{ $torrentRequest->comments_count }}</td>
                                <td>
                                    <time
                                        datetime="{{ $torrentRequest->created_at }}"
                                        title="{{ $torrentRequest->created_at }}"
                                    >
                                        {{ $torrentRequest->created_at->diffForHumans() }}
                                    </time>
                                </td>
                                <td>
                                    @switch(true)
                                        @case($torrentRequest->claimed && $torrentRequest->torrent_id === null)
                                            <i class="fas fa-circle text-blue"></i>
                                            {{ __('request.claimed') }}

                                            @break
                                        @case($torrentRequest->torrent_id !== null && $torrentRequest->approved_by === null)
                                            <i class="fas fa-circle text-purple"></i>
                                            {{ __('request.pending') }}

                                            @break
                                        @case($torrentRequest->torrent_id === null)
                                            <i class="fas fa-circle text-red"></i>
                                            {{ __('request.unfilled') }}

                                            @break
                                        @default
                                            <i class="fas fa-circle text-green"></i>
                                            {{ __('request.filled') }}

                                            @break
                                    @endswitch
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">{{ __('common.no-result') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $torrentRequests->links('partials.pagination') }}
            </div>
        </section>
    </div>
    <aside>
        <section class="panelV2">
            <h2 class="panel__heading">{{ __('torrent.filters') }}</h2>
            <div class="panel__body">
                <form class="form">
                    <div class="form__group--horizontal">
                        <p class="form__group">
                            <input
                                id="name"
                                wire:model.live="name"
                                type="search"
                                class="form__text"
                                placeholder=" "
                            />
                            <label class="form__label form__label--floating" for="name">
                                {{ __('common.name') }}
                            </label>
                        </p>
                        <p class="form__group">
                            <input
                                id="requester"
                                wire:model.live="requestor"
                                class="form__text"
                                placeholder=" "
                            />
                            <label class="form__label form__label--floating" for="requester">
                                {{ __('common.author') }}
                            </label>
                        </p>
                    </div>
                    <div class="form__group--short-horizontal">
                        <div class="form__group">
                            <fieldset class="form__fieldset">
                                <legend class="form__legend">{{ __('torrent.category') }}</legend>
                                <div class="form__fieldset-checkbox-container">
                                    @foreach ($categories as $category)
                                        <p class="form__group">
                                            <label class="form__label">
                                                <input
                                                    class="form__checkbox"
                                                    type="checkbox"
                                                    value="{{ $category->id }}"
                                                    wire:model.live="categories"
                                                />
                                                {{ $category->name }}
                                            </label>
                                        </p>
                                    @endforeach
                                </div>
                            </fieldset>
                        </div>
                        <div class="form__group">
                            <fieldset class="form__fieldset">
                                <legend class="form__legend">{{ __('common.type') }}</legend>
                                <div class="form__fieldset-checkbox-container">
                                    @foreach ($types as $type)
                                        <p class="form__group">
                                            <label class="form__label">
                                                <input
                                                    class="form__checkbox"
                                                    type="checkbox"
                                                    value="{{ $type->id }}"
                                                    wire:model.live="types"
                                                />
                                                {{ $type->name }}
                                            </label>
                                        </p>
                                    @endforeach
                                </div>
                            </fieldset>
                        </div>
                        <div class="form__group">
                            <fieldset class="form__fieldset">
                                <legend class="form__legend">{{ __('common.status') }}</legend>
                                <div class="form__fieldset-checkbox-container">
                                    <p class="form__group">
                                        <label class="form__label">
                                            <input
                                                class="form__checkbox"
                                                type="checkbox"
                                                value="1"
                                                wire:model.live="unfilled"
                                            />
                                            {{ __('request.unfilled') }}
                                        </label>
                                    </p>
                                    <p class="form__group">
                                        <label class="form__label">
                                            <input
                                                class="form__checkbox"
                                                type="checkbox"
                                                value="1"
                                                wire:model.live="claimed"
                                            />
                                            {{ __('request.claimed') }}
                                        </label>
                                    </p>
                                    <p class="form__group">
                                        <label class="form__label">
                                            <input
                                                class="form__checkbox"
                                                type="checkbox"
                                                value="1"
                                                wire:model.live="pending"
                                            />
                                            {{ __('request.pending') }}
                                        </label>
                                    </p>
                                    <p class="form__group">
                                        <label class="form__label">
                                            <input
                                                class="form__checkbox"
                                                type="checkbox"
                                                value="1"
                                                wire:model.live="filled"
                                            />
                                            {{ __('request.filled') }}
                                        </label>
                                    </p>
                                </div>
                            </fieldset>
                        </div>
                        <div class="form__group">
                            <fieldset class="form__fieldset">
                                <legend class="form__legend">{{ __('common.extra') }}</legend>
                                <div class="form__fieldset-checkbox-container">
                                    <p class="form__group">
                                        <label class="form__label">
                                            <input
                                                class="form__checkbox"
                                                type="checkbox"
                                                value="1"
                                                wire:model.live="myRequests"
                                            />
                                            {{ __('request.my-requests') }}
                                        </label>
                                    </p>
                                    <p class="form__group">
                                        <label class="form__label">
                                            <input
                                                class="form__checkbox"
                                                type="checkbox"
                                                value="1"
                                                wire:model.live="myClaims"
                                            />
                                            {{ __('request.my-claims') }}
                                        </label>
                                    </p>
                                    <p class="form__group">
                                        <label class="form__label">
                                            <input
                                                class="form__checkbox"
                                                type="checkbox"
                                                value="1"
                                                wire:model.live="myVoted"
                                            />
                                            {{ __('request.my-voted') }}
                                        </label>
                                    </p>
                                    <p class="form__group">
                                        <label class="form__label">
                                            <input
                                                class="form__checkbox"
                                                type="checkbox"
                                                value="1"
                                                wire:model.live="myFilled"
                                            />
                                            {{ __('request.my-filled') }}
                                        </label>
                                    </p>
                                </div>
                            </fieldset>
                        </div>
                    </div>
                </form>
            </div>
        </section>
        <section class="panelV2">
            <h2 class="panel__heading">{{ __('stat.stats') }}</h2>
            <dl class="key-value">
                <div class="key-value__group">
                    <dt>{{ __('request.requests') }}:</dt>
                    <dd>{{ number_format($torrentRequestStat->total) }}</dd>
                </div>
                <div class="key-value__group">
                    <dt>{{ __('request.filled') }}:</dt>
                    <dd>{{ number_format($torrentRequestStat->filled) }}</dd>
                </div>
                <div class="key-value__group">
                    <dt>{{ __('request.unfilled') }}:</dt>
                    <dd>{{ number_format($torrentRequestStat->unfilled) }}</dd>
                </div>
            </dl>
        </section>
    </aside>
</div>
