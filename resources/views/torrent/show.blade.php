@extends('layout.default')

@section('title')
    <title>
        {{ $torrent->name }} - {{ __('torrent.torrents') }} - {{ config('other.title') }}
    </title>
@endsection

@section('meta')
    <meta
        name="description"
        content="{{ __('torrent.meta-desc', ['name' => $torrent->name]) }}!"
    />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('torrents.index') }}" class="breadcrumb__link">
            {{ __('torrent.torrents') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ $torrent->name }}
    </li>
@endsection

@section('main')
    <h1 class="torrent__name">
        {{ $torrent->name }}
    </h1>
    @include('torrent.partials.general')
    @include('torrent.partials.buttons')

    {{-- Tools Block --}}
    @if (auth()->user()->group->is_modo || (auth()->id() === $torrent->user_id && $canEdit))
        @include('torrent.partials.tools')
    @endif

    {{-- Audits Block --}}
    @if (auth()->user()->group->is_modo)
        @include('torrent.partials.audits')
        @include('torrent.partials.downloads')
    @endif

    {{-- Description Block --}}
    @include('torrent.partials.description')

    {{-- NZB Block --}}
    {{--@include('torrent.partials.nzb')--}}

    {{-- Commments Block --}}
    @include('torrent.partials.comments')
@endsection
