@extends('layout.default')

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('torrents.index') }}" class="breadcrumb__link">
            {{ __('torrent.torrents') }}
        </a>
    </li>
    <li class="breadcrumbV2">
        <a href="{{ route('torrents.show', ['id' => $torrent->id]) }}" class="breadcrumb__link">
            {{ $torrent->name }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('common.edit') }}
    </li>
@endsection

@section('main')
    <section
        class="panelV2"
    >
        <h2 class="panel__heading">{{ __('common.edit') }}: {{ $torrent->name }}</h2>
        <div class="panel__body">
            <form
                class="form"
                method="POST"
                action="{{ route('torrents.update', ['id' => $torrent->id]) }}"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PATCH')
                <p class="form__group">
                    <input
                        id="name"
                        type="text"
                        class="form__text"
                        name="name"
                        value="{{ old('name') ?? $torrent->name }}"
                        required
                    />
                    <label class="form__label form__label--floating" for="name">
                        {{ __('torrent.title') }}
                    </label>
                </p>
                <p class="form__group">
                    <select
                        id="category_id"
                        class="form__select"
                        name="category_id"
                    >
                        <option value="{{ old('category_id') ?? $torrent->category_id }}" selected>
                            {{ $torrent->category->name }} ({{ __('torrent.current') }})
                        </option>
                        @foreach ($categories as $id => $category)
                            <option value="{{ $id }}" @selected('category_id' === $id)>
                                {{ $category['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <label class="form__label form__label--floating" for="category_id">
                        {{ __('torrent.category') }}
                    </label>
                </p>
                <p class="form__group">
                    <select
                        id="type_id"
                        class="form__select"
                        name="type_id"
                    >
                        <option value="{{ old('type_id') ?? $torrent->type->id }}" selected>
                            {{ $torrent->type->name }} ({{ __('torrent.current') }})
                        </option>
                        @foreach ($types as $id => $type)
                            <option value="{{ $id }}" @selected(old('type_id') === $id)>
                                {{ $type['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <label class="form__label form__label--floating" for="type_id">
                        {{ __('torrent.type') }}
                    </label>
                </p>

                @livewire('bbcode-input', [
                    'name'     => 'description',
                    'label'    => __('common.description'),
                    'required' => true,
                    'content'  => $torrent->description
                ])

                <p class="form__group">
                    <input
                        type="text"
                        name="sku"
                        id="sku"
                        class="form__text"
			value="{{ $torrent->sku }}"
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
			value="{{ $torrent->external_download_link }}"
                    />
                    <label class="form__label form__label--floating" for="external_download_link">
                        External Download Link
                    </label>
                </p>

                @if (auth()->user()->group->is_modo || auth()->id() === $torrent->user_id)
                    <p class="form__group">
                        <input type="hidden" name="anon" value="0" />
                        <input
                            type="checkbox"
                            class="form__checkbox"
                            id="anon"
                            name="anon"
                            value="1"
                            @checked(old('anon') ?? $torrent->anon)
                        />
                        <label class="form__label" for="anon">{{ __('common.anonymous') }}?</label>
                    </p>
                @else
                    <input type="hidden" name="anon" value="{{ $torrent->anon }}" />
                @endif

                <p class="form__group">
                    <button class="form__button form__button--filled">
                        {{ __('common.submit') }}
                    </button>
                </p>
            </form>
        </div>
    </section>
@endsection
