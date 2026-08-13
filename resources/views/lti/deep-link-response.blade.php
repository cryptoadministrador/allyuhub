<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>AllyuHub — volviendo a Moodle…</title>
</head>
<body>
    {{-- DeepLinkingResponse: formulario auto-enviado al return_url de la Platform. --}}
    <form id="dl-response" method="POST" action="{{ $returnUrl }}">
        <input type="hidden" name="JWT" value="{{ $jwt }}">
    </form>
    <script>document.getElementById('dl-response').submit();</script>
    <noscript>
        <p>JavaScript está desactivado: pulsa para volver a Moodle.</p>
        <button form="dl-response" type="submit">Continuar</button>
    </noscript>
</body>
</html>
