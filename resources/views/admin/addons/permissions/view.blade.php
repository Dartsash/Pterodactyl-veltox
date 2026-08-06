@extends('layouts.admin')

@section('title')
    {{ $role->exists ? $role->name : 'New Staff Role' }}
@endsection

@section('content-header')
    <h1>{{ $role->exists ? $role->name : 'New Staff Role' }}<small>Pick which admin sections this role may open.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li><a href="{{ route('admin.addons.permissions') }}">Permission Management</a></li>
        <li class="active">{{ $role->exists ? $role->name : 'New' }}</li>
    </ol>
@endsection

@section('content')
<form action="{{ $role->exists ? route('admin.addons.permissions.view', $role->id) : route('admin.addons.permissions.new') }}" method="POST">
    {!! csrf_field() !!}
    @if ($role->exists)
        {!! method_field('PATCH') !!}
    @endif

    <div class="row">
        <div class="col-sm-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Sections</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                            @foreach ($sections as $key => $section)
                                <tr>
                                    <td>
                                        <strong><i class="fa {{ $section['icon'] }}"></i> {{ $section['label'] }}</strong>
                                        <p class="text-muted small no-margin">{{ $section['description'] }}</p>
                                    </td>
                                    <td style="width: 150px; vertical-align: middle;">
                                        <select name="permissions[{{ $key }}]" class="form-control input-sm">
                                            <option value="0" @if (!in_array($key, $role->permissions ?? [], true)) selected="selected" @endif>No access</option>
                                            <option value="1" @if (in_array($key, $role->permissions ?? [], true)) selected="selected" @endif>Allowed</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <p class="text-muted small no-margin">
                        Access is checked on the server for every admin request, not just hidden in the sidebar.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Role</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label class="control-label">Name</label>
                        <input type="text" name="name" class="form-control" maxlength="191"
                               value="{{ old('name', $role->name) }}" placeholder="Support" required />
                    </div>
                    <div class="form-group no-margin">
                        <label class="control-label">Badge colour</label>
                        <select name="color" class="form-control">
                            @foreach ($colors as $value => $label)
                                <option value="{{ $value }}" @if (old('color', $role->color) === $value) selected="selected" @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-primary pull-right">Save</button>
                </div>
            </div>

            @if ($role->exists)
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Members</h3>
                    </div>
                    <div class="box-body">
                        @forelse ($members as $member)
                            <a href="{{ route('admin.users.view', $member->id) }}" class="label label-default" style="display:inline-block;margin:0 4px 4px 0;">
                                {{ $member->username }}
                            </a>
                        @empty
                            <p class="text-muted small no-margin">Nobody has this role yet. Assign it on a user's page.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</form>

@if ($role->exists)
    <div class="row">
        <div class="col-sm-8">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">Delete role</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted small no-margin">
                        Deleting this role removes admin access from everyone holding it. Their accounts are left untouched otherwise.
                    </p>
                </div>
                <div class="box-footer">
                    <form action="{{ route('admin.addons.permissions.view', $role->id) }}" method="POST">
                        {!! csrf_field() !!}
                        {!! method_field('DELETE') !!}
                        <button type="submit" class="btn btn-sm btn-danger pull-right">Delete role</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
