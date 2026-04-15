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
            if (!Schema::hasColumn('users', 'subscription_tier')) {
                $table->string('subscription_tier')->nullable()->after('status');
            }
        });

        Schema::table('offres', function (Blueprint $table) {
            if (!Schema::hasColumn('offres', 'niveau_etude')) {
                $table->string('niveau_etude')->nullable()->after('contract_type');
            }
            if (!Schema::hasColumn('offres', 'places_demanded')) {
                $table->integer('places_demanded')->default(1)->after('contract_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('subscription_tier');
        });

        Schema::table('offres', function (Blueprint $table) {
            $table->dropColumn(['niveau_etude', 'places_demanded']);
        });
    }
};
