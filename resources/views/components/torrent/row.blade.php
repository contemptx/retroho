@props([
    'torrent',
    'personalFreeleech',
])

<tr
    @class([
        'torrent-search--list__no-poster-row',
        'torrent-search--list__sticky-row' => $torrent->sticky,
    ])
    data-torrent-id="{{ $torrent->id }}"
    data-category-id="{{ $torrent->category_id }}"
    data-type-id="{{ $torrent->type_id }}"
    wire:key="torrent-search-row-{{ $torrent->id }}"
>
    <td class="torrent-search--list__format">
        <div>
            <div class="torrent-search--list__category">
                @if ($torrent->category->image !== null)
                    <img
                        src="{{ url('files/img/' . $torrent->category->image) }}"
                        title="{{ $torrent->category->name }} {{ strtolower(__('torrent.torrent')) }}"
                        alt="{{ $torrent->category->name }}"
                        loading="lazy"
                        @style([
                            'height: 35px',
                        ])
                    />
                @else
                    <i
                        class="{{ $torrent->category->icon }} category__icon"
                        @style([
                            'font-size: 24px',
                        ])
                    ></i>
                @endif
            </div>
            <div class="torrent-search--list__resolution-and-type">
                <span class="torrent-search--list__type">
                    {{ $torrent->type->name }}
                </span>
            </div>
        </div>
    </td>
    <td class="torrent-search--list__overview">
        <div>
            <a
                class="torrent-search--list__name"
                style="padding: 0; margin: 0;"
                href="{{ route('torrents.show', ['id' => $torrent->id]) }}"
            >
                {{ $torrent->name }}
            </a>
            <x-user_tag
                class="torrent-search--list__uploader"
                :user="$torrent->user"
                :anon="$torrent->anon"
            />
            @include('components.partials._torrent-icons')
        </div>
    </td>
    <td class="torrent-search--list__buttons">
        <div>
            @if (auth()->user()->group->is_editor || auth()->user()->group->is_modo || auth()->id() === $torrent->user_id)
                <a
                    class="torrent-search--list__edit form__standard-icon-button"
                    href="{{ route('torrents.edit', ['id' => $torrent->id]) }}"
                    title="{{ __('common.edit') }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-pencil-alt"></i>
                </a>
            @endif

            @if (config('torrent.download_check_page'))
                <a
                    class="torrent-search--list__file form__standard-icon-button"
                    href="{{ route('download_check', ['id' => $torrent->id]) }}"
                    title="{{ __('common.download') }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-download"></i>
                </a>
            @else
                <a
                    class="torrent-search--list__file form__standard-icon-button"
                    href="{{ route('download', ['id' => $torrent->id]) }}"
                    title="{{ __('common.download') }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-download"></i>
                </a>
            @endif
            @if (config('torrent.magnet'))
                @php
                    $magnetUrl = 'magnet:?dn=' . urlencode($torrent->name);
                    $tVersion = $torrent->torrent_version ?? 1;
                    if ($tVersion === 1 || $tVersion === 3) {
                        $magnetUrl .= '&xt=urn:btih:' . bin2hex($torrent->info_hash);
                    }
                    if ($tVersion >= 2 && $torrent->info_hash_v2 !== null) {
                        $magnetUrl .= '&xt=urn:btmh:1220' . bin2hex($torrent->info_hash_v2);
                    }
                    $magnetUrl .= '&as=' . route('torrent.download.rsskey', ['id' => $torrent->id, 'rsskey' => auth()->user()->rsskey]);
                    $magnetUrl .= '&tr=' . route('announce', ['passkey' => auth()->user()->passkey]);
                    $magnetUrl .= '&xl=' . $torrent->size;
                @endphp
                <a
                    class="torrent-search--list__maget form__contained-icon-button form__contained-icon-button--filled"
                    href="{{ $magnetUrl }}"
                    download
                    title="{{ __('common.magnet') }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-magnet"></i>
                </a>
            @endif
        </div>
    </td>
    <td class="torrent-search--list__rating"></td>
    <td class="torrent-search--list__size">
        <span>{{ $torrent->getSize() }}</span>
    </td>
    <td class="torrent-search--list__seeders">
        <a href="{{ route('peers', ['id' => $torrent->id]) }}">
            <span>{{ $torrent->seeds_count ?? $torrent->seeders }}</span>
        </a>
    </td>
    <td class="torrent-search--list__leechers">
        <a href="{{ route('peers', ['id' => $torrent->id]) }}">
            <span>{{ $torrent->leeches_count ?? $torrent->leechers }}</span>
        </a>
    </td>
    <td class="torrent-search--list__completed">
        <a href="{{ route('history', ['id' => $torrent->id]) }}">
            <span>{{ $torrent->times_completed }}</span>
        </a>
    </td>
    <td class="torrent-search--list__age">
        <time datetime="{{ $torrent->created_at }}" title="{{ $torrent->created_at }}">
            {{ $torrent->created_at->diffForHumans() }}
        </time>
    </td>
</tr>
