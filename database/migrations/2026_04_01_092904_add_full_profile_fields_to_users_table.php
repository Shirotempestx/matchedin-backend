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
            // Student missing fields
            if (!Schema::hasColumn('users', 'title')) $table->string('title')->nullable();
            if (!Schema::hasColumn('users', 'bio')) $table->text('bio')->nullable();
            if (!Schema::hasColumn('users', 'phone')) $table->string('phone')->nullable();
            if (!Schema::hasColumn('users', 'cv_url')) $table->string('cv_url')->nullable();
            if (!Schema::hasColumn('users', 'avatar_url')) $table->string('avatar_url')->nullable();
            if (!Schema::hasColumn('users', 'education_level')) $table->string('education_level')->nullable();
            if (!Schema::hasColumn('users', 'university')) $table->string('university')->nullable();
            
            // Enterprise missing fields
            if (!Schema::hasColumn('users', 'description')) $table->text('description')->nullable();
            if (!Schema::hasColumn('users', 'logo_url')) $table->string('logo_url')->nullable();
            if (!Schema::hasColumn('users', 'contact_phone')) $table->string('contact_phone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'title', 'bio', 'phone', 'cv_url', 'avatar_url', 
                'education_level', 'university', 'description', 
                'logo_url', 'contact_phone'
            ]);
        });
    }
};
