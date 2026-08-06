@extends('layouts.admin')

@section('title')
    Version Manager
@endsection

@section('content-header')
    <h1>Version Manager<small>Let users install server cores from the panel.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li class="active">Version Manager</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.addons.versions.update') }}" method="POST">
    {!! csrf_field() !!}

    <div class="row">
        <div class="col-sm-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Available cores</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>Core</th>
                                <th style="width: 140px;">Available</th>
                            </tr>
                            @foreach ($cores as $key => $core)
                                <tr>
                                    <td>
                                        <strong>{{ $core['name'] }}</strong>
                                        <span class="label label-default">{{ $categories[$core['category']] ?? $core['category'] }}</span>
                                        <p class="text-muted small no-margin">{{ $core['description'] }}</p>
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <select name="cores[{{ $key }}]" class="form-control input-sm">
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
                        When enabled, a <strong>Versions</strong> tab appears on every server for users holding the
                        file create permission. Installing a core downloads the jar over the server's current jar file
                        (the <code>SERVER_JARFILE</code> variable), so the server should be stopped first.
                    </p>
                    <p class="text-muted small no-margin">
                        Version lists are fetched from the official APIs (PaperMC, PurpurMC, Mojang, FabricMC, Forge)
                        and cached for 30 minutes.
                    </p>
                </div>
                <div class="box-footer">
                    <a href="{{ route('admin.addons.manager.toggle', 'versions') }}"
                       class="btn btn-sm {{ $active ? 'btn-warning' : 'btn-success' }}"
                       onclick="event.preventDefault(); document.getElementById('toggle-versions').submit();">
                        {{ $active ? 'Disable addon' : 'Enable addon' }}
                    </a>
                    <button type="submit" name="flush" value="1" class="btn btn-sm btn-default pull-right">Refresh version cache</button>
                </div>
            </div>
        </div>
    </div>
</form>

<form id="toggle-versions" action="{{ route('admin.addons.manager.toggle', 'versions') }}" method="POST" style="display:none">
    {!! csrf_field() !!}
</form>
@endsection
