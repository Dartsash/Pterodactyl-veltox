@extends('templates/wrapper', [
    // No background class here: the Veltox gradient is painted on <html> in
    // tailwind.css. A body background would end where the content ends and
    // leave a black strip under the footer on short pages.
    'css' => ['body' => ''],
])

@section('container')
    <div id="modal-portal"></div>
    <div id="app"></div>
@endsection
