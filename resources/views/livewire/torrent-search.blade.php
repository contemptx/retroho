<div class="page__torrents torrent-search__component">
<div wire:ignore>
<section class="panelV2 torrent-search__filters">
    <h2 class="panel__heading">Torrent Categories</h2>
    <div class="panel__body">
        <ul class="mediahub-card__list">
            @foreach ($categories as $category)
            <li class="mediahub-card__list-item">
                <a
                    href="{{ route('torrents.index', ['categoryIds' => [$category->id]]) }}"
                    class="mediahub-card"
                    style="padding: 22px 16px;"
                >
                    <h2 class="mediahub-card__heading" style="font-size: 16px;">{{ $category->name }}</h2>
                    <h3 class="mediahub-card__subheading">
                        {{ $category->torrents->count() }} {{ __('torrent.torrents') }}
                    </h3>
                </a>
            </li>
            @endforeach
        </ul>
    </div>
</section>
</div>
    <section class="panelV2 torrent-search__filters" x-data="toggle">
        <header class="panel__header">
            <h2 class="panel__heading">{{ __('common.search') }}</h2>
            <div class="panel__actions">
                <div class="panel__action">
                    <button
                        class="form__button form__button--outlined form__button--centered"
                        x-on:click="toggle"
                        x-text="
                            isToggledOn()
                                ? '{{ __('common.search-hide') }}'
                                : '{{ __('common.search-advanced') }}'
                        "
                    ></button>
                </div>
            </div>
        </header>
        <div class="panel__body" style="padding: 5px">
            <div class="form__group--horizontal">
                <p class="form__group">
                    <input
                        id="name"
                        wire:model.live="name"
                        class="form__text"
                        placeholder=" "
                        @if (auth()->user()->settings?->torrent_search_autofocus)
                            autofocus
                        @endif
                    />
                    <label class="form__label form__label--floating" for="name">
                        {{ __('torrent.name') }}
                    </label>
                </p>
            </div>
            <form class="form" x-cloak x-show="isToggledOn">
                <div class="form__group--short-horizontal">
                    <p class="form__group">
                        <input
                            id="description"
                            wire:model.live="description"
                            class="form__text"
                            placeholder=" "
                        />
                        <label class="form__label form__label--floating" for="description">
                            {{ __('torrent.description') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <input
                            id="uploader"
                            wire:model.live="uploader"
                            class="form__text"
                            placeholder=" "
                        />
                        <label class="form__label form__label--floating" for="uploader">
                            {{ __('torrent.uploader') }}
                        </label>
                    </p>
                </div>
                <div class="form__group--short-horizontal">
                    <div class="form__group--short-horizontal">
                        <p class="form__group">
                            <input
                                id="minSize"
                                wire:model.live="minSize"
                                class="form__text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                placeholder=" "
                            />
                            <label class="form__label form__label--floating" for="minSize">
                                Minimum Size
                            </label>
                        </p>
                        <p class="form__group">
                            <select
                                id="minSizeMultiplier"
                                wire:model.live="minSizeMultiplier"
                                class="form__select"
                                placeholder=" "
                            >
                                <option value="1" selected>Bytes</option>
                                <option value="1000">KB</option>
                                <option value="1024">KiB</option>
                                <option value="1000000">MB</option>
                                <option value="1048576">MiB</option>
                                <option value="1000000000">GB</option>
                                <option value="1073741824">GiB</option>
                                <option value="1000000000000">TB</option>
                                <option value="1099511627776">TiB</option>
                            </select>
                            <label
                                class="form__label form__label--floating"
                                for="minSizeMultiplier"
                            >
                                Unit
                            </label>
                        </p>
                    </div>
                    <div class="form__group--short-horizontal">
                        <p class="form__group">
                            <input
                                id="maxSize"
                                wire:model.live="maxSize"
                                class="form__text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                placeholder=" "
                            />
                            <label class="form__label form__label--floating" for="maxSize">
                                Maximum Size
                            </label>
                        </p>
                        <p class="form__group">
                            <select
                                id="maxSizeMultiplier"
                                wire:model.live="maxSizeMultiplier"
                                class="form__select"
                                placeholder=" "
                            >
                                <option value="1" selected>Bytes</option>
                                <option value="1000">KB</option>
                                <option value="1024">KiB</option>
                                <option value="1000000">MB</option>
                                <option value="1048576">MiB</option>
                                <option value="1000000000">GB</option>
                                <option value="1073741824">GiB</option>
                                <option value="1000000000000">TB</option>
                                <option value="1099511627776">TiB</option>
                            </select>
                            <label
                                class="form__label form__label--floating"
                                for="maxSizeMultiplier"
                            >
                                Unit
                            </label>
                        </p>
                    </div>
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
                                                wire:model.live="categoryIds"
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
                            <legend class="form__legend">{{ __('torrent.type') }}</legend>
                            <div class="form__fieldset-checkbox-container">
                                @foreach ($types as $type)
                                    <p class="form__group">
                                        <label class="form__label">
                                            <input
                                                class="form__checkbox"
                                                type="checkbox"
                                                value="{{ $type->id }}"
                                                wire:model.live="typeIds"
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
                            <legend class="form__legend">Buff</legend>
                            <div class="form__fieldset-checkbox-container">
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="0"
                                            wire:model.live="free"
                                        />
                                        0% Freeleech
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="25"
                                            wire:model.live="free"
                                        />
                                        25% Freeleech
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="50"
                                            wire:model.live="free"
                                        />
                                        50% Freeleech
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="75"
                                            wire:model.live="free"
                                        />
                                        75% Freeleech
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="100"
                                            wire:model.live="free"
                                        />
                                        100% Freeleech
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="doubleup"
                                        />
                                        Double Upload
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="featured"
                                        />
                                        Featured
                                    </label>
                                </p>
                            </div>
                        </fieldset>
                    </div>
                    <div class="form__group">
                        <fieldset class="form__fieldset">
                            <legend class="form__legend">Tags</legend>
                            <div class="form__fieldset-checkbox-container">
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="highspeed"
                                        />
                                        {{ __('common.high-speeds') }}
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="bookmarked"
                                        />
                                        {{ __('common.bookmarked') }}
                                    </label>
                                </p>
                            </div>
                        </fieldset>
                    </div>
                    <div class="form__group">
                        <fieldset class="form__fieldset">
                            <legend class="form__legend">{{ __('torrent.health') }}</legend>
                            <div class="form__fieldset-checkbox-container">
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="alive"
                                        />
                                        {{ __('torrent.alive') }}
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="dying"
                                        />
                                        Dying
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="dead"
                                        />
                                        Dead
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="graveyard"
                                        />
                                        {{ __('graveyard.graveyard') }}
                                    </label>
                                </p>
                            </div>
                        </fieldset>
                    </div>
                    <div class="form__group">
                        <fieldset class="form__fieldset">
                            <legend class="form__legend">{{ __('torrent.history') }}</legend>
                            <div class="form__fieldset-checkbox-container">
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="notDownloaded"
                                        />
                                        Not Downloaded
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="downloaded"
                                        />
                                        Downloaded
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="seeding"
                                        />
                                        Seeding
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="leeching"
                                        />
                                        Leeching
                                    </label>
                                </p>
                                <p class="form__group">
                                    <label class="form__label">
                                        <input
                                            class="form__checkbox"
                                            type="checkbox"
                                            value="1"
                                            wire:model.live="incomplete"
                                        />
                                        Incomplete
                                    </label>
                                </p>
                            </div>
                        </fieldset>
                    </div>
                </div>
            </form>
        </div>
    </section>
    <section class="panelV2 torrent-search__results">
        <header class="panel__header">
            <h2 class="panel__heading">{{ __('torrent.torrents') }}</h2>
            <div class="panel__actions">
                <div class="panel__action">
                    <div class="form__group">
                        <select id="view" class="form__select" wire:model.live="view" required>
                            <option value="list">{{ __('torrent.list') }}</option>
                        </select>
                        <label class="form__label form__label--floating" for="view">Layout</label>
                    </div>
                </div>
                <div class="panel__action">
                    <div class="form__group">
                        <select
                            id="perPage"
                            class="form__select"
                            wire:model.live="perPage"
                            required
                        >
                                <option>25</option>
                                <option>50</option>
                                <option>75</option>
                                <option>100</option>
                        </select>
                        <label class="form__label form__label--floating" for="perPage">
                            {{ __('common.quantity') }}
                        </label>
                    </div>
                </div>
            </div>
        </header>
        {{ $torrents->links('partials.pagination') }}

                <div class="data-table-wrapper torrent-search--list__results">
                    <table class="data-table">
                        <thead>
                            <tr class="torrent-search--list__no-poster-headers"
                            >
                                <th class="torrent-search--list__format-header">Format</th>
                                <th
                                    class="torrent-search--list__name-header"
                                    wire:click="sortBy('name')"
                                    role="columnheader button"
                                >
                                    {{ __('torrent.name') }}
                                    @include('livewire.includes._sort-icon', ['field' => 'name'])
                                </th>
                                <th class="torrent-search--list__actions-header">
                                    {{ __('common.actions') }}
                                </th>
                                <th class="torrent-search--list__ratings-header"></th>
                                <th
                                    class="torrent-search--list__size-header"
                                    wire:click="sortBy('size')"
                                    role="columnheader button"
                                >
                                    {{ __('torrent.size') }}
                                    @include('livewire.includes._sort-icon', ['field' => 'size'])
                                </th>
                                <th
                                    class="torrent-search--list__seeders-header"
                                    wire:click="sortBy('seeders')"
                                    role="columnheader button"
                                    title="{{ __('torrent.seeders') }}"
                                >
                                    <i class="fas fa-arrow-alt-circle-up"></i>
                                    @include('livewire.includes._sort-icon', ['field' => 'seeders'])
                                </th>
                                <th
                                    class="torrent-search--list__leechers-header"
                                    wire:click="sortBy('leechers')"
                                    role="columnheader button"
                                    title="{{ __('torrent.leechers') }}"
                                >
                                    <i class="fas fa-arrow-alt-circle-down"></i>
                                    @include('livewire.includes._sort-icon', ['field' => 'leechers'])
                                </th>
                                <th
                                    class="torrent-search--list__completed-header"
                                    wire:click="sortBy('times_completed')"
                                    role="columnheader button"
                                    title="{{ __('torrent.completed') }}"
                                >
                                    <i class="fas fa-check-circle"></i>
                                    @include('livewire.includes._sort-icon', ['field' => 'times_completed'])
                                </th>
                                <th
                                    class="torrent-search--list__age-header"
                                    wire:click="sortBy('created_at')"
                                    role="columnheader button"
                                >
                                    {{ __('torrent.age') }}
                                    @include('livewire.includes._sort-icon', ['field' => 'created_at'])
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($torrents as $torrent)
                                <x-torrent.row
                                    :torrent="$torrent"
                                    :personalFreeleech="$personalFreeleech"
                                />
                            @empty
                                <tr>
                                    <td colspan="10">{{ __('common.no-result') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

        {{ $torrents->links('partials.pagination') }}
    </section>
</div>
