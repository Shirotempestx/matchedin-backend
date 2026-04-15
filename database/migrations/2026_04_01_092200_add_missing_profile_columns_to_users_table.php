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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'country')) {
                $table->string('country')->nullable();
            }
            if (!Schema::hasColumn('users', 'work_mode')) {
                $table->string('work_mode')->nullable();
            }
            if (!Schema::hasColumn('users', 'salary_min')) {
                $table->string('salary_min')->nullable();
            }
            if (!Schema::hasColumn('users', 'profile_type')) {
                $table->string('profile_type')->nullable();
            }
            if (!Schema::hasColumn('users', 'skill_ids')) {
                $table->json('skill_ids')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['country', 'work_mode', 'salary_min', 'profile_type', 'skill_ids'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
