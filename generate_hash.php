<?php
// File ini untuk generate hash password yang benar
// Jalankan sekali melalui browser atau terminal

echo "<h1>Generate Password Hash</h1>";
echo "<pre>";

$passwords = [
    'super123' => 'Superuser',
    'sdi123' => 'SDI',
    'rektor123' => 'Rektor',
    'nilai123' => 'Penilai',
];

foreach ($passwords as $password => $role) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "Role: $role\n";
    echo "Password: $password\n";
    echo "Hash: $hash\n";
    echo str_repeat('-', 50) . "\n";
}

echo "</pre>";
?>