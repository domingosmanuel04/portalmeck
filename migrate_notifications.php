<?php
// migrate_notifications.php
require_once 'api/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(150),
        message TEXT,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Insert some mock notifications for the admin (user_id 1 usually)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'admin@mecklitter.com' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin) {
        $adminId = $admin['id'];
        $mocks = [
            ['Novo Estagiário', 'Carlos Sousa foi cadastrado no departamento de TI.', 'success'],
            ['Aviso de Presença', '3 estagiários ainda não marcaram a entrada hoje.', 'warning'],
            ['Relatório Disponível', 'O relatório mensal de Janeiro já pode ser baixado.', 'info']
        ];

        foreach ($mocks as $m) {
            $check = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND title = ?");
            $check->execute([$adminId, $m[0]]);
            if (!$check->fetch()) {
                $ins = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $ins->execute([$adminId, $m[0], $m[1], $m[2]]);
            }
        }
    }

    echo "Notifications migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>