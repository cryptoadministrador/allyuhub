<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CurriculumSeeder::class);
        // Los marcos internacionales van después de EC-MINEDEC porque el crosswalk
        // necesita las dos puntas ya sembradas.
        $this->call(InternationalFrameworksSeeder::class);
        $this->call(CrosswalkSeeder::class);
        $this->call(PracticeItemSeeder::class);

        // ORD deja de estar vacío: fases por subnivel con SUS destrezas. Va al
        // final porque necesita el grafo entero, y es idempotente — se puede
        // repetir después del importador oficial para recoger lo nuevo.
        //
        // La salida se reenvía a la consola del seeder: este comando RETIRA las
        // fases-grado vacías del seeder, y en un despliegue eso tiene que verse
        // en el log en vez de ocurrir en silencio (auditoría).
        Artisan::call('curriculo:fases-ord', [], $this->command?->getOutput());
    }
}
