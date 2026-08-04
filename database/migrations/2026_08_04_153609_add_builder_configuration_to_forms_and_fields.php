<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('store_submissions')->default(true)->after('is_published');
        });

        Schema::table('form_fields', function (Blueprint $table) {
            $table->string('placeholder')->nullable()->after('label');
            $table->text('help_text')->nullable()->after('placeholder');
            $table->json('default_value')->nullable()->after('help_text');
            $table->string('section')->nullable()->after('validation_rules');
            $table->string('step')->nullable()->after('section');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn(['placeholder', 'help_text', 'default_value', 'section', 'step']);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('store_submissions');
        });
    }
};
