<?php
// api/profile_update.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['error' => 'Sessão expirada.']);
    exit;
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';

try {
    $pdo->beginTransaction();

    // 1. Basic validation and user fetch
    $stmt = $pdo->prepare("SELECT password_hash, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('Usuário não encontrado.');
    }

    // 2. Update basic info (Name, Email, Phone)
    $stmtUpd = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    $stmtUpd->execute([$name, $email, $phone, $userId]);

    // 2.1 Update address if intern
    if ($user['role'] === 'estagiario') {
        $stmtCheck = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
        $stmtCheck->execute([$userId]);
        if ($stmtCheck->fetch()) {
            $stmtAddr = $pdo->prepare("UPDATE interns SET address = ? WHERE user_id = ?");
            $stmtAddr->execute([$address, $userId]);
        } else {
            $stmtAddr = $pdo->prepare("INSERT INTO interns (user_id, address, status) VALUES (?, ?, 'ativo')");
            $stmtAddr->execute([$userId, $address]);
        }
    }

    // Update session
    $_SESSION['user_name'] = $name;
    $_SESSION['email'] = $email;

    // 3. Handle Password change if requested
    if (!empty($newPassword)) {
        if (empty($currentPassword) || !password_verify($currentPassword, $user['password_hash'])) {
            echo json_encode(['error' => 'Palavra-passe atual incorreta.']);
            exit;
        }
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmtPass = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmtPass->execute([$newHash, $userId]);
    }

    // 4. Handle Photo Upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/profiles/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('profile_') . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
            $dbPath = 'uploads/profiles/' . $filename;
            $stmtPhoto = $pdo->prepare("UPDATE users SET photo_url = ? WHERE id = ?");
            $stmtPhoto->execute([$dbPath, $userId]);
            $_SESSION['photo_url'] = $dbPath;
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
?>