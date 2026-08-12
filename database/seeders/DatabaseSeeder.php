<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

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
    }
}
