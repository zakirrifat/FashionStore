<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot of what was asked about, kept even if the product is later deleted
            $table->string('product_name');
            $table->string('colour')->nullable();
            $table->string('size')->nullable();
            $table->string('sku')->nullable();

            // Customer
            $table->string('name');
            $table->string('phone', 40);
            $table->string('email')->nullable();
            $table->text('message')->nullable();

            // Follow-up
            $table->string('status', 20)->default('new')->index(); // new | contacted | closed
            $table->text('admin_note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_enquiries');
    }
};
