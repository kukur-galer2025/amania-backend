<?php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use Illuminate\Database\Seeder;

class ArticleCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Kategori yang lebih general dan ramah pembaca
        $categories = [
            'Teknologi',             // ID: 1
            'Edukasi & Tutorial',    // ID: 2
            'Keamanan Digital',      // ID: 3
            'Manajemen & Bisnis',    // ID: 4
            'Karir & Produktivitas'  // ID: 5
        ];

        foreach ($categories as $category) {
            ArticleCategory::create(['name' => $category]);
        }
    }
}