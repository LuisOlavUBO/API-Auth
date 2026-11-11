@extends('auth.layout')

@section('title', $title)

@section('content')
    <p>{{ $message }}</p>

    @if ($logout ?? false)
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Cerrar sesión</button>
        </form>
    @endif
@endsection
