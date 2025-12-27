@extends('layout.default')

@section('content')
    <section class="panelV2">
        <header class="panel__header">
            <h2 class="panel__heading">Manage Users for Torrent: {{ $torrent->name }}</h2>
            <div class="panel__actions">
                <div class="panel__action">
                    <div class="form__group">
                        <form action="{{ route('torrents.attachUser', $torrent) }}" method="POST" class="form__group form__group--horizontal" style="display: flex; align-items: center;">
                            @csrf
                            @method('POST')
                            <label for="user_id" class="form__label" style="margin-right: 10px;">Select User:</label>
                            <select name="user_id" id="user_id" class="form__select" style="margin-right: 10px;">
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->username }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="form__button form__button--filled form__button--centered">Attach</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <div class="panel__body">
            <h3>Attached Users</h3>
            <div class="data-table-wrapper">
                <table class="data-table">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($torrent->attachedUsers as $user)
                    <tr>
                        <td>
                            <x-user_tag :user="$user" :anon="false"></x-user_tag>
                        </td>
                        <td>
                            <form action="{{ route('torrents.detachUser', $torrent) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('POST')
                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                <button type="submit" class="form__button form__button--filled form__button--centered">Detach</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </section>
@endsection
