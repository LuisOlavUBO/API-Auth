<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · UBO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <style>
        /* Tokens del template de IAM (TailAdmin) con los colores institucionales de ubo.cl. */
        :root {
            color-scheme: light dark;
            --ubo-950: #0a1636; --ubo-900: #143080; --ubo-800: #1c3b72; --ubo-700: #1d3f9e; --ubo-600: #2b53b7; --ubo-500: #1e73be;
            --celeste: #3ec6e8;
            --fondo: #ffffff; --texto: #1d2939; --suave: #667085; --borde: #d0d5dd; --campo: #ffffff;
            --primario: var(--ubo-700); --primario-hover: var(--ubo-800); --foco: rgba(43, 83, 183, .16);
            --error: #d92d20; --error-fondo: #fef3f2; --error-borde: #fda29b;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --fondo: #101828; --texto: #f2f4f7; --suave: #98a2b3; --borde: #344054; --campo: #1d2939;
                --primario: var(--ubo-600); --primario-hover: var(--ubo-700); --foco: rgba(90, 127, 214, .28);
                --error: #f97066; --error-fondo: rgba(240, 68, 56, .12); --error-borde: rgba(240, 68, 56, .35);
            }
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; background: var(--fondo); color: var(--texto); font: 16px/1.5 Outfit, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }

        .marca { display: none; position: relative; overflow: hidden; flex: 1; flex-direction: column; justify-content: space-between; padding: 3rem; color: #fff; background: linear-gradient(150deg, var(--ubo-950) 0%, var(--ubo-800) 55%, var(--ubo-500) 100%); }
        .marca::before, .marca::after { content: ''; position: absolute; border-radius: 50%; border: 1px solid rgba(255, 255, 255, .08); }
        .marca::before { width: 34rem; height: 34rem; right: -12rem; top: -10rem; }
        .marca::after { width: 26rem; height: 26rem; left: -10rem; bottom: -12rem; background: rgba(62, 198, 232, .06); }
        .marca > * { position: relative; z-index: 1; }
        .marca h2 { max-width: 26rem; margin: 0 0 .75rem; font-size: 2.25rem; line-height: 1.2; font-weight: 600; }
        .marca h2 span { color: var(--celeste); }
        .marca p { max-width: 26rem; margin: 0; color: rgba(255, 255, 255, .72); }
        .marca small { color: rgba(255, 255, 255, .55); font-size: .8rem; }

        .logo { display: block; width: 11rem; height: auto; }
        .marca .logo { width: 13rem; --logo-azul: #fff; }

        .panel { display: flex; flex: 1; flex-direction: column; padding: 1.5rem; }
        @media (prefers-color-scheme: dark) { .panel > .logo { --logo-azul: #fff; } }
        main { width: 100%; max-width: 26rem; margin: auto; padding: 2.5rem 0; }
        h1 { margin: 0 0 .5rem; font-size: 1.875rem; line-height: 1.25; font-weight: 600; }
        .subtitulo { margin: 0 0 2rem; color: var(--suave); font-size: .95rem; }
        main p { color: var(--suave); }
        footer { color: var(--suave); font-size: .8rem; text-align: center; }

        label { display: block; margin-bottom: .375rem; font-size: .875rem; font-weight: 500; }
        input { display: block; width: 100%; height: 2.75rem; margin-bottom: 1.25rem; padding: 0 1rem; font: inherit; font-size: .9rem; color: inherit; background: var(--campo); border: 1px solid var(--borde); border-radius: .5rem; box-shadow: 0 1px 2px rgba(16, 24, 40, .05); transition: border-color .15s, box-shadow .15s; }
        input::placeholder { color: var(--suave); }
        input:focus { outline: none; border-color: var(--ubo-600); box-shadow: 0 0 0 4px var(--foco); }
        button, .boton-secundario { display: flex; align-items: center; justify-content: center; gap: .6rem; width: 100%; height: 2.875rem; padding: 0 1rem; font: inherit; font-size: .9rem; font-weight: 500; border-radius: .5rem; cursor: pointer; text-decoration: none; transition: background-color .15s, border-color .15s; }
        button { margin-top: .5rem; color: #fff; background: var(--primario); border: 0; box-shadow: 0 1px 2px rgba(16, 24, 40, .05); }
        button:hover { background: var(--primario-hover); }
        button:focus-visible, .boton-secundario:focus-visible { outline: none; box-shadow: 0 0 0 4px var(--foco); }
        .boton-secundario { color: var(--texto); background: var(--campo); border: 1px solid var(--borde); }
        .boton-secundario:hover { border-color: var(--suave); }
        .error { margin: -.75rem 0 1.25rem; color: var(--error); font-size: .85rem; }
        input:has(+ .error) { border-color: var(--error); }
        .separador { display: flex; align-items: center; gap: .75rem; margin: 1.5rem 0; color: var(--suave); font-size: .85rem; }
        .separador::before, .separador::after { content: ''; flex: 1; height: 1px; background: var(--borde); }
        .boton-secundario + .error { margin: .75rem 0 0; padding: .75rem 1rem; background: var(--error-fondo); border: 1px solid var(--error-borde); border-radius: .5rem; }

        @media (min-width: 1024px) {
            .marca { display: flex; flex: 0 0 50%; }
            .panel { padding: 2rem 3rem; }
            .panel > .logo { display: none; }
        }
    </style>
</head>
<body>
    <aside class="marca">
        @include('auth.logo')
        <div>
            <h2>Una sola cuenta para <span>todos tus sistemas</span> UBO</h2>
            <p>Inicia sesión una vez y accede a las plataformas de la Universidad de forma segura.</p>
        </div>
        <small>© {{ date('Y') }} Universidad Bernardo O'Higgins</small>
    </aside>

    <div class="panel">
        @include('auth.logo')
        <main>
            <h1>@yield('title')</h1>
            @hasSection('subtitle')
                <p class="subtitulo">@yield('subtitle')</p>
            @endif
            @yield('content')
        </main>
        <footer>Acceso centralizado UBO</footer>
    </div>
</body>
</html>
