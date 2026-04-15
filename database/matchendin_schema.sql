CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

CREATE TYPE role_type AS ENUM ('Etudiant', 'Entreprise', 'Admin');
CREATE TYPE offer_type AS ENUM ('Stage', 'Emploi', 'Hackathon');
CREATE TYPE offer_status AS ENUM ('Brouillon', 'Active', 'Fermee');
CREATE TYPE application_status AS ENUM ('En_attente', 'Acceptee', 'Refusee');

CREATE TABLE "Villes" (
    id_ville SERIAL PRIMARY KEY,
    nom_ville VARCHAR(100) NOT NULL UNIQUE,
    code_postal VARCHAR(10)
);

CREATE TABLE "Niveaux_Etude" (
    id_niveau SERIAL PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE "Competences" (
    id_competence SERIAL PRIMARY KEY,
    nom_competence VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE "Utilisateurs" (
    id_utilisateur UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email VARCHAR(255) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role role_type NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE "Etudiants" (
    id_etudiant UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    id_utilisateur UUID UNIQUE REFERENCES "Utilisateurs"(id_utilisateur) ON DELETE CASCADE,
    id_ville INT REFERENCES "Villes"(id_ville),
    id_niveau INT REFERENCES "Niveaux_Etude"(id_niveau),
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    bio TEXT
);

CREATE TABLE "Entreprises" (
    id_entreprise UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    id_utilisateur UUID UNIQUE REFERENCES "Utilisateurs"(id_utilisateur) ON DELETE CASCADE,
    id_ville INT REFERENCES "Villes"(id_ville),
    nom_entreprise VARCHAR(150) NOT NULL,
    secteur VARCHAR(100),
    description TEXT
);

CREATE TABLE "Opportunites" (
    id_opportunite UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    id_entreprise UUID NOT NULL REFERENCES "Entreprises"(id_entreprise) ON DELETE CASCADE,
    id_ville INT REFERENCES "Villes"(id_ville),
    id_niveau_requis INT REFERENCES "Niveaux_Etude"(id_niveau),
    titre VARCHAR(150) NOT NULL,
    description TEXT,
    type offer_type DEFAULT 'Stage',
    statut offer_status DEFAULT 'Active',
    date_publication DATE DEFAULT CURRENT_DATE
);

CREATE TABLE "Etudiant_Competence" (
    id_etudiant UUID REFERENCES "Etudiants"(id_etudiant) ON DELETE CASCADE,
    id_competence INT REFERENCES "Competences"(id_competence) ON DELETE CASCADE,
    niveau_maitrise INT CHECK (niveau_maitrise BETWEEN 1 AND 5),
    PRIMARY KEY (id_etudiant, id_competence)
);

CREATE TABLE "Opportunite_Competence" (
    id_opportunite UUID REFERENCES "Opportunites"(id_opportunite) ON DELETE CASCADE,
    id_competence INT REFERENCES "Competences"(id_competence) ON DELETE CASCADE,
    poids_critere INT CHECK (poids_critere BETWEEN 1 AND 10),
    PRIMARY KEY (id_opportunite, id_competence)
);

CREATE TABLE "Candidatures" (
    id_candidature UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    id_etudiant UUID REFERENCES "Etudiants"(id_etudiant) ON DELETE CASCADE,
    id_opportunite UUID REFERENCES "Opportunites"(id_opportunite) ON DELETE CASCADE,
    score_compatibilite INT DEFAULT 0,
    statut application_status DEFAULT 'En_attente',
    date_candidature TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE "Favoris" (
    id_etudiant UUID REFERENCES "Etudiants"(id_etudiant) ON DELETE CASCADE,
    id_opportunite UUID REFERENCES "Opportunites"(id_opportunite) ON DELETE CASCADE,
    date_ajout TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_etudiant, id_opportunite)
);

CREATE INDEX idx_etudiant_skills ON "Etudiant_Competence"(id_etudiant);
CREATE INDEX idx_opportunite_skills ON "Opportunite_Competence"(id_opportunite);
CREATE INDEX idx_candidature_score ON "Candidatures"(score_compatibilite DESC);
