<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Skill;

class TechnicalStackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skills = [
            // IT Skills - Languages/Frameworks
            ['name' => 'React', 'category' => 'IT', 'weight' => 5],
            ['name' => 'Laravel', 'category' => 'IT', 'weight' => 5],
            ['name' => 'Vue.js', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Angular', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Node.js', 'category' => 'IT', 'weight' => 5],
            ['name' => 'Python', 'category' => 'IT', 'weight' => 5],
            ['name' => 'Java', 'category' => 'IT', 'weight' => 5],
            ['name' => 'C#', 'category' => 'IT', 'weight' => 4],
            ['name' => 'PHP', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Ruby', 'category' => 'IT', 'weight' => 3],
            ['name' => 'Go', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Rust', 'category' => 'IT', 'weight' => 3],
            ['name' => 'TypeScript', 'category' => 'IT', 'weight' => 5],
            ['name' => 'Next.js', 'category' => 'IT', 'weight' => 5],
            ['name' => 'NestJS', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Express.js', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Django', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Flask', 'category' => 'IT', 'weight' => 3],
            ['name' => 'Spring Boot', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Flutter', 'category' => 'IT', 'weight' => 4],
            ['name' => 'React Native', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Swift', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Kotlin', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Solidity', 'category' => 'IT', 'weight' => 3],
            
            // IT Skills - Frontend/CSS
            ['name' => 'Tailwind CSS', 'category' => 'IT', 'weight' => 5],
            ['name' => 'SASS/SCSS', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Material UI', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Chakra UI', 'category' => 'IT', 'weight' => 3],
            ['name' => 'Redux', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Zustand', 'category' => 'IT', 'weight' => 4],
            
            // IT Skills - DevOps/Cloud
            ['name' => 'Docker', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Kubernetes', 'category' => 'IT', 'weight' => 4],
            ['name' => 'AWS', 'category' => 'IT', 'weight' => 5],
            ['name' => 'Azure', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Google Cloud', 'category' => 'IT', 'weight' => 4],
            ['name' => 'Terraform', 'category' => 'IT', 'weight' => 3],
            ['name' => 'Ansible', 'category' => 'IT', 'weight' => 3],
            ['name' => 'Jenkins', 'category' => 'IT', 'weight' => 3],
            ['name' => 'GitHub Actions', 'category' => 'IT', 'weight' => 4],
            ['name' => 'CircleCI', 'category' => 'IT', 'weight' => 3],
            ['name' => 'Firebase', 'category' => 'IT', 'weight' => 4],
            
            // IT Skills - Databases
            ['name' => 'PostgreSQL', 'category' => 'IT', 'weight' => 4],
            ['name' => 'MySQL', 'category' => 'IT', 'weight' => 4],
            ['name' => 'MongoDB', 'category' => 'IT', 'weight' => 3],
            ['name' => 'Redis', 'category' => 'IT', 'weight' => 3],
            ['name' => 'ElasticSearch', 'category' => 'IT', 'weight' => 3],
            ['name' => 'Cassandra', 'category' => 'IT', 'weight' => 3],
            ['name' => 'GraphQL', 'category' => 'IT', 'weight' => 4],
            
            // Non-IT Skills - Marketing/Sales
            ['name' => 'SEO', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Google Ads', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Facebook Ads', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Growth Hacking', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Email Marketing', 'category' => 'NON_IT', 'weight' => 3],
            ['name' => 'Copywriting', 'category' => 'NON_IT', 'weight' => 3],
            ['name' => 'Sales', 'category' => 'NON_IT', 'weight' => 5],
            ['name' => 'B2B Sales', 'category' => 'NON_IT', 'weight' => 5],
            ['name' => 'CRM (Salesforce)', 'category' => 'NON_IT', 'weight' => 5],
            ['name' => 'HubSpot', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Pipedrive', 'category' => 'NON_IT', 'weight' => 3],
            ['name' => 'Customer Success', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Marketing Automation', 'category' => 'NON_IT', 'weight' => 4],
            
            // Non-IT Skills - Design/UI/UX
            ['name' => 'Figma', 'category' => 'NON_IT', 'weight' => 5],
            ['name' => 'Adobe Photoshop', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Adobe Illustrator', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Adobe InDesign', 'category' => 'NON_IT', 'weight' => 3],
            ['name' => 'UI Design', 'category' => 'NON_IT', 'weight' => 5],
            ['name' => 'UX Design', 'category' => 'NON_IT', 'weight' => 5],
            ['name' => 'Prototyping', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Motion Design', 'category' => 'NON_IT', 'weight' => 3],
            ['name' => 'Web Design', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Branding', 'category' => 'NON_IT', 'weight' => 4],
            
            // Non-IT Skills - Product/Management
            ['name' => 'Product Management', 'category' => 'NON_IT', 'weight' => 5],
            ['name' => 'Agile', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Scrum', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Kanban', 'category' => 'NON_IT', 'weight' => 3],
            ['name' => 'Jira', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Asana', 'category' => 'NON_IT', 'weight' => 3],
            ['name' => 'Trello', 'category' => 'NON_IT', 'weight' => 3],
            ['name' => 'Project Management', 'category' => 'NON_IT', 'weight' => 5],
            ['name' => 'Business Strategy', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Financial Modeling', 'category' => 'NON_IT', 'weight' => 3],
            ['name' => 'HR Management', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Talent Acquisition', 'category' => 'NON_IT', 'weight' => 4],
            ['name' => 'Legal Compliance', 'category' => 'NON_IT', 'weight' => 3],
        ];

        foreach ($skills as $skill) {
            Skill::updateOrCreate(
                ['nom_competence' => $skill['name']],
                [
                    'category' => $skill['category'],
                    'weight' => $skill['weight']
                ]
            );
        }
    }
}
