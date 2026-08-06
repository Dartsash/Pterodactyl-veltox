@extends('layouts.admin')

@section('title')
    Addon - {{ $addon->name }}
@endsection

@section('content-header')
    <h1>{{ $addon->name }}<small>Configure this addon.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li><a href="{{ route('admin.addons.manage') }}">Plugin Manager</a></li>
        <li class="active">{{ $addon->name }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <form action="{{ route('admin.addons.view', $addon->id) }}" method="POST">
                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Addon Settings</h3></div>
                    <div class="box-body">
                        @include('admin.addons._fields', ['addon' => $addon])
                    </div>
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        {!! method_field('PATCH') !!}
                        <button type="submit" class="btn btn-primary pull-right">Save</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-md-4">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Visibility</h3></div>
                <div class="box-body">
                    <p>Status:
                        @if ($addon->enabled)<span class="label label-success">Enabled</span>@else<span class="label label-danger">Disabled</span>@endif
                    </p>
                    <form action="{{ route('admin.addons.toggle', $addon->id) }}" method="POST">
                        {!! csrf_field() !!}
                        @if ($addon->enabled)
                            <button type="submit" class="btn btn-warning btn-block">Disable (hide from all servers)</button>
                        @else
                            <button type="submit" class="btn btn-success btn-block">Enable</button>
                        @endif
                    </form>
                </div>
            </div>
            <div class="box box-danger">
                <div class="box-header with-border"><h3 class="box-title">Delete</h3></div>
                <div class="box-body">
                    <form action="{{ route('admin.addons.view', $addon->id) }}" method="POST" onsubmit="return confirm('Delete this addon permanently?')">
                        {!! csrf_field() !!}
                        {!! method_field('DELETE') !!}
                        <button type="submit" class="btn btn-danger btn-block">Delete addon</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
