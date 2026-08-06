@extends('layouts.admin')

@section('title')
    Permission Management
@endsection

@section('content-header')
    <h1>Permission Management<small>Give staff members access to parts of the admin area.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li class="active">Permission Management</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Staff roles</h3>
                    <div class="box-tools">
                        <span class="label {{ $active ? 'label-success' : 'label-danger' }}">
                            {{ $active ? 'Enabled' : 'Disabled' }}
                        </span>
                        <form action="{{ route('admin.addons.permissions.toggle') }}" method="POST" style="display:inline">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-xs {{ $active ? 'btn-warning' : 'btn-success' }}">
                                {{ $active ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                        <a href="{{ route('admin.addons.permissions.new') }}" class="btn btn-xs btn-primary">Create role</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>Role</th>
                                <th>Sections</th>
                                <th class="text-center">Members</th>
                                <th class="text-center">Actions</th>
                            </tr>
                            @forelse ($roles as $role)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.addons.permissions.view', $role->id) }}">
                                            <span class="label label-{{ $role->color }}">{{ $role->name }}</span>
                                        </a>
                                    </td>
                                    <td>
                                        @forelse ($role->permissions ?? [] as $permission)
                                            <span class="label label-default">{{ $sections[$permission]['label'] ?? $permission }}</span>
                                        @empty
                                            <span class="text-muted">No access granted yet.</span>
                                        @endforelse
                                    </td>
                                    <td class="text-center">{{ $role->users_count }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.addons.permissions.view', $role->id) }}" class="btn btn-xs btn-primary">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        No staff roles yet. Create one to hand out partial admin access.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <p class="text-muted small no-margin">
                        Assign a role on a user's page under <strong>Users &rarr; view &rarr; Permissions</strong>.
                        Root administrators always keep full access, and only they can open this screen.
                        Staff members can never edit an administrator account or grant administrator status.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
