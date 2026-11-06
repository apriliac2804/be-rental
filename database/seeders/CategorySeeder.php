<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Tenda & Shelter',    'slug' => 'tenda-shelter'],
            ['name' => 'Sleeping Gear',      'slug' => 'sleeping-gear'],
            ['name' => 'Perlengkapan Masak', 'slug' => 'perlengkapan-masak'],
            ['name' => 'Carrier & Daypack',  'slug' => 'carrier-daypack'],
            ['name' => 'Penerangan',         'slug' => 'penerangan'],
            ['name' => 'Navigasi & Safety',  'slug' => 'navigasi-safety'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        $this->command->info('✅ ' . count($categories) . ' categories seeded.');
    }
}
