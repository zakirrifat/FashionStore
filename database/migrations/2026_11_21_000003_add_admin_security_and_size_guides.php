<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two-step sign-in (authenticator app) for the admin panel. Stored encrypted.
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });

        Schema::create('size_guides', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // e.g. "Women's jeans"
            $table->longText('body')->nullable();   // rich text with a size table
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('size_guide_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('size_guide_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropConstrainedForeignId('size_guide_id'));
        Schema::table('categories', fn (Blueprint $t) => $t->dropConstrainedForeignId('size_guide_id'));
        Schema::dropIfExists('size_guides');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']));
    }
};
