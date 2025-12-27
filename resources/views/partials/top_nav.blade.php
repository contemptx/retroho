<nav class="top-nav" x-data="{ expanded: false }" x-bind:class="expanded && 'mobile'">
    <div class="top-nav__left">
        <a class="top-nav__branding" href="{{ route('home.index') }}">
            <img src="{{ url('favicon.ico') }}" style="width: 85px;" alt="{{ \config('other.title') }}" />
        </a>
    </div>
    <ul class="top-nav__main-menus" x-bind:class="expanded && 'mobile'">
        <li class="top-nav--left__list-item top-nav__dropdown">
            <a class="top-nav__dropdown--nontouch" href="{{ route('torrents.index') }}">
                <div class="top-nav--left__container">
                    {{ __('torrent.torrents') }}
                </div>
            </a>
            <a class="top-nav__dropdown--touch" tabindex="0">
                <div class="top-nav--left__container">
                    {{ __('torrent.torrents') }}
                </div>
            </a>
            <ul>
                <li>
                    <a href="{{ route('torrents.index') }}">
                        <i class="{{ config('other.font-awesome') }} fa-download"></i>
                        {{ __('torrent.torrents') }}
                    </a>
                </li>
                <li>
                    <a href="{{ route('torrents.create', ['category_id' => 1]) }}">
                        <i class="{{ config('other.font-awesome') }} fa-upload"></i>
                        {{ __('common.upload') }}
                    </a>
                </li>
            </ul>
        </li>
        <li class="top-nav--left__list-item top-nav__dropdown">
            <a class="top-nav__dropdown--nontouch" href="{{ route('nzbs.index') }}">
                <div class="top-nav--left__container">
                     NZBs
                </div>
            </a>
            <a class="top-nav__dropdown--touch" tabindex="0">
                <div class="top-nav--left__container">
                    NZBs
                </div>
            </a>
            <ul>
                <li>
                    <a href="{{ route('nzbs.index') }}">
                        <i class="{{ config('other.font-awesome') }} fa-download"></i>
                        NZBs
                    </a>
                </li>
                <li>
                    <a href="{{ route('nzbs.create') }}">
                        <i class="{{ config('other.font-awesome') }} fa-upload"></i>
                        {{ __('common.upload') }}
                    </a>
                </li>
            </ul>
        </li>
        <li class="top-nav--left__list-item top-nav__dropdown">
            <a class="top-nav__dropdown--nontouch" href="{{ route('forums.index') }}">
                <div class="top-nav--left__container">
                    {{ __('common.community') }}
                </div>
            </a>
            <a class="top-nav__dropdown--touch" tabindex="0">
                <div class="top-nav--left__container">
                    {{ __('common.community') }}
                </div>
            </a>
            <ul>
                <li>
                    <a href="{{ route('forums.index') }}">
                        <i class="{{ config('other.font-awesome') }} fa-comments"></i>
                        {{ __('forum.forums') }}
                    </a>
                </li>
                <li>
                    <a href="{{ route('articles.index') }}">
                        <i class="{{ config('other.font-awesome') }} fa-newspaper"></i>
                        {{ __('common.news') }}
                    </a>
                </li>
                @if (! empty(config('unit3d.chat-link-url')))
                    <li>
                        <a href="{{ config('unit3d.chat-link-url') }}">
                            <i class="{{ config('unit3d.chat-link-icon') }}"></i>
                            {{ config('unit3d.chat-link-name') ?: __('common.chat') }}
                        </a>
                    </li>
                @endif
            </ul>
        </li>
        <li class="top-nav__dropdown">
            <a tabindex="0">
                <div class="top-nav--left__container">
                    {{ __('common.support') }}
                </div>
            </a>
            <ul>
                <li>
                    <a href="https://arcadesystems.co.uk/help" target"_blank">
                        <i class="{{ config('other.font-awesome') }} fa-info"></i>
                        Help Guides
                    </a>
                </li>
                <li>
                    <a href="https://arcadesystems.co.uk/contact-us">
                        <i class="{{ config('other.font-awesome') }} fa-question"></i>
                        Contact Us
                    </a>
                </li>
            </ul>
        </li>
        <li class="top-nav--left__list-item top-nav__dropdown">
            <a class="top-nav__dropdown--nontouch" href="https://arcadesystems.co.uk">
                <div class="top-nav--left__container">
                    Arcade Systems
                </div>
            </a>
        </li>
    </ul>
    <div class="top-nav__right" x-bind:class="expanded && 'mobile'">
        <ul class="top-nav__stats" x-bind:class="expanded && 'mobile'">
            <li class="top-nav__stats-up" title="{{ __('common.upload') }}">
                <i class="{{ config('other.font-awesome') }} fa-arrow-up text-green"></i>
                {{ auth()->user()->formatted_uploaded }}
            </li>
            <li class="top-nav__stats-down" title="{{ __('common.download') }}">
                <i class="{{ config('other.font-awesome') }} fa-arrow-down text-red"></i>
                {{ auth()->user()->formatted_downloaded }}
            </li>
            <li class="top-nav__stats-ratio" title="{{ __('common.ratio') }}">
                <i class="{{ config('other.font-awesome') }} fa-sync-alt text-blue"></i>
                {{ auth()->user()->formatted_ratio }}
            </li>
        </ul>
        <ul class="top-nav__ratio-bar" x-bind:class="expanded && 'mobile'">
            <li class="ratio-bar__uploaded" title="{{ __('common.upload') }}">
                <a href="{{ route('users.torrents.index', ['user' => auth()->user()]) }}">
                    <i class="{{ config('other.font-awesome') }} fa-arrow-up"></i>
                    {{ auth()->user()->formatted_uploaded }}
                </a>
            </li>
            <li class="ratio-bar__downloaded" title="{{ __('common.download') }}">
                <a
                    href="{{ route('users.history.index', ['user' => auth()->user(), 'downloaded' => 'include']) }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-arrow-down"></i>
                    {{ auth()->user()->formatted_downloaded }}
                </a>
            </li>
            @php
                // Generally sites have more seeders than leechers, so it ends up being faster (by approximately 50%) to compute these stats instead of computing them individually
                $peer_count = Cache::remember(
                    'users:' . auth()->id() . ':peer_count',
                    60,
                    fn () => DB::table('peers')
                        ->where('user_id', '=', auth()->id())
                        ->where('active', '=', 1)
                        ->count() ?? 0
                );
                $leech_count = Cache::remember(
                    'users:' . auth()->id() . ':leech_count',
                    60,
                    fn () => DB::table('peers')
                        ->where('seeder', '=', false)
                        ->where('user_id', '=', auth()->id())
                        ->where('active', '=', 1)
                        ->count() ?? 0
                );
            @endphp

            <li class="ratio-bar__seeding" title="{{ __('torrent.seeding') }}">
                <a href="{{ route('users.peers.index', ['user' => auth()->user()]) }}">
                    <i class="{{ config('other.font-awesome') }} fa-upload"></i>
                    {{ $peer_count - $leech_count }}
                </a>
            </li>
            <li class="ratio-bar__leeching" title="{{ __('torrent.leeching') }}">
                <a
                    href="{{ route('users.peers.index', ['user' => auth()->user(), 'seeding' => 'exclude']) }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-download"></i>
                    {{ $leech_count }}
                </a>
            </li>
            <li class="ratio-bar__buffer" title="{{ __('common.buffer') }}">
                <a href="{{ route('users.history.index', ['user' => auth()->user()]) }}">
                    <i class="{{ config('other.font-awesome') }} fa-exchange"></i>
                    {{ auth()->user()->formatted_buffer }}
                </a>
            </li>
            <li class="ratio-bar__ratio" title="{{ __('common.ratio') }}">
                <a href="{{ route('users.history.index', ['user' => auth()->user()]) }}">
                    <i class="{{ config('other.font-awesome') }} fa-sync-alt"></i>
                    {{ auth()->user()->formatted_ratio }}
                </a>
            </li>
        </ul>
        <a
            class="top-nav__username--highresolution"
            href="{{ route('users.show', ['user' => auth()->user()]) }}"
        >
            <span
                class="text-bold"
                style="
                    color: {{ auth()->user()->group->color }};
                    background-image: {{ auth()->user()->group->effect }};
                "
            >
                <i class="{{ auth()->user()->group->icon }}"></i>
                {{ auth()->user()->username }}
                @if ($warningsExists = auth()->user()->warnings()->active()->exists())
                    <i
                        class="{{ config('other.font-awesome') }} fa-exclamation-circle text-orange"
                        title="{{ __('common.active-warning') }}"
                    ></i>
                @endif
            </span>
        </a>
        <ul class="top-nav__icon-bar" x-bind:class="expanded && 'mobile'">
            @if (auth()->user()->group->is_modo)
                <li>
                    <a
                        class="top-nav--right__icon-link"
                        href="{{ route('staff.dashboard.index') }}"
                        title="{{ __('staff.staff-dashboard') }}"
                    >
                        <i class="{{ config('other.font-awesome') }} fa-cogs"></i>
                    </a>
                </li>
            @endif

            @if (auth()->user()->group->is_modo)
                <li>
                    <a
                        class="top-nav--right__icon-link"
                        href="{{ route('staff.moderation.index') }}"
                        title="{{ __('staff.torrent-moderation') }}"
                    >
                        <i class="{{ config('other.font-awesome') }} fa-tasks"></i>
                        @php
                            $torrents_unmoderated = DB::table('torrents')
                                ->where('status', '=', \App\Models\Torrent::PENDING)
                                ->whereNull('deleted_at')
                                ->exists();
                        @endphp

                        @if ($torrents_unmoderated)
                            <x-animation.notification />
                        @endif
                    </a>
                </li>
            @endif

            <li>
                @php
                    $pm = DB::table('participants')
                        ->where('user_id', '=', auth()->id())
                        ->where('read', '=', false)
                        ->whereNull('deleted_at')
                        ->exists();
                @endphp

                <a
                    class="top-nav--right__icon-link"
                    href="{{ route('users.conversations.index', ['user' => auth()->user()]) }}"
                    title="{{ __('pm.inbox') }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-envelope"></i>
                    @if ($pm)
                        <x-animation.notification />
                    @endif
                </a>
            </li>
            <li class="top-nav__dropdown">
                <a
                    class="top-nav__dropdown--nontouch"
                    href="{{ route('users.show', ['user' => auth()->user()]) }}"
                >
                    <img
                        src="{{ url(auth()->user()->image ? 'files/img/' . auth()->user()->image : 'img/profile.png') }}"
                        alt="{{ __('user.my-profile') }}"
                        class="top-nav__profile-image"
                    />
                </a>
                <a class="top-nav__dropdown--touch" tabindex="0">
                    <img
                        src="{{ url(auth()->user()->image ? 'files/img/' . auth()->user()->image : 'img/profile.png') }}"
                        alt="{{ __('user.my-profile') }}"
                        class="top-nav__profile-image"
                    />
                </a>
                <ul>
                    <li>
                        <a
                            class="top-nav__username"
                            href="{{ route('users.show', ['user' => auth()->user()]) }}"
                        >
                            <span
                                class="text-bold"
                                style="
                                    color: {{ auth()->user()->group->color }};
                                    background-image: {{ auth()->user()->group->effect }};
                                "
                            >
                                <i class="{{ auth()->user()->group->icon }}"></i>
                                {{ auth()->user()->username }}
                                @if ($warningsExists)
                                    <i
                                        class="{{ config('other.font-awesome') }} fa-exclamation-circle text-orange"
                                        title="{{ __('common.active-warning') }}"
                                    ></i>
                                @endif
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('users.show', ['user' => auth()->user()]) }}">
                            <i class="{{ config('other.font-awesome') }} fa-user"></i>
                            {{ __('user.my-profile') }}
                        </a>
                    </li>
                    <li>
                        <a
                            class="top-nav--right__link"
                            href="{{ route('users.general_settings.edit', ['user' => auth()->user()]) }}"
                        >
                            <i class="{{ config('other.font-awesome') }} fa-cogs"></i>
                            {{ __('user.my-settings') }}
                        </a>
                    </li>
                    <li>
                        <form role="form" method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="top-nav--right__link" type="submit">
                                <i class="far fa-sign-out"></i>
                                {{ __('auth.logout') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
    <button
        class="top-nav__toggle {{ \config('other.font-awesome') }}"
        x-bind:class="expanded ? 'fa-times mobile' : 'fa-bars'"
        x-on:click="expanded = !expanded"
    ></button>
</nav>
