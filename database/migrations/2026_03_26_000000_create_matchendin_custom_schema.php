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
        // Tables are created in order of dependency

// <<<<<<< HEAD
// DO $$ BEGIN
//     IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'role_type') THEN
//         CREATE TYPE role_type AS ENUM ('Etudiant', 'Entreprise', 'Admin');
//     END IF;
// END $$;

// DO $$ BEGIN
//     IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'offer_type') THEN
//         CREATE TYPE offer_type AS ENUM ('Stage', 'Emploi', 'Hackathon');
//     END IF;
// END $$;

// DO $$ BEGIN
//     IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'offer_status') THEN
//         CREATE TYPE offer_status AS ENUM ('Brouillon', 'Active', 'Fermee');
//     END IF;
// END $$;

// DO $$ BEGIN
//     IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'application_status') THEN
//         CREATE TYPE application_status AS ENUM ('En_attente', 'Acceptee', 'Refusee');
//     END IF;
// END $$;

// CREATE TABLE IF NOT EXISTS "Villes" (
//     id_ville SERIAL PRIMARY KEY,
//     nom_ville VARCHAR(100) NOT NULL UNIQUE,
//     code_postal VARCHAR(10)
// );

// CREATE TABLE IF NOT EXISTS "Niveaux_Etude" (
//     id_niveau SERIAL PRIMARY KEY,
//     libelle VARCHAR(50) NOT NULL UNIQUE
// );

// CREATE TABLE IF NOT EXISTS "Competences" (
//     id_competence SERIAL PRIMARY KEY,
//     nom_competence VARCHAR(100) NOT NULL UNIQUE
// );

// CREATE TABLE IF NOT EXISTS "Utilisateurs" (
//     id_utilisateur UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
//     email VARCHAR(255) UNIQUE NOT NULL,
//     mot_de_passe VARCHAR(255) NOT NULL,
//     role role_type NOT NULL,
//     created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
// );

// CREATE TABLE IF NOT EXISTS "Etudiants" (
//     id_etudiant UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
//     id_utilisateur UUID UNIQUE REFERENCES "Utilisateurs"(id_utilisateur) ON DELETE CASCADE,
//     id_ville INT REFERENCES "Villes"(id_ville),
//     id_niveau INT REFERENCES "Niveaux_Etude"(id_niveau),
//     nom VARCHAR(100) NOT NULL,
//     prenom VARCHAR(100) NOT NULL,
//     bio TEXT
// );

// CREATE TABLE IF NOT EXISTS "Entreprises" (
//     id_entreprise UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
//     id_utilisateur UUID UNIQUE REFERENCES "Utilisateurs"(id_utilisateur) ON DELETE CASCADE,
//     id_ville INT REFERENCES "Villes"(id_ville),
//     nom_entreprise VARCHAR(150) NOT NULL,
//     secteur VARCHAR(100),
//     description TEXT
// );

// CREATE TABLE IF NOT EXISTS "Opportunites" (
//     id_opportunite UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
//     id_entreprise UUID NOT NULL REFERENCES "Entreprises"(id_entreprise) ON DELETE CASCADE,
//     id_ville INT REFERENCES "Villes"(id_ville),
//     id_niveau_requis INT REFERENCES "Niveaux_Etude"(id_niveau),
//     titre VARCHAR(150) NOT NULL,
//     description TEXT,
//     type offer_type DEFAULT 'Stage',
//     statut offer_status DEFAULT 'Active',
//     date_publication DATE DEFAULT CURRENT_DATE
// );

// CREATE TABLE IF NOT EXISTS "Etudiant_Competence" (
//     id_etudiant UUID REFERENCES "Etudiants"(id_etudiant) ON DELETE CASCADE,
//     id_competence INT REFERENCES "Competences"(id_competence) ON DELETE CASCADE,
//     niveau_maitrise INT CHECK (niveau_maitrise BETWEEN 1 AND 5),
//     PRIMARY KEY (id_etudiant, id_competence)
// );

// CREATE TABLE IF NOT EXISTS "Opportunite_Competence" (
//     id_opportunite UUID REFERENCES "Opportunites"(id_opportunite) ON DELETE CASCADE,
//     id_competence INT REFERENCES "Competences"(id_competence) ON DELETE CASCADE,
//     poids_critere INT CHECK (poids_critere BETWEEN 1 AND 10),
//     PRIMARY KEY (id_opportunite, id_competence)
// );

// CREATE TABLE IF NOT EXISTS "Candidatures" (
//     id_candidature UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
//     id_etudiant UUID REFERENCES "Etudiants"(id_etudiant) ON DELETE CASCADE,
//     id_opportunite UUID REFERENCES "Opportunites"(id_opportunite) ON DELETE CASCADE,
//     score_compatibilite INT DEFAULT 0,
//     statut application_status DEFAULT 'En_attente',
//     date_candidature TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
// );

// CREATE TABLE IF NOT EXISTS "Favoris" (
//     id_etudiant UUID REFERENCES "Etudiants"(id_etudiant) ON DELETE CASCADE,
//     id_opportunite UUID REFERENCES "Opportunites"(id_opportunite) ON DELETE CASCADE,
//     date_ajout TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
//     PRIMARY KEY (id_etudiant, id_opportunite)
// );

// CREATE INDEX IF NOT EXISTS idx_etudiant_skills ON "Etudiant_Competence"(id_etudiant);
// CREATE INDEX IF NOT EXISTS idx_opportunite_skills ON "Opportunite_Competence"(id_opportunite);
// CREATE INDEX IF NOT EXISTS idx_candidature_score ON "Candidatures"(score_compatibilite DESC);
// SQL);
// =======
        if (!Schema::hasTable('Villes')) {
            Schema::create('Villes', function (Blueprint $table) {
                $table->increments('id_ville');
                $table->string('nom_ville', 100)->unique();
                $table->string('code_postal', 10)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('Niveaux_Etude')) {
            Schema::create('Niveaux_Etude', function (Blueprint $table) {
                $table->increments('id_niveau');
                $table->string('libelle', 50)->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('Competences')) {
            Schema::create('Competences', function (Blueprint $table) {
                $table->increments('id_competence');
                $table->string('nom_competence', 100)->unique();
                $table->string('category', 50)->default('IT');
                $table->integer('weight')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('Utilisateurs')) {
            Schema::create('Utilisateurs', function (Blueprint $table) {
                $table->uuid('id_utilisateur')->primary();
                $table->string('email', 255)->unique();
                $table->string('mot_de_passe', 255);
                $table->string('role', 50); // Replacing ENUM with string for portability
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('Etudiants')) {
            Schema::create('Etudiants', function (Blueprint $table) {
                $table->uuid('id_etudiant')->primary();
                $table->uuid('id_utilisateur')->unique();
                $table->unsignedInteger('id_ville')->nullable();
                $table->unsignedInteger('id_niveau')->nullable();
                $table->string('nom', 100);
                $table->string('prenom', 100);
                $table->text('bio')->nullable();
                $table->timestamps();

                $table->foreign('id_utilisateur')->references('id_utilisateur')->on('Utilisateurs')->onDelete('cascade');
                $table->foreign('id_ville')->references('id_ville')->on('Villes');
                $table->foreign('id_niveau')->references('id_niveau')->on('Niveaux_Etude');
            });
        }

        if (!Schema::hasTable('Entreprises')) {
            Schema::create('Entreprises', function (Blueprint $table) {
                $table->uuid('id_entreprise')->primary();
                $table->uuid('id_utilisateur')->unique();
                $table->unsignedInteger('id_ville')->nullable();
                $table->string('nom_entreprise', 150);
                $table->string('secteur', 100)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('id_utilisateur')->references('id_utilisateur')->on('Utilisateurs')->onDelete('cascade');
                $table->foreign('id_ville')->references('id_ville')->on('Villes');
            });
        }

        if (!Schema::hasTable('Opportunites')) {
            Schema::create('Opportunites', function (Blueprint $table) {
                $table->uuid('id_opportunite')->primary();
                $table->uuid('id_entreprise');
                $table->unsignedInteger('id_ville')->nullable();
                $table->unsignedInteger('id_niveau_requis')->nullable();
                $table->string('titre', 150);
                $table->text('description')->nullable();
                $table->string('type', 50)->default('Stage');
                $table->string('statut', 50)->default('Active');
                $table->date('date_publication')->nullable();
                $table->timestamps();

                $table->foreign('id_entreprise')->references('id_entreprise')->on('Entreprises')->onDelete('cascade');
                $table->foreign('id_ville')->references('id_ville')->on('Villes');
                $table->foreign('id_niveau_requis')->references('id_niveau')->on('Niveaux_Etude');
            });
        }

        if (!Schema::hasTable('Etudiant_Competence')) {
            Schema::create('Etudiant_Competence', function (Blueprint $table) {
                $table->uuid('id_etudiant');
                $table->unsignedInteger('id_competence');
                $table->integer('niveau_maitrise')->nullable();
                $table->primary(['id_etudiant', 'id_competence']);

                $table->foreign('id_etudiant')->references('id_etudiant')->on('Etudiants')->onDelete('cascade');
                $table->foreign('id_competence')->references('id_competence')->on('Competences')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('Opportunite_Competence')) {
            Schema::create('Opportunite_Competence', function (Blueprint $table) {
                $table->uuid('id_opportunite');
                $table->unsignedInteger('id_competence');
                $table->integer('poids_critere')->nullable();
                $table->primary(['id_opportunite', 'id_competence']);

                $table->foreign('id_opportunite')->references('id_opportunite')->on('Opportunites')->onDelete('cascade');
                $table->foreign('id_competence')->references('id_competence')->on('Competences')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('Candidatures')) {
            Schema::create('Candidatures', function (Blueprint $table) {
                $table->uuid('id_candidature')->primary();
                $table->uuid('id_etudiant');
                $table->uuid('id_opportunite');
                $table->integer('score_compatibilite')->default(0);
                $table->string('statut', 50)->default('En_attente');
                $table->timestamps();

                $table->foreign('id_etudiant')->references('id_etudiant')->on('Etudiants')->onDelete('cascade');
                $table->foreign('id_opportunite')->references('id_opportunite')->on('Opportunites')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('Favoris')) {
            Schema::create('Favoris', function (Blueprint $table) {
                $table->uuid('id_etudiant');
                $table->uuid('id_opportunite');
                $table->timestamps();
                $table->primary(['id_etudiant', 'id_opportunite']);

                $table->foreign('id_etudiant')->references('id_etudiant')->on('Etudiants')->onDelete('cascade');
                $table->foreign('id_opportunite')->references('id_opportunite')->on('Opportunites')->onDelete('cascade');
            });
        }
// >>>>>>> mustapha
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Favoris');
        Schema::dropIfExists('Candidatures');
        Schema::dropIfExists('Opportunite_Competence');
        Schema::dropIfExists('Etudiant_Competence');
        Schema::dropIfExists('Opportunites');
        Schema::dropIfExists('Entreprises');
        Schema::dropIfExists('Etudiants');
        Schema::dropIfExists('Utilisateurs');
        Schema::dropIfExists('Competences');
        Schema::dropIfExists('Niveaux_Etude');
        Schema::dropIfExists('Villes');
    }
};
