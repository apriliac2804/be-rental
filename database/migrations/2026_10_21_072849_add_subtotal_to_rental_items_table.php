<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            // Simpan subtotal (qty × price_per_day × duration_days) agar tidak
            // perlu join ke rentals setiap kali menampilkan item
            $table->decimal('subtotal', 14, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropColumn('subtotal');
        });
    }
};
