@extends('layouts.admin')

@section('title')
    Менеджер разрешений
@endsection

@section('content-header')
    <h1>Менеджер разрешений<small>Управляйте пользовательскими правами администраторов</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Админ</a></li>
        <li class="active">Разрешения</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Управление разрешениями</h3>
                </div>
                <div class="box-body">
                    <div id="permission-manager"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof PermissionManager !== 'undefined') {
            const root = ReactDOM.createRoot(document.getElementById('permission-manager'));
            root.render(React.createElement(PermissionManager));
        }
    });
</script>
@endpush
