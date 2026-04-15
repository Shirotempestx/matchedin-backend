<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Etudiants', function (Blueprint $table) {
            if (!Schema::hasColumn('Etudiants', 'titre_profil')) {
                $table->string('titre_profil', 150)->nullable();
            }
            if (!Schema::hasColumn('Etudiants', 'disponibilite')) {
                $table->string('disponibilite', 120)->nullable();
            }
            if (!Schema::hasColumn('Etudiants', 'mode_travail')) {
                $table->string('mode_travail', 60)->nullable();
            }
            if (!Schema::hasColumn('Etudiants', 'lien_github')) {
                $table->string('lien_github', 255)->nullable();
            }
            if (!Schema::hasColumn('Etudiants', 'lien_linkedin')) {
                $table->string('lien_linkedin', 255)->nullable();
            }
            if (!Schema::hasColumn('Etudiants', 'lien_portfolio')) {
                $table->string('lien_portfolio', 255)->nullable();
            }
            if (!Schema::hasColumn('Etudiants', 'cv_url')) {
                $table->string('cv_url', 255)->nullable();
            }
        });

        DB::table('Etudiants')->whereNull('disponibilite')->update(['disponibilite' => 'Disponible selon opportunité']);
        DB::table('Etudiants')->whereNull('mode_travail')->update(['mode_travail' => 'Remote / Hybride / Présentiel']);
    }

    public function down(): void
    {
        Schema::table('Etudiants', function (Blueprint $table) {
            $columns = ['cv_url', 'lien_portfolio', 'lien_linkedin', 'lien_github', 'mode_travail', 'disponibilite', 'titre_profil'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('Etudiants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
