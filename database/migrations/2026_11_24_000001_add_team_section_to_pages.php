<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** "Meet Our Leadership" section shown under the About page content. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('team_section')->nullable()->after('body');
        });

        // Give the existing About page the section's starting text, so the admin only has to add people.
        DB::table('pages')->where('slug', Page::ABOUT_SLUG)->whereNull('team_section')
            ->update(['team_section' => json_encode(Page::TEAM_SECTION_DEFAULTS)]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('team_section');
        });
    }
};
