<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AllyuHub</title>
    {{-- Vista mínima a propósito: el frontend real (Inertia+React) llegará después. --}}
</head>
<body>
    <h1>AllyuHub</h1>
    <p>Hola, {{ $user->name }}.</p>

    @if ($objective)
        <h2>Destreza {{ $objective->native_code }}</h2>
        <p>{{ $objective->statement['es'] ?? '' }}</p>

        @if ($practiceUrl)
            {{-- El user_id es el de la SESIÓN LTI, nunca del payload de Moodle. --}}
            <p><a href="{{ $practiceUrl }}">Practicar esta destreza</a></p>
        @else
            <p>Esta destreza aún no tiene ítems de práctica.</p>
        @endif
    @elseif ($resource)
        <h2>{{ $resource->title['es'] ?? $resource->slug }}</h2>
        @if ($bundleUrl)
            <p><a href="{{ $bundleUrl }}">Abrir el laboratorio</a></p>
        @else
            <p>Este recurso aún no tiene una versión publicada con bundle.</p>
        @endif
    @else
        <p>Lanzamiento LTI correcto. El docente aún no eligió contenido (Deep Linking).</p>
    @endif
</body>
</html>
