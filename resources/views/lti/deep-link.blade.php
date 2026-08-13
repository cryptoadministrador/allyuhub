<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AllyuHub — elegir contenido</title>
</head>
<body>
    <h1>AllyuHub</h1>
    <p>Elige el contenido que quieres incrustar en tu curso de Moodle.</p>

    <h2>Laboratorios y simuladores</h2>
    @forelse ($sims as $sim)
        <form method="POST" action="{{ url('/lti/deep-link') }}">
            <input type="hidden" name="type" value="resource">
            <input type="hidden" name="id" value="{{ $sim->id }}">
            <button type="submit">{{ $sim->title['es'] ?? $sim->slug }}</button>
        </form>
    @empty
        <p>No hay simuladores publicados todavía.</p>
    @endforelse

    <h2>Destrezas con práctica</h2>
    @forelse ($objectives as $objective)
        <form method="POST" action="{{ url('/lti/deep-link') }}">
            <input type="hidden" name="type" value="objective">
            <input type="hidden" name="id" value="{{ $objective->id }}">
            <button type="submit">
                {{ $objective->native_code }} — {{ \Illuminate\Support\Str::limit($objective->statement['es'] ?? '', 80) }}
            </button>
        </form>
    @empty
        <p>No hay destrezas con ítems de práctica todavía.</p>
    @endforelse
</body>
</html>
