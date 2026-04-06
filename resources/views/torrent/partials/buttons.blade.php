<menu class="torrent__buttons form__group--short-horizontal">
    <li class="form__group form__group--short-horizontal">
        @if ($fileExists = file_exists(public_path() . '/files/torrents/' . $torrent->file_name))
            @if (config('torrent.download_check_page') == 1)
                <a
                    class="form__button form__button--filled form__button--centered"
                    href="{{ route('download_check', ['id' => $torrent->id]) }}"
                    role="button"
                >
                    <i class="{{ config('other.font-awesome') }} fa-download"></i>
                    Torrent {{ __('common.download') }}
                </a>
            @else
                <a
                    class="form__button form__button--filled form__button--centered"
                    href="{{ route('download', ['id' => $torrent->id]) }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-download"></i>
                    Torrent {{ __('common.download') }}
                </a>
            @endif
        @else
            @php
                $magnetUrl = 'magnet:?dn=' . urlencode($torrent->name);
                $tVersion = $torrent->torrent_version ?? 1;
                if ($tVersion === 1 || $tVersion === 3) {
                    $magnetUrl .= '&xt=urn:btih:' . bin2hex($torrent->info_hash);
                }
                if ($tVersion >= 2 && $torrent->info_hash_v2 !== null) {
                    $magnetUrl .= '&xt=urn:btmh:1220' . bin2hex($torrent->info_hash_v2);
                }
                $magnetUrl .= '&as=' . route('torrent.download.rsskey', ['id' => $torrent->id, 'rsskey' => $user->rsskey]);
                $magnetUrl .= '&tr=' . route('announce', ['passkey' => $user->passkey]);
                $magnetUrl .= '&xl=' . $torrent->size;
            @endphp
            <a
                href="{{ $magnetUrl }}"
                class="form__button form__button--filled form__button--centered"
            >
                <i class="{{ config('other.font-awesome') }} fa-magnet"></i>
                {{ __('common.magnet') }}
            </a>
        @endif
    </li>
@if ($torrent->external_download_link)
    <li class="form__group form__group--short-horizontal">
                <a
                    class="form__button form__button--filled form__button--centered"
                    href="{{ $torrent->external_download_link }}"
                    role="button"
                >
                    <i class="{{ config('other.font-awesome') }} fa-link"></i>
                    External {{ __('common.download') }}
                </a>
    </li>
@endif
    @if ($fileExists)
        @if ($torrent->free !== 100 && config('other.freeleech') == false && ! $personal_freeleech && $user->group->is_freeleech == 0 && ! $torrent->freeleechToken_exists)
            <li class="form__group form__group--short-horizontal">
                <form
                    action="{{ route('freeleech_token', ['id' => $torrent->id]) }}"
                    method="POST"
                    style="display: contents"
                    x-data="freeleechTokenConfirmation"
                >
                    @csrf
                    <button
                        class="form__button form__button--outlined form__button--centered"
                        title="{{ __('torrent.fl-tokens-left', ['tokens' => $user->fl_tokens]) }}!"
                        x-on:click.prevent="confirmAction"
                    >
                        {{ __('torrent.use-fl-token') }}
                    </button>
                </form>
                <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
                    document.addEventListener('alpine:init', () => {
                        Alpine.data('freeleechTokenConfirmation', () => ({
                            confirmAction() {
                                Swal.fire({
                                    title: 'Are you sure?',
                                    text: 'This will use one of your Freeleech Tokens!',
                                    icon: 'warning',
                                    showConfirmButton: true,
                                    showCloseButton: true,
                                }).then((result) => {
                                    if (result.isConfirmed && {{ $torrent->seeders }} == 0) {
                                        Swal.fire({
                                            title: 'Are you sure?',
                                            text: 'This torrent has 0 seeders!',
                                            icon: 'warning',
                                            showConfirmButton: true,
                                            showCancelButton: true,
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                this.$root.submit();
                                            }
                                        });
                                    } else if (result.isConfirmed) {
                                        this.$root.submit();
                                    }
                                });
                            },
                        }));
                    });
                </script>
            </li>
        @endif
    @endif

    @if (config('other.thanks-system.is-enabled'))
        <li class="form__group form__group--short-horizontal">
            @livewire('thank-button', ['torrent' => $torrent])
        </li>
    @endif

    @if ($torrent->nfo)
        <li x-data="dialog" class="form__group form__group--short-horizontal">
            <button
                class="form__button form__button--outlined form__button--centered"
                x-bind="showDialog"
            >
                <i class="{{ config('other.font-awesome') }} fa-info-circle"></i>
                NFO
            </button>
            <dialog class="dialog dialog--auto-width" x-bind="dialogElement">
                <h4 class="dialog__heading">NFO</h4>
                <div class="dialog__form" x-bind="dialogForm">
                    <div class="bbcode-rendered" style="text-align: left">
                        <pre
                            style="width: max-content"
                        ><code class="torrent__nfo" style="white-space: pre;">{{ iconv('cp437', 'utf8', $torrent->nfo) }}</code></pre>
                    </div>
                </div>
            </dialog>
        </li>
    @endif

    <li class="form__group form__group--short-horizontal">
        @livewire('bookmark-button', [
            'torrent' => $torrent,
            'isBookmarked' => $torrent->bookmarks_exists,
            'user' => auth()->user(),
            'bookmarksCount' => $torrent->bookmarks_count ?? 0,
        ])
    </li>

    @if ($torrent->seeders <= 2 &&
        /* $history is used inside the resurrection code below and assumes is set if torrent->seeders are equal to 0 */
        null !==
            ($history = $user
                ->history()
                ->where('torrent_id', $torrent->id)
                ->first()) &&
        $history->seeder == 0 &&
        $history->active == 1)
        <li class="form__group form__group--short-horizontal">
            <form
                action="{{ route('reseed', ['id' => $torrent->id]) }}"
                method="POST"
                style="display: inline"
            >
                @csrf
                <button class="form__button form__button--outlined form__button--centered">
                    <i class="{{ config('other.font-awesome') }} fa-envelope"></i>
                    {{ __('torrent.request-reseed') }}
                </button>
            </form>
        </li>
    @endif

    @if (DB::table('resurrections')->where('torrent_id', '=', $torrent->id)->where('rewarded', '=', 0)->exists())
        <li class="form__group form__group--short-horizontal">
            <button class="form__button form__button--outlined form__button--centered" disabled>
                {{ strtolower(__('graveyard.pending')) }}
            </button>
        </li>
    @elseif ($torrent->seeders == 0 && $torrent->created_at->lt(\Illuminate\Support\Carbon::now()->subDays(30)))
        <li class="form__group form__group--short-horizontal" x-data="dialog">
            <button
                class="form__button form__button--outlined form__button--centered"
                x-bind="showDialog"
            >
                <i class="{{ config('other.font-awesome') }} fa-list-ol"></i>
                {{ __('graveyard.resurrect') }}
            </button>
            <dialog class="dialog" x-bind="dialogElement">
                <h4 class="dialog__heading">
                    {{ __('graveyard.resurrect') }} {{ strtolower(__('torrent.torrent')) }} ?
                </h4>
                <form
                    class="dialog__form"
                    method="POST"
                    action="{{ route('users.resurrections.store', ['user' => auth()->user()]) }}"
                    x-bind="dialogForm"
                >
                    @csrf
                    <input type="hidden" name="torrent_id" value="{{ $torrent->id }}" />
                    <p class="form__group">
                        {{ __('graveyard.howto') }}
                    </p>
                    <p>
                        {!! __('graveyard.howto-desc1', ['name' => $torrent->name]) !!}
                        <span class="text-red text-bold">
                            {{ $history === null ? '0' : App\Helpers\StringHelper::timeElapsed($history->seedtime) }}
                        </span>
                        {{ strtolower(__('graveyard.howto-hits')) }}
                        <span class="text-red text-bold">
                            {{ $history === null ? App\Helpers\StringHelper::timeElapsed(config('graveyard.time')) : App\Helpers\StringHelper::timeElapsed($history->seedtime + config('graveyard.time')) }}
                        </span>
                        {{ strtolower(__('graveyard.howto-desc2')) }}
                        <span
                            class="text-bold text-pink"
                            style="background-image:url({{ url('/img/sparkels.gif') }};"
                        >
                            {{ config('graveyard.reward') }} {{ __('torrent.freeleech') }}
                            Token(s)!
                        </span>
                    </p>
                    <p class="form__group" style="text-align: left">
                        <button class="form__button form__button--filled">
                            {{ __('graveyard.resurrect') }}
                        </button>
                        <button
                            formmethod="dialog"
                            formnovalidate
                            class="form__button form__button--outlined"
                        >
                            {{ __('common.cancel') }}
                        </button>
                    </p>
                </form>
            </dialog>
        </li>
    @endif
    <li x-data="dialog" class="form__group form__group--short-horizontal">
        <button
            class="form__button form__button--outlined form__button--centered"
            x-bind="showDialog"
        >
            <i class="{{ config('other.font-awesome') }} fa-fw fa-eye"></i>
            {{ __('common.report') }}
        </button>
        <dialog class="dialog" x-bind="dialogElement">
            <h4 class="dialog__heading">
                {{ __('common.report') }} {{ strtolower(__('torrent.torrent')) }}:
                {{ $torrent->name }}
            </h4>
            <form
                class="dialog__form"
                method="POST"
                action="{{ route('report_torrent', ['id' => $torrent->id]) }}"
                x-bind="dialogForm"
            >
                @csrf
                <input type="hidden" name="torrent_id" value="{{ $torrent->id }}" />
                <p class="form__group">
                    <textarea
                        id="message"
                        class="form__textarea"
                        name="message"
                        required
                    ></textarea>
                    <label
                        for="report_reason"
                        class="form__label form__label--floating"
                        for="message"
                    >
                        {{ __('common.reason') }}
                    </label>
                </p>
                <p class="form__group" style="text-align: left">
                    <button class="form__button form__button--filled">
                        {{ __('common.report') }}
                    </button>
                    <button
                        formmethod="dialog"
                        formnovalidate
                        class="form__button form__button--outlined"
                    >
                        {{ __('common.cancel') }}
                    </button>
                </p>
            </form>
        </dialog>
    </li>
</menu>
