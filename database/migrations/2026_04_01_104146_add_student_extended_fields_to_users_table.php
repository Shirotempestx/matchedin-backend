<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'availability')) {
                $table->string('availability', 120)->nullable();
            }
            if (!Schema::hasColumn('users', 'linkedin_url')) {
                $table->string('linkedin_url', 255)->nullable();
            }
            if (!Schema::hasColumn('users', 'portfolio_url')) {
                $table->string('portfolio_url', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['availability', 'linkedin_url', 'portfolio_url'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
