<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ArticleCategorySeeder::class,
            EventSeeder::class,
            ArticleSeeder::class,
            SpeakerSeeder::class,
            MaterialSeeder::class,
            EventBankAccountSeeder::class,
            RegistrationSeeder::class,
        ]);
    }
}