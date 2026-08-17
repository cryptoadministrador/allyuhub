<!DOCTYPE html>
{{--
    La página de "vuelve desde Moodle". Es Blade puro a propósito (sin sesión no
    hay Inertia que valga) y AUTOCONTENIDA: nada de @vite — si el build fallara,
    esta página es justo la que no puede caerse. Es además lo primero que ve
    cualquiera que llegue al dominio directo: lleva la marca (los mismos tokens
    de resources/css/app.css) aunque viva fuera del pipeline.
--}}
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AllyuHub — entra desde tu curso</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { --marca: #4f46e5; --tinta: #0f172a; --gris: #475569; }
        * { box-sizing: border-box; margin: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: var(--tinta); background: #f8fafc; min-height: 100vh;
            display: flex; flex-direction: column;
        }
        header {
            padding: 1rem 1.5rem; background: #fff; border-bottom: 1px solid #e2e8f0;
        }
        .marca { font-weight: 700; font-size: 1.125rem; color: var(--tinta); }
        .marca span { color: var(--marca); }
        main {
            flex: 1; display: flex; align-items: center; justify-content: center;
            padding: 2rem 1.5rem;
        }
        .tarjeta { max-width: 26rem; text-align: center; }
        .tarjeta svg { margin-bottom: 1.25rem; }
        h1 { font-size: 1.375rem; margin-bottom: .75rem; }
        p { color: var(--gris); line-height: 1.6; margin-bottom: 1.5rem; }
        .boton {
            display: inline-block; background: var(--marca); color: #fff;
            padding: .625rem 1.25rem; border-radius: .5rem; font-weight: 600;
            text-decoration: none;
        }
        .boton:hover { background: #4338ca; }
        .boton:focus-visible { outline: 2px solid var(--marca); outline-offset: 2px; }
        footer {
            padding: 1rem 1.5rem; text-align: center; color: #94a3b8; font-size: .8125rem;
        }
    </style>
</head>
<body>
    <header>
        <p class="marca">Allyu<span>Hub</span></p>
    </header>
    <main>
        <div class="tarjeta">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" aria-hidden="true"
                 stroke="#4f46e5" stroke-width="1.5" style="display:inline">
                <rect x="3" y="11" width="18" height="10" rx="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <h1>Entra desde tu curso de Moodle</h1>
            <p>
                AllyuHub se abre desde el aula virtual de tu colegio: entra a tu curso,
                abre la actividad de AllyuHub y tu sesión se creará sola. Si estabas
                practicando y ves esta página, tu sesión caducó — vuelve a abrir la
                actividad y sigues donde ibas.
            </p>
            <a class="boton" href="https://e-learnium.edu.ec/">Ir al aula virtual</a>
        </div>
    </main>
    <footer>AllyuHub · plataforma educativa</footer>
</body>
</html>
