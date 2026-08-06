@extends('layouts.admin')

@section('title')
    Announcement
@endsection

@section('content-header')
    <h1>Announcement<small>Show a notification on top of the client dashboard.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li class="active">Announcement</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.addons.announcement.update') }}" method="POST">
    {!! csrf_field() !!}

    <div class="row">
        <div class="col-sm-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Message</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label class="control-label">Title</label>
                        <input type="text" name="title" class="form-control" maxlength="120"
                               value="{{ old('title', $title) }}" placeholder="Maintenance tonight" />
                        <p class="text-muted small">Optional. Shown in bold on the first line of the banner.</p>
                    </div>

                    <div class="form-group no-margin">
                        <label class="control-label">Text</label>
                        <textarea name="message" class="form-control" rows="5" maxlength="2000"
                                  placeholder="Write anything you want here...">{{ old('message', $message) }}</textarea>
                        <p class="text-muted small">This is the text every user sees above the greeting on the dashboard. Line breaks are kept.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Settings</h3>
                    <div class="box-tools">
                        <span class="label {{ $active ? 'label-success' : 'label-danger' }}">
                            {{ $active ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label class="control-label">Status</label>
                        <select name="enabled" class="form-control">
                            <option value="1" @if ($active) selected="selected" @endif>Enabled</option>
                            <option value="0" @if (!$active) selected="selected" @endif>Disabled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="control-label">Style</label>
                        <select name="type" class="form-control">
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}" @if ($type === $key) selected="selected" @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="control-label">Can be dismissed</label>
                        <select name="dismissible" class="form-control">
                            <option value="1" @if ($dismissible) selected="selected" @endif>Yes</option>
                            <option value="0" @if (!$dismissible) selected="selected" @endif>No</option>
                        </select>
                        <p class="text-muted small">Editing the text makes a dismissed banner appear again.</p>
                    </div>

                    <div class="form-group no-margin">
                        <label class="control-label">Audience</label>
                        <select name="admin_only" class="form-control">
                            <option value="0" @if (!$adminOnly) selected="selected" @endif>Everyone</option>
                            <option value="1" @if ($adminOnly) selected="selected" @endif>Administrators only</option>
                        </select>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-primary pull-right">Save</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
