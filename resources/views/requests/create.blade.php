@extends('layout.default')

@section('title')
    <title>{{ __('request.add-request') }} - {{ config('other.title') }}</title>
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('requests.index') }}" class="breadcrumb__link">
            {{ __('request.requests') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('common.new-adj') }}
    </li>
@endsection

@section('page', 'page__request--create')

@section('main')
    @if ($user->can_request)
        <section
            class="panelV2"
            x-data="{
                cat: {{ (int) $category_id }},
                cats: JSON.parse(atob('{{ base64_encode(json_encode($categories)) }}')),
            }"
        >
            <h2 class="panel__heading">{{ __('request.add-request') }}</h2>
            <div class="panel__body">
                <form class="form" method="POST" action="{{ route('requests.store') }}">
                    @csrf
                    <p class="form__group">
                        <input
                            id="title"
                            class="form__text"
                            name="name"
                            required
                            type="text"
                            value="{{ $title ?: old('name') }}"
                        />
                        <label class="form__label form__label--floating" for="title">
                            {{ __('request.title') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <select
                            name="category_id"
                            id="category_id"
                            class="form__select"
                            required
                            x-model="cat"
                            x-on:change="cats[cat].type = cats[$event.target.value].type;"
                        >
                            <option hidden selected disabled value=""></option>
                            @foreach ($categories as $id => $category)
                                <option
                                    class="form__option"
                                    value="{{ $id }}"
                                    @selected((old('category_id') ?: $category_id) == $id)
                                >
                                    {{ $category['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <label class="form__label form__label--floating" for="category_id">
                            {{ __('request.category') }}
                        </label>
                    </p>
                    <p class="form__group">
                        <select id="type_id" class="form__select" name="type_id" required>
                            <option hidden disabled selected value=""></option>
                            @foreach ($types as $type)
                                <option
                                    value="{{ $type->id }}"
                                    @selected(old('type_id') == $type->id)
                                >
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        <label class="form__label form__label--floating" for="type_id">
                            {{ __('request.type') }}
                        </label>
                    </p>
                    @livewire('bbcode-input', [
                        'name' => 'description',
                        'label' => __('request.description'),
                        'required' => true
                    ])
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
                    <p class="form__group">
                        <button class="form__button form__button--filled">
                            {{ __('common.submit') }}
                        </button>
                    </p>
                </form>
            </div>
        </section>
    @else
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-times text-danger"></i>
                {{ __('request.no-privileges') }}!
            </h2>
            <p class="panel__body">{{ __('request.no-privileges-desc') }}!</p>
        </section>
    @endif
@endsection

@if ($user->can_request)
    @section('sidebar')
        <section class="panelV2">
            <h2 class="panel__heading">{{ __('common.info') }}</h2>
            <div class="panel__body">
               Please be descriptive when creating a torrent request.
            </div>
        </section>
    @endsection
@endif
