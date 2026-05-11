<?php
require_once 'api/db.php';
// 1. Check names
$stmt = $pdo->query("SELECT id, name FROM users");
while ($row = $stmt->fetch()) {
    $orig = $row['name'];
    // Try to fix common mangling
    $fixed = str_replace(['├í', '├ú', '├¡', '├│'], ['á', 'ã', 'í', 'ó'], $orig);
    // Also remove any weird symbols like & if they are between letters
    $fixed = preg_replace('/&([A-Za-z])/', '$1', $fixed);
    $fixed = str_replace('%&&', 'ã', $fixed); // Guessing from the image

    if ($orig !== $fixed) {
        echo "FIXING: '$orig' -> '$fixed'\n";
        $pdo->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$fixed, $row['id']]);
    } else {
        echo "NAME OK: '$orig'\n";
    }
}
?>
