@extends('layouts.admin')

@section('title')
    Minecraft Player Manager
@endsection

@section('content-header')
    <h1>Minecraft Player Manager<small>Whitelist, operators and bans without editing JSON.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li class="active">Minecraft Player Manager</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.addons.players.update') }}" method="POST">
    {!! csrf_field() !!}

    <div class="row">
        <div class="col-sm-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Manageable lists</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>List</th>
                                <th style="width: 140px;">Available</th>
                            </tr>
                            @foreach ($lists as $key => $list)
                                <tr>
                                    <td>
                                        <strong>{{ $list['name'] }}</strong>
                                        <span class="label label-default">{{ $list['file'] }}</span>
                                        <p class="text-muted small no-margin">{{ $list['description'] }}</p>
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <select name="lists[{{ $key }}]" class="form-control input-sm">
                                            <option value="0" @if (!in_array($key, $enabled, true)) selected="selected" @endif>Hidden</option>
                                            <option value="1" @if (in_array($key, $enabled, true)) selected="selected" @endif>Available</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-primary pull-right">Save</button>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Name lookup</h3>
                </div>
                <div class="box-body">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="lookup" value="1" @if ($lookup) checked="checked" @endif>
                            Resolve UUIDs through the Mojang API
                        </label>
                    </div>
                    <p class="text-muted small no-margin">
                        Needed for servers running in online mode, so an entry keeps working after a name change.
                        Results are cached for a week. With this turned off, or when the API cannot be reached, the
                        offline mode UUID is written instead &mdash; which is the correct value for a cracked server.
                    </p>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-primary pull-right">Save</button>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Addon</h3>
                </div>
                <div class="box-body">
                    <p>
                        Status:
                        <span class="label {{ $active ? 'label-success' : 'label-danger' }}">{{ $active ? 'Enabled' : 'Disabled' }}</span>
                    </p>
                    <p class="text-muted small">
                        When enabled, a <strong>Players</strong> tab appears on every server for users holding a file
                        permission. Reading a list needs <code>file.read-content</code>, changing one needs
                        <code>file.update</code>.
                    </p>
                    <p class="text-muted small">
                        While the server is <strong>running</strong> every change is sent as the matching console
                        command (<code>whitelist add</code>, <code>op</code>, <code>ban</code>, <code>ban-ip</code>), so
                        it applies instantly and the game writes the file itself.
                    </p>
                    <p class="text-muted small no-margin">
                        While the server is <strong>stopped</strong> the JSON file is rewritten directly, keeping the
                        formatting the game uses. A file that is not valid JSON is never overwritten.
                    </p>
                </div>
                <div class="box-footer">
                    <a href="{{ route('admin.addons.manager.toggle', 'players') }}"
                       class="btn btn-sm {{ $active ? 'btn-warning' : 'btn-success' }}"
                       onclick="event.preventDefault(); document.getElementById('toggle-players').submit();">
                        {{ $active ? 'Disable addon' : 'Enable addon' }}
                    </a>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Good to know</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted small">
                        The whitelist only takes effect when <code>white-list=true</code> is set in
                        server.properties, which the Config Editor addon can change.
                    </p>
                    <p class="text-muted small no-margin">
                        Banning a player who is currently online kicks them; banning an offline player does not.
                        Banned IPs are matched by the address the player connects from, so be careful behind shared
                        or mobile networks.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

<form id="toggle-players" action="{{ route('admin.addons.manager.toggle', 'players') }}" method="POST" style="display:none">
    {!! csrf_field() !!}
</form>
@endsection
