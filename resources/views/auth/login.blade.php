@extends('auth.layout')

@section('title', 'Iniciar sesión')

@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Correo</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        @error('email')
            <p class="error">{{ $message }}</p>
        @enderror

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password')
            <p class="error">{{ $message }}</p>
        @enderror

        <button type="submit">Ingresar</button>
    </form>
@endsection
