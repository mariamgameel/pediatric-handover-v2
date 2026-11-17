@extends('layouts.app')

@section('content')
    <div class="error-container">
        <h1>403 Forbidden</h1>
        <p>{{ $exception?->getMessage() ?: 'You are not authorized to perform this action.' }}</p>
    </div>
@endsection
