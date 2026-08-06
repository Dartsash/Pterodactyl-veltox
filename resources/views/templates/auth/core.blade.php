@extends('templates/wrapper', [
    // See base/core.blade.php - the background lives on <html>, not <body>.
    'css' => ['body' => '']
])

@section('container')
    <div id="app"></div>
@endsection
