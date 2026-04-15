<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Competences', function (Blueprint $table) {
            if (!Schema::hasColumn('Competences', 'category')) {
                $table->string('category', 50)->default('IT');
            }
            if (!Schema::hasColumn('Competences', 'created_at')) {
                $table->timestamps();
            }
        });

        // Set all existing rows to 'IT' by default
        DB::table('Competences')->whereNull('category')->update(['category' => 'IT']);
    }

    public function down(): void
    {
        Schema::table('Competences', function (Blueprint $table) {
            if (Schema::hasColumn('Competences', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
