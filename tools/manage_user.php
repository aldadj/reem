<?php
// Usage: php tools/manage_user.php email [password]
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = $argv[1] ?? null;
if (!$email) {
    echo "MISSING_EMAIL\n";
    exit(2);
}

$password = $argv[2] ?? '12345678';

try {
    $exists = \App\Models\User::where('email', $email)->exists();
    if ($exists) {
        echo "EXISTS\n";
        exit(0);
    }

    \Illuminate\Support\Facades\DB::table('users')->insert([
        'name' => 'ali',
        'email' => $email,
        'email_verified_at' => null,
        'password' => \Illuminate\Support\Facades\Hash::make($password),
        'remember_token' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    echo "INSERTED\n";
    exit(0);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
