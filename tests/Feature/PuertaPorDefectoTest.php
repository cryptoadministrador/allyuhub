<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\ResourceVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA PUERTA FALLA CERRADA.
 *
 * `resources.origen` dejó de ser metadato descriptivo el día que
 * `scopePublished()` empezó a bifurcarse sobre él: desde entonces ES el control
 * de acceso. Y un control de acceso que nace permisivo no se salta atacándolo,
 * se salta olvidando una línea.
 *
 * El escenario que esto impide: alguien escribe el sembrador de la Fase 2 y
 * omite `'origen' => Resource::GENERADO`. El simulador funciona, se ve, y nadie
 * lo echa en falta — porque un test que tampoco declara la procedencia lo ve
 * publicado y afirma «los recursos publicados se sirven», que suena correcto.
 *
 * Por eso la propiedad se fija sobre el DEFAULT de la columna y no sobre lo que
 * escriba un sembrador concreto: lo que se protege es que OLVIDARSE no publique.
 */
class PuertaPorDefectoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_recurso_que_no_declara_procedencia_nace_sujeto_a_firma(): void
    {
        $recurso = $this->recursoSinDeclararProcedencia();

        $this->assertSame(
            Resource::GENERADO,
            $recurso->origen,
            'La columna que gobierna la puerta tiene que nacer en el valor que EXIGE firma.',
        );
    }

    public function test_olvidar_la_procedencia_no_publica_contenido_sin_firmar(): void
    {
        $recurso = $this->recursoSinDeclararProcedencia();

        $this->assertFalse(
            Resource::published()->whereKey($recurso->id)->exists(),
            'Un recurso sin firmar no puede colarse por no haber declarado su procedencia.',
        );

        $this->get("/recurso/{$recurso->id}")->assertNotFound();
    }

    public function test_en_cuanto_se_firma_aparece(): void
    {
        $recurso = $this->recursoSinDeclararProcedencia();

        $recurso->currentVersion->update(['reviewed_at' => now()]);

        $this->assertTrue(
            Resource::published()->whereKey($recurso->fresh()->id)->exists(),
            'La puerta retiene lo no firmado; no es un muro. Sin este caso seria un muro y nadie se enteraria.',
        );
    }

    /** Un recurso publicado cuya creación NO menciona `origen`: manda el default. */
    private function recursoSinDeclararProcedencia(): Resource
    {
        $recurso = Resource::create([
            'slug' => 'recurso-sin-declarar-procedencia',
            'kind' => Resource::SIMULACION,
            'title' => ['es' => 'Simulador que no ha leido nadie'],
            'status' => 'published',
        ]);

        $version = ResourceVersion::create([
            'resource_id' => $recurso->id,
            'semver' => '1.0.0',
            'published_at' => now(),
            'reviewed_at' => null,
        ]);
        $recurso->update(['current_version_id' => $version->id]);

        return $recurso->fresh();
    }
}
