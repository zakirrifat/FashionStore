<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // "Standard delivery"
            $table->string('zone');                         // za | international
            $table->unsignedBigInteger('price');            // cents
            $table->unsignedBigInteger('free_over')->nullable(); // free when subtotal ≥ this (cents)
            $table->string('estimate')->nullable();         // "2–5 working days"
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();               // stored upper-case
            $table->string('type');                         // percent | fixed
            $table->unsignedBigInteger('value');            // percent (e.g. 10) or cents
            $table->unsignedBigInteger('min_subtotal')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();                // kept in a cookie for guests
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('discount_code')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'product_variant_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();             // FS-100001
            $table->string('access_token', 64);             // lets guests open their confirmation page
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('awaiting_payment');
            $table->string('email');
            $table->string('phone', 30);
            $table->json('shipping_address');               // snapshot at time of order
            $table->string('shipping_method');
            $table->string('currency', 3)->default('ZAR');
            $table->unsignedBigInteger('subtotal');         // cents, after VAT adjustment for export orders
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('gift_wrap_total')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('vat_total')->default(0); // VAT included in total
            $table->boolean('vat_exempt')->default(false);  // shipped outside SA
            $table->string('discount_code')->nullable();
            $table->boolean('gift_wrap')->default(false);
            $table->string('gift_message', 500)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('courier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot, so the order stays correct if the product changes later.
            $table->string('name');
            $table->string('colour')->nullable();
            $table->string('size')->nullable();
            $table->string('length')->nullable();
            $table->string('sku')->nullable();
            $table->string('image')->nullable();
            $table->unsignedBigInteger('unit_price');       // cents charged per item
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('shipping_methods');
    }
};
