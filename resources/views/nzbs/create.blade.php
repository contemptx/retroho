@extends('layout.default')

@section('title')
    <title>Upload - {{ config('other.title') }}</title>
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('nzbs.index') }}" class="breadcrumb__link">
            NZBs
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('common.upload') }}
    </li>
@endsection

@section('main')
    <section
        class="upload panelV2"
    >
        <h2 class="upload-title panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-file"></i>
            NZB
        </h2>
        <div class="panel__body">
            <form
                name="upload"
                class="upload-form form"
                id="upload-form"
                method="POST"
                action="{{ route('nzbs.store') }}"
                enctype="multipart/form-data"
>
                @csrf
                <p class="form__group">
                    <label for="torrent" class="form__label">
                        NZB {{ __('torrent.file') }}
                    </label>
                    <input
                        class="upload-form-file form__file"
                        type="file"
                        accept=".nzb"
                        name="nzb_file"
                        id="nzb_file"
                        required
                    />
                </p>
                <p class="form__group">
                    <input
                        type="text"
                        name="title"
                        id="title"
                        class="form__text"
                        value="{{ old('title') }}"
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
                        name="password"
                        id="password"
                        class="form__text"
                        value="{{ old('password') }}"
                        required
                    />
                    <label class="form__label form__label--floating" for="password">
                        Password
                    </label>
                </p>

                <p class="form__group">
                    <input
                        type="text"
                        name="sku"
                        id="sku"
                        class="form__text"
                        value="{{ old('sku') }}"
                        required
                    />
                    <label class="form__label form__label--floating" for="sku">
                        SKU
                    </label>
                </p>

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
