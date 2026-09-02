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
        // El MCER: el marco de los cursos de idiomas (FR/IT/DE/ZH). Entra
        // verificado y citado — sus descriptores son públicos, al revés que
        // los syllabus de CAIE/IB.
        $this->call(CefrSeeder::class);
        $this->call(CrosswalkSeeder::class);

        // Lo ESTRUCTURAL antes que el contenido. `curriculo:fases-ord` iba al
        // final, detrás del seeder de ítems — y cuando ese seeder abortó en
        // producción (el código replicado que se creía ambiguo), las fases del
        // track ORD se quedaron vacías desde el primer día sin que nadie lo
        // viera: el error de arriba tapaba el silencio de abajo. Las fases
        // solo necesitan el grafo, que ya está; los ítems van después, y si
        // fallan, fallan ELLOS solos y con las fases ya en pie.
        //
        // La salida se reenvía a la consola del seeder: retirar fases vacías
        // tiene que verse en el log de un despliegue, no ocurrir en silencio.
        Artisan::call('curriculo:fases-ord', [], $this->command?->getOutput());

        $this->call(PracticeItemSeeder::class);
    }
}
