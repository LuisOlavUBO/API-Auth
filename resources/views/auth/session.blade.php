@extends('auth.layout')

@section('title', 'Ya iniciaste sesión')

@section('content')
    <p>Tu sesión está iniciada como {{ $email }}.</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Cerrar sesión</button>
    </form>
@endsection
