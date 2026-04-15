<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $count = \App\Models\Skill::count();
    $skills = \App\Models\Skill::limit(5)->get();
    echo "Total skills: " . $count . "\n";
    foreach ($skills as $skill) {
        echo "- " . $skill->nom_competence . " (" . $skill->category . ")\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
