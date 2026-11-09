@extends('auth.layout')

@section('title', 'Cambiar contraseña')

@section('content')
    <p>Tu contraseña es temporal. Elige una nueva para continuar.</p>

    <form method="POST" action="{{ route('password') }}">
        @csrf
        <label for="password">Nueva contraseña</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required autofocus>
        @error('password')
            <p class="error">{{ $message }}</p>
        @enderror

        <label for="password_confirmation">Repite la nueva contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

        <button type="submit">Guardar y continuar</button>
    </form>
@endsection
