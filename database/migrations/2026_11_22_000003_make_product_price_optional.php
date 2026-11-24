<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Catalogue mode: products can be added without a price (customers use "Ask for price"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('price')->nullable()->change();
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('products')->whereNull('price')->update(['price' => 0]);

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('price')->nullable(false)->default(0)->change();
        });
    }
};
