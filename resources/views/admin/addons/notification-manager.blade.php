@extends('layouts.admin')

@section('title')
    Менеджер уведомлений
@endsection

@section('content-header')
    <h1>Менеджер уведомлений<small>Создавайте и управляйте системными уведомлениями</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Админ</a></li>
        <li class="active">Уведомления</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Уведомления</h3>
                </div>
                <div class="box-body">
                    <div id="notification-manager"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof NotificationManager !== 'undefined') {
            const root = ReactDOM.createRoot(document.getElementById('notification-manager'));
            root.render(React.createElement(NotificationManager));
        }
    });
</script>
@endpush
