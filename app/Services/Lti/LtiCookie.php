<?php

namespace App\Services\Lti;

use Illuminate\Support\Facades\Cookie;
use Packback\Lti1p3\Interfaces\ICookie;

/**
 * ICookie de la librería sobre cookies de Laravel.
 *
 * La cookie de state ata el apretón OIDC al NAVEGADOR (anti-CSRF): un launch
 * forjado desde otro navegador no la tiene y muere en validateState.
 * SameSite=None + Secure porque el POST del launch llega cross-site desde
 * Moodle (y dentro de un iframe). Las rutas /lti/* van en un grupo de
 * middleware SIN EncryptCookies: el nombre de la cookie lleva el state
 * (dinámico) y no puede excluirse del cifrado por nombre.
 */
class LtiCookie implements ICookie
{
    public function getCookie(string $name): ?string
    {
        return request()->cookie($name);
    }

    public function setCookie(string $name, string $value, int $exp = 3600, array $options = []): void
    {
        Cookie::queue(Cookie::make(
            name: $name,
            value: $value,
            minutes: max(1, intdiv($exp, 60)),
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: 'none',
        ));
    }
}
