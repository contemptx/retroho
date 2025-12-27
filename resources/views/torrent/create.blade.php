@extends('layout.default')

@section('title')
    <title>Upload - {{ config('other.title') }}</title>
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('torrents.index') }}" class="breadcrumb__link">
            {{ __('torrent.torrents') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('common.upload') }}
    </li>
@endsection

@section('nav-tabs')
    <li class="nav-tabV2">
        <a class="nav-tab__link" href="{{ route('torrents.index') }}">
            {{ __('torrent.search') }}
        </a>
    </li>
    <li class="nav-tabV2">
        <a class="nav-tab__link" href="{{ route('rss.index') }}">
            {{ __('rss.rss') }}
        </a>
    </li>
    <li class="nav-tab--active">
        <a
            class="nav-tab--active__link"
            href="{{ route('torrents.create', ['category_id' => 1]) }}"
        >
            {{ __('common.upload') }}
        </a>
    </li>
@endsection

@section('main')
    <section
        class="upload panelV2"
    >
        <h2 class="upload-title panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-file"></i>
            {{ __('torrent.torrent') }}
        </h2>
        <div class="panel__body">
            <form
                name="upload"
                class="upload-form form"
                id="upload-form"
                method="POST"
                action="{{ route('torrents.store') }}"
                enctype="multipart/form-data"
            >
                @csrf
                <p class="form__group">
                    <label for="torrent" class="form__label">
                        Torrent {{ __('torrent.file') }}
                    </label>
                    <input
                        class="upload-form-file form__file"
                        type="file"
                        accept=".torrent"
                        name="torrent"
                        id="torrent"
                        required
                    />
                </p>
                <p class="form__group">
                    <label for="nfo" class="form__label">
                        NFO {{ __('torrent.file') }} ({{ __('torrent.optional') }})
                    </label>
                    <input
                        id="nfo"
                        class="upload-form-file form__file"
                        type="file"
                        accept=".nfo"
                        name="nfo"
                    />
                </p>
                <p class="form__group">
                    <input
                        type="text"
                        name="name"
                        id="title"
                        class="form__text"
                        value="{{ $title ?: old('name') }}"
                        required
                    />
                    <label class="form__label form__label--floating" for="title">
                        {{ __('torrent.title') }}
                    </label>
                </p>
                <p class="form__group">
                    <select
                        name="category_id"
                        class="form__select"
                        required
                    >
                        <option hidden selected disabled value=""></option>
                        @foreach ($categories as $category)
                            <option class="form__option" value="{{ $category->id }}">
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    <label class="form__label form__label--floating" for="autocat">
                        {{ __('torrent.category') }}
                    </label>
                </p>
                <p class="form__group">
                    <select name="type_id" class="form__select" required>
                        <option hidden disabled selected value=""></option>
                        @foreach ($types as $type)
                            <option
                                value="{{ $type->id }}"
                            >
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                    <label class="form__label form__label--floating" for="autotype">
                        {{ __('torrent.type') }}
                    </label>
                </p>

                @livewire('bbcode-input', ['name' => 'description', 'label' => __('common.description'), 'required' => true])

                <p class="form__group">
                    <input
                        type="text"
                        name="sku"
                        id="sku"
                        class="form__text"
                    />
                    <label class="form__label form__label--floating" for="sku">
                        SKU
                    </label>
                </p>

                <p class="form__group">
                    <input
                        type="text"
                        name="external_download_link"
                        id="external_download_link"
                        class="form__text"
                    />
                    <label class="form__label form__label--floating" for="external_download_link">
                        External Download Link
                    </label>
                </p>

                <p class="form__group">
                    <input type="hidden" name="anon" value="0" />
                    <input
                        type="checkbox"
                        class="form__checkbox"
                        id="anon"
                        name="anon"
                        value="1"
                        @checked(old('anon'))
                    />
                    <label class="form__label" for="anon">{{ __('common.anonymous') }}?</label>
                </p>

                @if (auth()->user()->group->is_modo || auth()->user()->group->is_internal)
                    <p class="form__group">
                        <select name="free" id="free" class="form__select">
                            <option
                                value="0"
                                @selected(old('free') === '0' || old('free') === null)
                            >
                                {{ __('common.no') }}
                            </option>
                            <option value="25" @selected(old('free') === '25')>25%</option>
                            <option value="50" @selected(old('free') === '50')>50%</option>
                            <option value="75" @selected(old('free') === '75')>75%</option>
                            <option value="100" @selected(old('free') === '100')>100%</option>
                        </select>
                        <label class="form__label form__label--floating" for="free">
                            {{ __('torrent.freeleech') }}
                        </label>
                    </p>
                @endif

                <p class="form__group">
                    <button
                        type="submit"
                        class="form__button form__button--filled"
                        name="post"
                        value="true"
                        id="post"
                    >
                        {{ __('common.submit') }}
                    </button>
                </p>
            </form>
        </div>
    </section>
@endsection

@if ($user->can_upload == 1 && $user->group->can_upload == 1)
    @section('sidebar')
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-info"></i>
                {{ __('common.info') }}
            </h2>
            <div class="panel__body">
                <p>
                    {{ __('torrent.announce-url') }}:
                    <a
                        x-data="upload"
                        data-announce-url="{{ route('announce', ['passkey' => $user->passkey]) }}"
                        x-on:click.prevent="copy"
                        href="{{ route('announce', ['passkey' => $user->passkey]) }}"
                    >
                        {{ route('announce', ['passkey' => $user->passkey]) }}
                    </a>
                </p>
                <p>
                    {{ __('torrent.announce-url-desc', ['source' => config('torrent.source')]) }}
                </p>
                <p class="text-success">
                    {!! __('torrent.announce-url-desc-url', ['url' => config('other.upload-guide_url')]) !!}
                </p>
            </div>
        </section>
    @endsection
@endif

@section('javascripts')
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('upload', () => ({
                copy() {
                    navigator.clipboard.writeText(this.$el.dataset.announceUrl);
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        icon: 'success',
                        title: 'Copied to clipboard!',
                    });
                },
            }));
        });
    </script>
@endsection
