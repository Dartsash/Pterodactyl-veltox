@extends('layouts.admin')

@section('title')
    Mod Installer
@endsection

@section('content-header')
    <h1>Mod Installer<small>Install Fabric, Forge, NeoForge and Quilt mods straight from Modrinth.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li class="active">Mod Installer</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.addons.mods.update') }}" method="POST">
    {!! csrf_field() !!}

    <div class="row">
        <div class="col-sm-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Search behaviour</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label class="control-label">Results per search</label>
                        <input type="number" name="limit" class="form-control" min="5" max="50" value="{{ $limit }}">
                        <p class="text-muted small">
                            Between 5 and 50. Modrinth is queried live and each response is cached for 30 minutes,
                            so a larger number costs nothing on repeated searches.
                        </p>
                    </div>

                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="client" value="1" @if ($allowClient) checked="checked" @endif>
                            Also offer client side only mods
                        </label>
                    </div>
                    <p class="text-muted small no-margin">
                        Off by default, and that is usually what you want: shaders, minimaps and similar mods do
                        nothing on a server and some of them crash it on startup. Turn this on only if your users
                        knowingly build modpacks whose client half lives on the server too.
                    </p>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-primary pull-right">Save</button>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">How installing works</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted small">
                        The loader and game version are detected from the server egg and its variables, and the user
                        can override both. Only builds matching that pair are offered, so a Fabric server is never
                        handed a Forge jar.
                    </p>
                    <p class="text-muted small">
                        The jar is downloaded by Wings into <code>/mods</code>. Redirects are resolved by the panel
                        first because the daemon does not follow them, and the target address is checked so it cannot
                        point at a private or internal host.
                    </p>
                    <p class="text-muted small no-margin">
                        Required dependencies are resolved one level deep, which covers the common case of Fabric API.
                        A dependency of a dependency is not fetched; when something cannot be resolved the user is told
                        which ones to add manually instead of being left with a mod that silently fails to load.
                    </p>
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
                        When enabled, a <strong>Mods</strong> tab appears on every server for users holding a file
                        permission. Browsing needs <code>file.read-content</code>, installing needs
                        <code>file.create</code>, enabling or disabling needs <code>file.update</code> and removing
                        needs <code>file.delete</code>.
                    </p>
                    <p class="text-muted small no-margin">
                        Every install, toggle and deletion is written to the server activity log.
                    </p>
                </div>
                <div class="box-footer">
                    <a href="{{ route('admin.addons.manager.toggle', 'mods') }}"
                       class="btn btn-sm {{ $active ? 'btn-warning' : 'btn-success' }}"
                       onclick="event.preventDefault(); document.getElementById('toggle-mods').submit();">
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
                        Mods need a modded server jar. On a vanilla, Paper or Spigot server the <code>/mods</code>
                        folder is ignored &mdash; install Fabric, Forge, NeoForge or Quilt first, which the Version
                        Manager addon can do.
                    </p>
                    <p class="text-muted small">
                        Mods are read only when the server starts, so a restart is required after any change here.
                    </p>
                    <p class="text-muted small no-margin">
                        Disabling renames the jar to <code>.disabled</code> instead of deleting it, so a mod can be
                        ruled out while debugging a crash and brought back afterwards. Only files inside
                        <code>/mods</code> are ever touched.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

<form id="toggle-mods" action="{{ route('admin.addons.manager.toggle', 'mods') }}" method="POST" style="display:none">
    {!! csrf_field() !!}
</form>
@endsection
