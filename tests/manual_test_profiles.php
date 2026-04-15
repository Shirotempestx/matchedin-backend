<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\EnterpriseProfileController;

try {
    echo "Starting profile fetching test...\n";

    // Create test student 
    $student = User::firstOrCreate(['email' => 'fetch_student@test.com'], [
        'name' => 'Fetch Student',
        'password' => bcrypt('password'),
        'role' => 'student',
        'title' => 'Software Engineer',
    ]);

    $studentCtl = app(StudentProfileController::class);

    echo "Fetching Student Profile...\n";
    $req = Request::create("/api/student-profiles/me?user_id={$student->id}", 'GET');
    $req->setUserResolver(function () use ($student) { return $student; });
    
    $res1 = $studentCtl->me($req);
    echo "Student Profile: \n" . $res1->getContent() . "\n";
    
    // Enterprise test
    $ent = User::firstOrCreate(['email' => 'fetch_ent@test.com'], [
        'name' => 'Fetch Enterprise',
        'password' => bcrypt('password'),
        'role' => 'enterprise',
        'company_name' => 'Acme Inc',
    ]);
    
    $entCtl = app(EnterpriseProfileController::class);
    
    echo "Fetching Enterprise Profile...\n";
    $reqEnt = Request::create("/api/enterprise-profiles/me?user_id={$ent->id}", 'GET');
    $reqEnt->setUserResolver(function () use ($ent) { return $ent; });
    
    $res2 = $entCtl->me($reqEnt);
    echo "Enterprise Profile: \n" . $res2->getContent() . "\n";

    // Clean up
    $student->delete();
    $ent->delete();

    echo "All profile fetches completed!\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
