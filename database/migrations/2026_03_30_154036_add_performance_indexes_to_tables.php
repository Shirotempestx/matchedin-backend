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
            $table->index('role');
        });

        Schema::table('offres', function (Blueprint $table) {
            $table->index(['is_active', 'work_mode', 'contract_type']);
            $table->index('user_id');
            // fullText() is only supported on certain DBs, we'll use a regular index for compat or omit if purely sqlite
            // $table->fullText('title'); 
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });

        Schema::table('offres', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'work_mode', 'contract_type']);
            $table->dropIndex(['user_id']);
        });
    }
};
