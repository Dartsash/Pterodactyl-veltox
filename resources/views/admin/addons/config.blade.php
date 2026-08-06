@extends('layouts.admin')

@section('title')
    Config Editor
@endsection

@section('content-header')
    <h1>Config Editor<small>Choose which server.properties settings owners may change.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li class="active">Config Editor</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.addons.config.update') }}" method="POST" id="pConfigEditorForm">
    {!! csrf_field() !!}

    <div class="row">
        <div class="col-sm-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Addon</h3>
                    <div class="box-tools">
                        <span class="label label-primary">build v4</span>
                        <span class="label label-default">{{ $enabledCount }} / {{ $totalCount }} settings visible</span>
                    </div>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label class="control-label">Status</label>
                        <select name="enabled" class="form-control" style="max-width: 260px;">
                            <option value="1" @if ($active) selected="selected" @endif>Enabled</option>
                            <option value="0" @if (!$active) selected="selected" @endif>Disabled</option>
                        </select>
                        <p class="text-muted small">When disabled, the Configuration tab stops working for every server and the API rejects any change.</p>
                    </div>

                    <div class="form-group no-margin">
                        <label class="control-label">Visible settings</label>
                        <p class="text-muted small">
                            Only settings set to <strong>Shown</strong> appear for server owners. Hidden settings are
                            ignored even if they are sent straight to the API. Keys outside this list are never touched,
                            and comments in server.properties are always preserved.
                        </p>
                        <button type="button" class="btn btn-xs btn-default" id="pShowAll">Show all</button>
                        <button type="button" class="btn btn-xs btn-default" id="pHideAll">Hide all</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @foreach ($groups as $groupKey => $groupLabel)
            @php
                $groupFields = [];
                foreach ($fields as $fieldKey => $field) {
                    if (($field['group'] ?? 'general') === $groupKey) {
                        $groupFields[$fieldKey] = $field;
                    }
                }
            @endphp

            @if (count($groupFields) > 0)
                <div class="col-sm-6">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ $groupLabel }}</h3>
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover">
                                <tbody>
                                    @foreach ($groupFields as $fieldKey => $field)
                                        <tr>
                                            <td>
                                                <strong>{{ $field['label'] ?? $fieldKey }}</strong>
                                                <br />
                                                <code>{{ $fieldKey }}</code>
                                                @if (!empty($field['description']))
                                                    <p class="text-muted small no-margin">{{ $field['description'] }}</p>
                                                @endif
                                            </td>
                                            <td style="width: 130px; vertical-align: middle;">
                                                <select name="fields[{{ $fieldKey }}]" class="form-control input-sm pConfigField">
                                                    <option value="1" @if (!empty($checked[$fieldKey])) selected="selected" @endif>Shown</option>
                                                    <option value="0" @if (empty($checked[$fieldKey])) selected="selected" @endif>Hidden</option>
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="box box-primary">
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
                var selects = document.getElementsByClassName('pConfigField');

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
