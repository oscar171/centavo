<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events are intentionally left enabled so the UUID-generation hooks
     * on the domain models fire while seeding.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
