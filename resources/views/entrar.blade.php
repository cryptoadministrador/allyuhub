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
        .secundario { margin: 1rem 0 0; font-size: .9375rem; }
        .secundario a { color: var(--marca); }
        /* PR 20 · la puerta de los docentes, debajo y más discreta. */
        .docente {
            margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; text-align: left;
        }
        .docente h2 { font-size: 1rem; margin-bottom: .25rem; }
        .docente p { font-size: .875rem; margin-bottom: 1rem; }
        .docente label { display: block; font-size: .875rem; font-weight: 600; margin: .75rem 0 .25rem; }
        .docente input {
            width: 100%; padding: .5rem .625rem; border: 1px solid #cbd5e1; border-radius: .375rem;
            font: inherit;
        }
        .docente input:focus-visible { outline: 2px solid var(--marca); outline-offset: 1px; }
        .docente button {
            margin-top: 1rem; width: 100%; background: #fff; color: var(--marca);
            border: 1px solid var(--marca); padding: .5rem 1rem; border-radius: .5rem;
            font: inherit; font-weight: 600; cursor: pointer;
        }
        .docente button:hover { background: #eef2ff; }
        .docente button:focus-visible { outline: 2px solid var(--marca); outline-offset: 2px; }
        .error { color: #b91c1c; font-size: .875rem; margin-top: .5rem; }
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
                <path d="M21 3H3v18l9-4 9 4V3Z"/>
                <path d="M8 9h8M8 13h5"/>
            </svg>
            <h1>Entra para guardar tu avance</h1>
            <p>
                El currículo y los ejercicios de AllyuHub están abiertos: puedes verlos y
                practicar sin cuenta. Lo que pide esta página es lo otro — que lo que
                practiques <strong>se guarde</strong> y que la nota llegue a tu curso.
            </p>
            <p>
                Eso lo abre tu colegio: entra a tu aula virtual, abre la actividad de
                AllyuHub y tu sesión se creará sola. Si estabas practicando y ves esta
                página, tu sesión caducó — vuelve a abrir la actividad y sigues donde ibas.
            </p>
            <a class="boton" href="https://e-learnium.edu.ec/">Ir al aula virtual</a>
            <p class="secundario"><a href="/catalogo">Seguir explorando el currículo</a></p>

            {{-- PR 20 · Cuentas docentes creadas por consola (`docente:web`):
                 revisan y firman contenido sin un Moodle conectado. Los
                 alumnos siguen entrando desde su aula. --}}
            <section class="docente" aria-labelledby="titulo-docente">
                @auth
                    <h2 id="titulo-docente">Ya entraste como {{ auth()->user()->name }}</h2>
                    <p><a href="/docente/revisar" style="color: var(--marca)">Ir a revisar contenido</a></p>
                    <form method="POST" action="/salir">
                        @csrf
                        <button type="submit">Salir</button>
                    </form>
                @else
                    <h2 id="titulo-docente">¿Eres docente del colegio?</h2>
                    <p>Entra con la cuenta que te dio el administrador para revisar y firmar contenido.</p>
                    <form method="POST" action="/entrar" novalidate>
                        @csrf
                        <label for="email">Correo</label>
                        <input id="email" name="email" type="email" autocomplete="username" required
                               value="{{ old('email') }}"
                               @error('email') aria-invalid="true" aria-describedby="error-acceso" @enderror>
                        <label for="password">Contraseña</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                        @error('email')
                            <p id="error-acceso" class="error" role="alert">{{ $message }}</p>
                        @enderror
                        @error('password')
                            <p class="error" role="alert">{{ $message }}</p>
                        @enderror
                        <button type="submit">Entrar como docente</button>
                    </form>
                @endauth
            </section>
        </div>
    </main>
    <footer>AllyuHub · plataforma educativa</footer>
</body>
</html>
