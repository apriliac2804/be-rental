<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // ── Tenda & Shelter ──────────────────────────────
            [
                'category' => 'tenda-shelter',
                'name'     => 'Tenda Dome 2 Orang Rei',
                'slug'     => 'tenda-dome-2-orang-rei',
                'price'    => 75_000,
                'stock'    => 5,
                'desc'     => 'Tenda dome kapasitas 2 orang, waterproof 3000mm, berat 2.1 kg. Cocok untuk pendakian gunung dan camping keluarga.',
            ],
            [
                'category' => 'tenda-shelter',
                'name'     => 'Tenda Dome 4 Orang Coleman',
                'slug'     => 'tenda-dome-4-orang-coleman',
                'price'    => 120_000,
                'stock'    => 3,
                'desc'     => 'Tenda keluarga kapasitas 4 orang, mudah dipasang 60 detik, dilengkapi karpet bawah.',
            ],
            [
                'category' => 'tenda-shelter',
                'name'     => 'Tarp / Flysheet 3x4m',
                'slug'     => 'tarp-flysheet-3x4m',
                'price'    => 35_000,
                'stock'    => 8,
                'desc'     => 'Flysheet serbaguna ukuran 3x4 meter, bahan ripstop nylon, waterproof.',
            ],

            // ── Sleeping Gear ─────────────────────────────────
            [
                'category' => 'sleeping-gear',
                'name'     => 'Sleeping Bag Sintetik -5°C',
                'slug'     => 'sleeping-bag-sintetik-minus-5c',
                'price'    => 50_000,
                'stock'    => 10,
                'desc'     => 'Sleeping bag sintetik cocok untuk suhu hingga -5°C. Ideal untuk gunung setinggi 2000–3400 mdpl.',
            ],
            [
                'category' => 'sleeping-gear',
                'name'     => 'Sleeping Pad / Matras Gunung',
                'slug'     => 'sleeping-pad-matras-gunung',
                'price'    => 25_000,
                'stock'    => 12,
                'desc'     => 'Matras foam EVA ringan, tebal 1cm, mudah digulung dan dibawa.',
            ],

            // ── Perlengkapan Masak ────────────────────────────
            [
                'category' => 'perlengkapan-masak',
                'name'     => 'Kompor Portable Ultralight',
                'slug'     => 'kompor-portable-ultralight',
                'price'    => 40_000,
                'stock'    => 7,
                'desc'     => 'Kompor ultralight berbahan titanium, berat hanya 85g, kompatibel semua canister standar.',
            ],
            [
                'category' => 'perlengkapan-masak',
                'name'     => 'Nesting / Mess Kit Aluminium',
                'slug'     => 'nesting-mess-kit-aluminium',
                'price'    => 30_000,
                'stock'    => 6,
                'desc'     => 'Set peralatan masak 4-in-1: pot, pan, dan 2 mangkuk. Material aluminium anti lengket.',
            ],

            // ── Carrier & Daypack ─────────────────────────────
            [
                'category' => 'carrier-daypack',
                'name'     => 'Carrier 60L Deuter AirContact',
                'slug'     => 'carrier-60l-deuter-aircontact',
                'price'    => 85_000,
                'stock'    => 4,
                'desc'     => 'Carrier 60 liter dengan sistem ventilasi AirContact, cocok untuk pendakian 3–5 hari.',
            ],
            [
                'category' => 'carrier-daypack',
                'name'     => 'Daypack 25L Eiger Summit',
                'slug'     => 'daypack-25l-eiger-summit',
                'price'    => 45_000,
                'stock'    => 6,
                'desc'     => 'Daypack ringan 25 liter dengan panel back padding, cocok untuk day hike atau summit attack.',
            ],

            // ── Penerangan ────────────────────────────────────
            [
                'category' => 'penerangan',
                'name'     => 'Headlamp LED 350 Lumens',
                'slug'     => 'headlamp-led-350-lumens',
                'price'    => 20_000,
                'stock'    => 15,
                'desc'     => 'Headlamp dengan kecerahan 350 lumens, tahan air IPX4, baterai tahan 8 jam.',
            ],
            [
                'category' => 'penerangan',
                'name'     => 'Lentera Camping Coleman 200L',
                'slug'     => 'lentera-camping-coleman-200l',
                'price'    => 30_000,
                'stock'    => 5,
                'desc'     => 'Lentera LED 200 lumens, mode low/high, baterai tahan 75 jam mode low.',
            ],

            // ── Navigasi & Safety ─────────────────────────────
            [
                'category' => 'navigasi-safety',
                'name'     => 'Kompas Baseplate Silva',
                'slug'     => 'kompas-baseplate-silva',
                'price'    => 15_000,
                'stock'    => 8,
                'desc'     => 'Kompas baseplate presisi tinggi dengan penggaris dan skala 1:25000, cocok untuk orienteering.',
            ],
            [
                'category' => 'navigasi-safety',
                'name'     => 'Trekking Pole Aluminium (1 pasang)',
                'slug'     => 'trekking-pole-aluminium-1-pasang',
                'price'    => 35_000,
                'stock'    => 6,
                'desc'     => 'Trekking pole teleskopik aluminium, adjustable 60–135 cm, dengan tips tungsten carbide.',
            ],
        ];

        foreach ($products as $item) {
            $category = Category::where('slug', $item['category'])->first();
            if (! $category) continue;

            Product::firstOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id'   => $category->id,
                    'name'          => $item['name'],
                    'slug'          => $item['slug'],
                    'price_per_day' => $item['price'],
                    'stock'         => $item['stock'],
                    'description'   => $item['desc'],
                    'image'         => null,
                    'status'        => 'available',
                ]
            );
        }

        $this->command->info('✅ ' . count($products) . ' products seeded.');
    }
}
