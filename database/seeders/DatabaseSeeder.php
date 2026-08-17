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
        Artisan::call('curriculo:fases-ord');
    }
}
