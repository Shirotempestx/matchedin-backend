<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Offre;
use App\Models\InAppNotification;
use App\Models\Skill;
use Illuminate\Support\Facades\Hash;

class ComprehensiveTestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get skills first
        $skills = Skill::all();
        $skillMap = $skills->pluck('id_competence', 'nom_competence')->toArray();

        // 1. ADMIN
        $admin = User::updateOrCreate(
            ['email' => 'admin@matchendin.ma'],
            [
                'name' => 'Admin MatchendIn',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'status' => 'active',
                'subscription_tier' => 'admin',
            ]
        );

        // 2. FREE TIER STUDENT
        $studentFree = User::create([
            'name' => 'Ahmed Bennani',
            'email' => 'ahmed.bennani@email.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'status' => 'active',
            'subscription_tier' => 'free',
            'profile_type' => 'IT',
            'education_level' => 'Bac+2',
            'preferred_language' => 'fr',
            'country' => 'Casablanca',
            'title' => 'Développeur JavaScript Débutant',
            'bio' => 'Étudiant passionné par le développement web. J\'approche mes premiers projets React et Node.js. Je suis motivé à apprendre et à mettre en pratique mes connaissances.',
            'university' => 'ISGI Casablanca',
            'work_mode' => 'On-site',
            'availability' => '2-3 months',
            'skill_ids' => [
                ['id' => $skillMap['JavaScript'] ?? 1, 'level' => 2],
                ['id' => $skillMap['React'] ?? 3, 'level' => 1],
                ['id' => $skillMap['Git'] ?? 7, 'level' => 2],
            ],
        ]);

        // 3. PRO TIER STUDENT
        $studentPro = User::create([
            'name' => 'Fatima Zahra Alaoui',
            'email' => 'fatima.alaoui@email.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'status' => 'active',
            'subscription_tier' => 'pro',
            'profile_type' => 'IT',
            'education_level' => 'Bac+2',
            'preferred_language' => 'fr',
            'country' => 'Marrakech',
            'title' => 'Développeur Full Stack Senior Étudiant',
            'bio' => 'Développeuse Full Stack expérimentée avec 2 ans d\'expérience en Laravel et React. Excellente maîtrise du cycle complet de développement. À la recherche d\'une opportunité pour progresser en gestion de projet ou architecture logicielle.',
            'university' => 'Université Cadi Ayyad',
            'work_mode' => 'Hybrid',
            'availability' => 'Immediately',
            'linkedin_url' => 'linkedin.com/in/fitamaalaui',
            'portfolio_url' => 'fatimaalaoui.dev',
            'skill_ids' => [
                ['id' => $skillMap['Laravel'] ?? 2, 'level' => 4],
                ['id' => $skillMap['React'] ?? 3, 'level' => 4],
                ['id' => $skillMap['PHP'] ?? 1, 'level' => 4],
                ['id' => $skillMap['JavaScript'] ?? 4, 'level' => 4],
                ['id' => $skillMap['SQL'] ?? 6, 'level' => 3],
                ['id' => $skillMap['Docker'] ?? 8, 'level' => 2],
                ['id' => $skillMap['Git'] ?? 7, 'level' => 3],
                ['id' => $skillMap['Communication'] ?? 10, 'level' => 4],
            ],
        ]);

        // 4. ELITE TIER STUDENT
        $studentElite = User::create([
            'name' => 'Karim El Fassi',
            'email' => 'karim.elfassi@email.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'status' => 'active',
            'subscription_tier' => 'elite',
            'profile_type' => 'IT',
            'education_level' => 'Master',
            'preferred_language' => 'fr',
            'country' => 'Rabat',
            'title' => 'Architecte Logiciel & Lead Developer',
            'bio' => 'Architecte logiciel avec expérience en conception de systèmes scalables. Spécialiste en microservices, DevOps et cloud infrastructure. Mentor de 3+ développeurs juniors. Recherche un rôle de leadership technique dans une entreprise en croissance.',
            'university' => 'Université Mohammed V',
            'work_mode' => 'Remote',
            'availability' => 'Available',
            'linkedin_url' => 'linkedin.com/in/karim-elfassi',
            'portfolio_url' => 'kelfassi.com',
            'website' => 'github.com/kelfassi',
            'skill_ids' => [
                ['id' => $skillMap['Laravel'] ?? 2, 'level' => 5],
                ['id' => $skillMap['PHP'] ?? 1, 'level' => 5],
                ['id' => $skillMap['React'] ?? 3, 'level' => 5],
                ['id' => $skillMap['JavaScript'] ?? 4, 'level' => 5],
                ['id' => $skillMap['Python'] ?? 5, 'level' => 4],
                ['id' => $skillMap['Docker'] ?? 8, 'level' => 4],
                ['id' => $skillMap['SQL'] ?? 6, 'level' => 5],
                ['id' => $skillMap['Git'] ?? 7, 'level' => 5],
                ['id' => $skillMap['Gestion de projet'] ?? 12, 'level' => 4],
            ],
        ]);

        // 5. FREE TIER ENTERPRISE
        $enterpriseFree = User::create([
            'name' => 'StartupWave',
            'email' => 'hr@startupwave.ma',
            'password' => Hash::make('password123'),
            'role' => 'enterprise',
            'status' => 'active',
            'subscription_tier' => 'free',
            'company_name' => 'StartupWave Maroc',
            'industry' => 'Technology',
            'company_size' => '10-49',
            'country' => 'Casablanca',
            'description' => 'StartupWave est une jeune startup en édition logicielle SaaS. Notre mission : transformer la manière dont les PME gèrent leurs opérations digitales.',
            'website' => 'startupwave.ma',
            'work_mode' => 'Hybrid',
        ]);

        // 6. PRO TIER ENTERPRISE
        $enterprisePro = User::create([
            'name' => 'TechHub Solutions',
            'email' => 'careers@techhubsolutions.ma',
            'password' => Hash::make('password123'),
            'role' => 'enterprise',
            'status' => 'active',
            'subscription_tier' => 'pro',
            'company_name' => 'TechHub Solutions',
            'industry' => 'Software Development',
            'company_size' => '50-199',
            'country' => 'Casablanca',
            'description' => 'TechHub Solutions est un leader dans le développement logiciel custom et l\'intégration de systèmes. Nous servons 200+ clients en Afrique du Nord avec une équipe de 120 développeurs.',
            'website' => 'techhubsolutions.ma',
            'work_mode' => 'On-site',
            'contact_phone' => '+212 5 22 12 34 56',
        ]);

        // 7. ELITE TIER ENTERPRISE
        $enterpriseElite = User::create([
            'name' => 'Innovate Group Pro',
            'email' => 'talents@innovategroup.com',
            'password' => Hash::make('password123'),
            'role' => 'enterprise',
            'status' => 'active',
            'subscription_tier' => 'elite',
            'company_name' => 'Innovate Group - Subsidiary',
            'industry' => 'Digital Transformation & Consulting',
            'company_size' => '200+',
            'country' => 'Casablanca',
            'description' => 'Innovate Group est une firme internationale de conseil et transformation digitale avec bureaux dans 15 pays. Nous recrutons les meilleurs talents pour nos projets d\'envergure mondiale.',
            'website' => 'innovategroup.com',
            'work_mode' => 'Remote',
            'contact_phone' => '+212 5 22 98 76 54',
        ]);

        // OFFERS
        Offre::create([
            'user_id' => $enterpriseFree->id,
            'title' => 'Développeur Frontend Junior (React)',
            'description' => 'Nous recherchons un développeur frontend passionné pour rejoindre notre équipe. Vous travaillerez sur notre plateforme SaaS utilisant React et Tailwind CSS. Excellente opportunité pour débuter votre carrière en startup.',
            'location' => 'Casablanca, Maroc',
            'work_mode' => 'Hybrid',
            'salary_min' => 8000,
            'salary_max' => 12000,
            'contract_type' => 'CDI',
            'niveau_etude' => 'Bac+2',
            'places_demanded' => 2,
            'skills_required' => [
                ['id' => $skillMap['React'] ?? 3, 'level' => 2],
                ['id' => $skillMap['JavaScript'] ?? 4, 'level' => 2],
                ['id' => $skillMap['Git'] ?? 7, 'level' => 1],
            ],
            'is_active' => true,
            'validation_status' => 'validated',
            'views_count' => 145,
        ]);

        Offre::create([
            'user_id' => $enterprisePro->id,
            'title' => 'Développeur Backend Senior (Laravel/PHP)',
            'description' => 'TechHub Solutions recrute un Développeur Backend Senior avec 3+ ans d\'expérience en Laravel. Vous piloterez l\'architecture de nos nouveaux microservices et mentorerez les juniors. Projet complexe, impact technologique majeur.',
            'location' => 'Casablanca, Maroc',
            'work_mode' => 'On-site',
            'salary_min' => 22000,
            'salary_max' => 28000,
            'contract_type' => 'CDI',
            'niveau_etude' => 'Bac+3',
            'places_demanded' => 1,
            'skills_required' => [
                ['id' => $skillMap['Laravel'] ?? 2, 'level' => 4],
                ['id' => $skillMap['PHP'] ?? 1, 'level' => 4],
                ['id' => $skillMap['SQL'] ?? 6, 'level' => 3],
                ['id' => $skillMap['Docker'] ?? 8, 'level' => 2],
                ['id' => $skillMap['Git'] ?? 7, 'level' => 3],
            ],
            'is_active' => true,
            'validation_status' => 'validated',
            'views_count' => 312,
        ]);

        Offre::create([
            'user_id' => $enterprisePro->id,
            'title' => 'Stage - Développeur Full Stack (React + Laravel)',
            'description' => 'Stage rémunéré de 3 mois pour étudiant passionné. Vous intègrerez une équipe travaillant sur un projet e-commerce innovant. Excellente opportunité pour acquérir de l\'expérience en conditions réelles.',
            'location' => 'Casablanca, Maroc',
            'work_mode' => 'On-site',
            'salary_min' => 3000,
            'salary_max' => 4000,
            'contract_type' => 'Stage',
            'niveau_etude' => 'Bac+2',
            'places_demanded' => 3,
            'internship_period' => '3 months',
            'skills_required' => [
                ['id' => $skillMap['React'] ?? 3, 'level' => 2],
                ['id' => $skillMap['Laravel'] ?? 2, 'level' => 2],
                ['id' => $skillMap['JavaScript'] ?? 4, 'level' => 2],
            ],
            'is_active' => true,
            'validation_status' => 'validated',
            'views_count' => 89,
        ]);

        Offre::create([
            'user_id' => $enterpriseElite->id,
            'title' => 'Architecte Cloud & Solution (Senior+)',
            'description' => 'Innovate Group cherche un Architecte Cloud expérimenté pour piloter nos projets de transformation digitale d\'envergure. Vous travaillerez avec nos clients Fortune 500. Télétravail complet, évolutions rapides garanties.',
            'location' => 'Remote (Télétravail)',
            'work_mode' => 'Remote',
            'salary_min' => 40000,
            'salary_max' => 55000,
            'contract_type' => 'CDI',
            'niveau_etude' => 'Master',
            'places_demanded' => 1,
            'skills_required' => [
                ['id' => $skillMap['Docker'] ?? 8, 'level' => 4],
                ['id' => $skillMap['Python'] ?? 5, 'level' => 3],
                ['id' => $skillMap['Gestion de projet'] ?? 12, 'level' => 4],
            ],
            'is_active' => true,
            'validation_status' => 'validated',
            'views_count' => 567,
        ]);

        Offre::create([
            'user_id' => $enterpriseElite->id,
            'title' => 'Lead Product Engineer (Product-Driven)',
            'description' => 'Nous recrutons un Lead Product Engineer pour piloter le développement d\'un nouveau produit SaaS. Vous aurez autonomie totale et reporterez directement au CTO. Vous façonnerez la roadmap produit.',
            'location' => 'Casablanca, Maroc (Possible Remote)',
            'work_mode' => 'Hybrid',
            'salary_min' => 35000,
            'salary_max' => 45000,
            'contract_type' => 'CDI',
            'niveau_etude' => 'Bac+3',
            'places_demanded' => 1,
            'skills_required' => [
                ['id' => $skillMap['React'] ?? 3, 'level' => 4],
                ['id' => $skillMap['JavaScript'] ?? 4, 'level' => 4],
                ['id' => $skillMap['Gestion de projet'] ?? 12, 'level' => 3],
            ],
            'is_active' => true,
            'validation_status' => 'validated',
            'views_count' => 234,
        ]);

        // NOTIFICATIONS
        InAppNotification::create([
            'user_id' => $studentFree->id,
            'type' => 'offer_recommendation',
            'title' => 'Nouvelle offre recommandée',
            'body' => 'Développeur Frontend Junior chez StartupWave - Match: 85%',
            'action_url' => '/offres/1',
            'severity' => 'info',
        ]);

        InAppNotification::create([
            'user_id' => $studentPro->id,
            'type' => 'offer_matched',
            'title' => 'Offre importante pour vous',
            'body' => 'Développeur Backend Senior chez TechHub - Match parfait 95%',
            'action_url' => '/offres/2',
            'severity' => 'high',
        ]);

        InAppNotification::create([
            'user_id' => $studentElite->id,
            'type' => 'recruiter_viewed',
            'title' => 'Un recruteur a vu votre profil',
            'body' => 'Innovate Group a consulté votre profil',
            'action_url' => '/profile',
            'severity' => 'info',
        ]);

        InAppNotification::create([
            'user_id' => $enterprisePro->id,
            'type' => 'application_received',
            'title' => 'Nouvelle candidature reçue',
            'body' => 'Fatima Zahra Alaoui a postulé pour "Développeur Backend Senior"',
            'action_url' => '/applications/1',
            'severity' => 'high',
        ]);

        InAppNotification::create([
            'user_id' => $enterpriseElite->id,
            'type' => 'profile_views',
            'title' => 'Vos offres en tendance',
            'body' => 'Votre offre "Lead Product Engineer" a reçu 23 vues cette semaine',
            'action_url' => '/my-offers',
            'severity' => 'info',
        ]);

        InAppNotification::create([
            'user_id' => $studentPro->id,
            'type' => 'offer_expiring_soon',
            'title' => 'Offre expire bientôt',
            'body' => 'Stage chez TechHub expire dans 5 jours - Postulez maintenant!',
            'action_url' => '/offres/3',
            'severity' => 'high',
        ]);
    }
}
