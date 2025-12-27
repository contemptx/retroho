@extends('layout.default')

@section('breadcrumbs')
    <li class="breadcrumb--active">
        NZBs
    </li>
@endsection

@section('page', 'page__staff-nzb--index')

@section('main')
<section class="panelV2 torrent-nzb">
    <header class="panel__header">
        <h2 class="panel__heading">
            <i class="fas fa-newspaper-o"></i>
            NZB Files
        </h2>
    </header>
    <div class="panel__body">
            @php
                $files = $nzb->metadata['files'] ?? [];
                $poster = $files[0]['poster'] ?? null;
                $dates = collect($files)->pluck('date')->filter()->map(fn($d) => (int)$d);
                $earliestDate = $dates->min() ? \Carbon\Carbon::createFromTimestamp($dates->min()) : null;
                $allGroups = collect($files)->pluck('groups')->flatten()->unique()->values()->all();
                $totalSize = collect($files)->sum(fn($file) => collect($file['segments'] ?? [])->sum('bytes') ?? 0);
                $totalSizeFormatted = App\Helpers\StringHelper::formatBytes($totalSize, 2);
            @endphp

            <div class="nzb-info">
                <div class="nzb-info__details">
                    <p><strong>Filename:</strong> {{ $nzb->filename }}</p>

                    @if($poster)
                        <p><strong>Poster:</strong> <span class="nzb-poster">{{ $poster }}</span></p>
                    @endif

                    @if($earliestDate)
                        <p><strong>Posted:</strong> {{ $earliestDate->toDayDateTimeString() }}</p>
                    @endif

                    @if(!empty($allGroups))
                        <p><strong>Groups:</strong></p>
                        <ul class="nzb-groups">
                            @foreach($allGroups as $group)
                                <li><code>{{ $group }}</code></li>
                            @endforeach
                        </ul>
                    @endif

                    @if($totalSize > 0)
                        <p><strong>Size:</strong> {{ $totalSizeFormatted }}</p>
                    @endif

                    @if($nzb->password)
                        <p><strong>Password:</strong> {{ $nzb->password }}</p>
                    @endif

                    <p><strong>Files:</strong> {{ count($files) }}</p>
                </div>

                <details class="nzb-file-list-container">
                    <summary class="nzb-file-toggle">Show File List</summary>
                    <div class="nzb-file-list">
                        @foreach($files as $index => $file)
                            <div class="nzb-file-item">
                                <span class="nzb-file-number">{{ $index + 1 }}.</span>
                                <span class="nzb-file-subject">{{ $file['subject'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </details>

                <div class="nzb-actions">
                    <a href="{{ route('nzbs.download', $nzb) }}" class="form__button form__button--filled">
                        <i class="fa fa-download"></i> Download NZB
                    </a>

                    @if(auth()->user()->group->is_owner)
                        <form method="POST" action="{{ route('nzbs.destroy', $nzb) }}" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="form__button form__button--outlined" onclick="return confirm('Are you sure?')">
                                <i class="fa fa-trash"></i> Delete NZB
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <style>
                .nzb-info {
                    background-color: rgba(0,0,0,0.03);
                    border-radius: 5px;
                    padding: .5rem;
                }

                .nzb-info p {
                    margin-bottom: 0.75rem;
                    line-height: 1.5;
                }

                .nzb-poster,
                .nzb-password {
                    font-family: monospace;
                    background-color: rgba(0,0,0,0.05);
                    padding: 0.2rem 0.4rem;
                    border-radius: 3px;
                    word-break: break-all;
                }

                .nzb-groups {
                    list-style-type: none;
                    padding-left: 1rem;
                    margin-top: -0.5rem;
                    margin-bottom: 0.75rem;
                }

                .nzb-groups li {                margin-bottom: 0.25rem;
                }

                .nzb-groups code {
                    font-family: monospace;
                    background-color: rgba(0,0,0,0.05);
                    padding: 0.2rem 0.4rem;
                    border-radius: 3px;
                }

                .nzb-file-list-container {
                    margin-top: 1rem;
                    border-top: 1px solid rgba(0,0,0,0.1);
                    padding-top: 0.75rem;
                }

                .nzb-file-toggle {
                    cursor: pointer;
                    padding: 0.5rem;
                    background-color: rgba(0,0,0,0.05);
                    border-radius: 3px;
                    display: inline-block;
                }

                .nzb-file-toggle:hover {
                    background-color: rgba(0,0,0,0.1);
                }

                .nzb-file-list {
                    margin-top: 0.75rem;
                    max-height: 200px;
                    overflow-y: auto;
                    padding: 0.5rem;
                    background-color: rgba(0,0,0,0.03);
                    border-radius: 3px;
                }

                .nzb-file-item {
                    padding: 0.25rem 0.5rem;
                    border-bottom: 1px dotted rgba(0,0,0,0.1);
                }

                .nzb-file-item:last-child {
                    border-bottom: none;
                }

                .nzb-file-number {
                    opacity: 0.7;
                    margin-right: 0.5rem;
                    font-weight: bold;
                }

                .nzb-file-subject {
                    font-family: monospace;                font-size: 0.9rem;
                    word-break: break-word;
                }

                .nzb-actions {
                    margin-top: 1rem;
                    border-top: 1px solid rgba(0,0,0,0.1);
                    padding-top: 1rem;
                    display: flex;
                    gap: 0.5rem;
                }
            </style>
    </div>
</section>
@endsection
