@extends('layout.default')

@section('page', 'page__home')

@section('main')
    @include('blocks.news')
    @livewire('top-torrents')
    @include('blocks.latest_topics')
    @include('blocks.latest_posts')
    @include('blocks.online')
@endsection
