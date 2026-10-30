<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · UBO</title>
    <style>
        :root { color-scheme: light dark; --fondo: #f4f5f7; --tarjeta: #fff; --texto: #1f2933; --suave: #52606d; --borde: #cbd2d9; --acento: #1d4f91; --error: #b42318; }
        @media (prefers-color-scheme: dark) {
            :root { --fondo: #111827; --tarjeta: #1f2937; --texto: #f3f4f6; --suave: #9ca3af; --borde: #374151; --acento: #6aa5ee; --error: #f97066; }
        }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--fondo); color: var(--texto); font: 16px/1.5 system-ui, sans-serif; }
        main { box-sizing: border-box; width: min(100%, 24rem); margin: 1rem; padding: 2rem; background: var(--tarjeta); border: 1px solid var(--borde); border-radius: .5rem; }
        h1 { margin: 0 0 1rem; font-size: 1.4rem; }
        p { margin: 0 0 1rem; color: var(--suave); }
        label { display: block; margin-bottom: .25rem; font-weight: 600; }
        input { box-sizing: border-box; width: 100%; margin-bottom: 1rem; padding: .6rem; font: inherit; color: inherit; background: transparent; border: 1px solid var(--borde); border-radius: .25rem; }
        button { width: 100%; padding: .7rem; font: inherit; font-weight: 600; color: #fff; background: var(--acento); border: 0; border-radius: .25rem; cursor: pointer; }
        .error { color: var(--error); }
    </style>
</head>
<body>
    <main>
        <h1>@yield('title')</h1>
        @yield('content')
    </main>
</body>
</html>
