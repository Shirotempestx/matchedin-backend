<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Conversation;

$initiator = User::where('role', 'enterprise')->first();
$target = User::where('role', 'student')->first();

if (!$initiator || !$target) {
    echo "Need both enterprise and student users in DB.\n";
    exit;
}

try {
    $result = Gate::forUser($initiator)->allows('create', [Conversation::class, $target]);
    echo "Enterprise -> Student allowed? : " . ($result ? 'Yes' : 'No') . "\n";
} catch (\Exception $e) {
    echo "Error (Enterprise -> Student): " . $e->getMessage() . "\n";
}

try {
    $result = Gate::forUser($target)->allows('create', [Conversation::class, $initiator]);
    echo "Student -> Enterprise allowed? : " . ($result ? 'Yes' : 'No') . "\n";
} catch (\Exception $e) {
    echo "Error (Student -> Enterprise): " . $e->getMessage() . "\n";
}
