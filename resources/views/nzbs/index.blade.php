@extends('layout.default')

@section('breadcrumbs')
    <li class="breadcrumb--active">
        NZBs
    </li>
@endsection

@section('page', 'page__staff-nzb--index')

@section('main')
<div class="page__torrents torrent-search__component">
<section class="panelV2 torrent-search__filters">
    <h2 class="panel__heading">NZB Categories</h2>
    <div class="panel__body">
        <ul class="mediahub-card__list">
            @foreach (App\Models\Category::query()->orderBy('position')->get() as $category)
            <li class="mediahub-card__list-item">
                <a
                    href="{{ route('torrents.index', ['categoryIds' => [$category->id]]) }}"
                    class="mediahub-card"
                    style="padding: 22px 16px;"
                >
                    <h2 class="mediahub-card__heading" style="font-size: 16px;">{{ $category->name }}</h2>
                    <h3 class="mediahub-card__subheading">
                        {{ $category->nzbs->count() }} NZBs
                    </h3>
                </a>
            </li>
            @endforeach
        </ul>
    </div>
</section>
    <section class="panelV2">
        <h2 class="panel__heading">NZBs</h2>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Type</th>
                        <th>{{ __('common.title') }}</th>
                        <th>{{ __('common.password') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('torrent.created_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($nzbs as $nzb)
                        <tr>
               <td> <i
                    class="{{ $nzb->category->icon }} category__icon"
                    style="font-size: 24px;"
                ></i>
</td>
                            <td>
            <span class="torrent-search--list__type">
                {{ $nzb->type->name }}
            </span>
                            </td>
                            <td><a href="{{ route('nzbs.show', $nzb) }}">{{ $nzb->title }}</a></td>
                            <td>{{ $nzb->password ? __('common.yes') : __('common.no') }}</td>
                            <td>
                                @if ($nzb->active)
                                    <span class="text-success">{{ __('common.active') }}</span>
                                @else
                                    <span class="text-danger">{{ __('common.inactive') }}</span>
                                @endif
                            </td>
                            <td>
                                <time
                                    datetime="{{ $nzb->created_at }}"
                                    title="{{ $nzb->created_at }}"
                                >
                                    {{ $nzb->created_at->diffForHumans() }}
                                </time>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">No NZBs found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $nzbs->links('partials.pagination') }}
    </section>
</div>
@endsection
