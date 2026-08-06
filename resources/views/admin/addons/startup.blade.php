@extends('layouts.admin')

@section('title')
    Startup Editor
@endsection

@section('content-header')
    <h1>Startup Editor<small>Control which startup options server owners may change.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li class="active">Startup Editor</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.addons.startup.update') }}" method="POST" id="pStartupEditorForm">
    {!! csrf_field() !!}

    <div class="row">
        <div class="col-sm-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Addon</h3>
                    <div class="box-tools">
                        <span class="label label-primary">build v4</span>
                        <span class="label label-default">{{ $enabledCount }} / {{ $totalCount }} options available</span>
                    </div>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Status</label>
                                <select name="enabled" class="form-control" style="max-width: 260px;">
                                    <option value="1" @if ($active) selected="selected" @endif>Enabled</option>
                                    <option value="0" @if (!$active) selected="selected" @endif>Disabled</option>
                                </select>
                                <p class="text-muted small">When disabled, the Startup Command Editor disappears from every server page and the API rejects any change.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Manual mode (administrators only)</label>
                                <select name="manual" class="form-control" style="max-width: 260px;">
                                    <option value="1" @if ($manual) selected="selected" @endif>Allowed</option>
                                    <option value="0" @if (!$manual) selected="selected" @endif>Blocked</option>
                                </select>
                                <p class="text-muted small">Manual mode lets an administrator type a raw startup command. It is still validated (must start with java, contain -jar, no shell characters, heap within the server limit). Normal users never see it.</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-group no-margin">
                        <label class="control-label">Available options</label>
                        <p class="text-muted small">
                            Only options set to <strong>Shown</strong> appear for server owners in automatic mode.
                            Hidden options are ignored even if they are sent straight to the API.
                        </p>
                        <button type="button" class="btn btn-xs btn-default" id="pShowAll">Show all</button>
                        <button type="button" class="btn btn-xs btn-default" id="pHideAll">Hide all</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Options</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                            @foreach ($options as $key => $option)
                                <tr>
                                    <td>
                                        <strong>{{ $option[0] }}</strong>
                                        <br />
                                        <code>{{ $key }}</code>
                                        <p class="text-muted small no-margin">{{ $option[1] }}</p>
                                    </td>
                                    <td style="width: 130px; vertical-align: middle;">
                                        <select name="options[{{ $key }}]" class="form-control input-sm pStartupOption">
                                            <option value="1" @if (!empty($checked[$key])) selected="selected" @endif>Shown</option>
                                            <option value="0" @if (empty($checked[$key])) selected="selected" @endif>Hidden</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-primary pull-right">Save</button>
                    <a href="{{ route('admin.addons') }}" class="btn btn-sm btn-default">Back to addons</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            function setAll(value) {
                var selects = document.getElementsByClassName('pStartupOption');

                for (var i = 0; i < selects.length; i++) {
                    selects[i].value = value;
                }
            }

            var showAll = document.getElementById('pShowAll');
            var hideAll = document.getElementById('pHideAll');

            if (showAll) {
                showAll.addEventListener('click', function () { setAll('1'); });
            }

            if (hideAll) {
                hideAll.addEventListener('click', function () { setAll('0'); });
            }
        })();
    </script>
@endsection
