<?php
// Generate password hash for testing
$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\nUse this SQL to update/create the RH user:\n";
echo "UPDATE users SET password_hash = '$hash' WHERE email = 'rh@mecklitter.com';\n";
?>