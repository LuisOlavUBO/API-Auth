@extends('auth.layout')

@section('title', 'Iniciar sesión')
@section('subtitle', 'Ingresa con tu cuenta institucional para continuar.')

@section('content')
    <form id="login" method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Correo</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nombre@ubo.cl" autocomplete="username" required autofocus>
        @error('email')
            <p class="error">{{ $message }}</p>
        @enderror

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" placeholder="Tu contraseña" autocomplete="current-password" required>
        @error('password')
            <p class="error">{{ $message }}</p>
        @enderror

        <button type="submit"><span class="spinner" aria-hidden="true"></span><span>Ingresar</span></button>
    </form>

    <script>
        {{-- Al enviar: campos de solo lectura (disabled no se enviaría), botón bloqueado y spinner. --}}
        (() => {
            const formulario = document.getElementById('login');
            const boton = formulario.querySelector('button');
            const restablecer = () => {
                formulario.classList.remove('enviando');
                formulario.querySelectorAll('input').forEach((campo) => campo.readOnly = false);
                boton.disabled = false;
                boton.lastElementChild.textContent = 'Ingresar';
            };
            formulario.addEventListener('submit', () => {
                formulario.classList.add('enviando');
                formulario.querySelectorAll('input').forEach((campo) => campo.readOnly = true);
                boton.disabled = true;
                boton.lastElementChild.textContent = 'Ingresando…';
            });
            // Al volver con "atrás", el navegador puede restaurar la página bloqueada.
            window.addEventListener('pageshow', (evento) => evento.persisted && restablecer());
        })();
    </script>

    @if (\App\Http\Controllers\Auth\MicrosoftLoginController::isEnabled())
        <p class="separador">o continúa con</p>
        <a class="boton-secundario" href="{{ route('login.microsoft') }}">
            <svg width="18" height="18" viewBox="0 0 21 21" aria-hidden="true">
                <path fill="#f25022" d="M0 0h10v10H0z"/><path fill="#7fba00" d="M11 0h10v10H11z"/><path fill="#00a4ef" d="M0 11h10v10H0z"/><path fill="#ffb900" d="M11 11h10v10H11z"/>
            </svg>
            Ingresar con Microsoft
        </a>
    @endif
    @error('microsoft')
        <p class="error">{{ $message }}</p>
    @enderror
@endsection
