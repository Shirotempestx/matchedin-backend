<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\InteractionController;

try {
    echo "Starting test suite using pgsql...\n";

    // Create test student and enterprise
    $student = User::firstOrCreate(['email' => 'teststudent_auto@test.com'], [
        'name' => 'Auto Student',
        'password' => bcrypt('password'),
        'role' => 'student',
    ]);

    $enterprise = User::firstOrCreate(['email' => 'testenterprise_auto@test.com'], [
        'name' => 'Auto Enterprise',
        'password' => bcrypt('password'),
        'role' => 'enterprise',
        'company_name' => 'Test Corp Auto',
    ]);

    $controller = app(InteractionController::class);

    echo "Testing Follow...\n";
    $reqFollow = Request::create("/api/enterprises/{$enterprise->id}/follow", 'POST');
    $reqFollow->setUserResolver(function () use ($student) { return $student; });
    
    $res1 = $controller->toggleFollowEnterprise($reqFollow, $enterprise->id);
    echo "Toggle 1 (Follow): " . $res1->getContent() . "\n";

    $res2 = $controller->toggleFollowEnterprise($reqFollow, $enterprise->id);
    echo "Toggle 2 (Unfollow): " . $res2->getContent() . "\n";

    echo "Testing Save Student...\n";
    $reqSave = Request::create("/api/students/{$student->id}/save", 'POST');
    $reqSave->setUserResolver(function () use ($enterprise) { return $enterprise; });

    $res3 = $controller->toggleSaveStudent($reqSave, $student->id);
    echo "Toggle 1 (Save): " . $res3->getContent() . "\n";

    $res4 = $controller->toggleSaveStudent($reqSave, $student->id);
    echo "Toggle 2 (Unsave): " . $res4->getContent() . "\n";
    
    // Clean up
    $student->delete();
    $enterprise->delete();

    echo "All tests completed successfully!\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
