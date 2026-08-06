@extends('layouts.admin')

@section('title')
    Addons
@endsection

@section('content-header')
    <h1>Addons<small>Manage the addons offered in the server marketplace.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Addons</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Addons</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Addons</th>
                                <th class="text-center">Actions</th>
                            </tr>
                            @foreach ($managers as $manager)
                                <tr>
                                    <td>
                                        <a href="{{ $manager['url'] }}">
                                            <i class="fa {{ $manager['icon'] }}"></i> {{ $manager['name'] }}
                                        </a>
                                    </td>
                                    <td class="text-muted">{{ $manager['description'] }}</td>
                                    <td class="text-center">
                                        @if ($manager['active'])
                                            <span class="label label-success">Enabled</span>
                                        @else
                                            <span class="label label-danger">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="label label-success">{{ $manager['enabled'] }} enabled</span>
                                        <span class="label label-default">{{ $manager['total'] }} total</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ $manager['url'] }}" class="btn btn-xs btn-primary">Open</a>
                                        <form action="{{ $manager['toggle_url'] }}" method="POST" style="display:inline">
                                            {!! csrf_field() !!}
                                            <button type="submit" class="btn btn-xs {{ $manager['active'] ? 'btn-warning' : 'btn-success' }}">
                                                {{ $manager['active'] ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <p class="text-muted small no-margin">Open an addon to manage its catalog and settings. Disabling an addon hides it from every client server without deleting its data.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
