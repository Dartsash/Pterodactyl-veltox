@extends('layouts.admin')

@section('title')
    Plugin Manager
@endsection

@section('content-header')
    <h1>Plugin Manager<small>Manage the addons offered in the server marketplace.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li class="active">Plugin Manager</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Addon Catalog</h3>
                    <div class="box-tools">
                        <span class="label label-success">{{ $enabledCount }} enabled</span>
                        <span class="label label-default">{{ $disabledCount }} disabled</span>
                        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newAddonModal">Create New</button>
                    </div>
                </div>
                <div class="box-body">
                    <ul class="nav nav-pills">
                        @foreach (['All', 'Plugin', 'Mod', 'Datapack'] as $tab)
                            <li @if ($activeCategory === $tab) class="active" @endif>
                                <a href="{{ $tab === 'All' ? route('admin.addons.manage') : route('admin.addons.manage', ['category' => $tab]) }}">
                                    {{ $tab === 'All' ? 'All' : $tab . 's' }}
                                    <span class="badge">{{ $counts[$tab] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>Name</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Version</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                            @forelse ($addons as $addon)
                                <tr>
                                    <td><a href="{{ route('admin.addons.view', $addon->id) }}">{{ $addon->name }}</a></td>
                                    <td>{{ $addon->author }}</td>
                                    <td><code>{{ $addon->category }}</code></td>
                                    <td>{{ $addon->version }}</td>
                                    <td class="text-center">
                                        @if ($addon->enabled)
                                            <span class="label label-success">Enabled</span>
                                        @else
                                            <span class="label label-danger">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.addons.view', $addon->id) }}" class="btn btn-xs btn-default">Edit</a>
                                        <form action="{{ route('admin.addons.toggle', $addon->id) }}" method="POST" style="display:inline">
                                            {!! csrf_field() !!}
                                            @if ($addon->enabled)
                                                <button type="submit" class="btn btn-xs btn-warning">Disable</button>
                                            @else
                                                <button type="submit" class="btn btn-xs btn-success">Enable</button>
                                            @endif
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted" style="padding:20px">No addons in this category yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <p class="text-muted small no-margin">Disabled addons disappear from the marketplace on every server and cannot be installed. Click a name to edit its download URL and details.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="newAddonModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="{{ route('admin.addons') }}" method="POST">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true" style="color:#FFFFFF">&times;</span></button>
                        <h4 class="modal-title">Create Addon</h4>
                    </div>
                    <div class="modal-body">
                        @include('admin.addons._fields', ['addon' => null])
                    </div>
                    <div class="modal-footer">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
