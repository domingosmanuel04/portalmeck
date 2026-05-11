<?php
// api/jobs.php
session_start();
require_once 'db.php';
require_once 'notifications_helper.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';
$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    if ($action === 'create_job' || $action === 'update_job') {
        if (!in_array($role, ['admin', 'rh'])) throw new Exception("Access Denied");
        
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $requirements = $_POST['requirements'] ?? '';
        $department = $_POST['department'] ?? '';
        $status = $_POST['status'] ?? 'aberta';
        $id = $_POST['id'] ?? null;

        if ($action === 'create_job') {
            $stmt = $pdo->prepare("INSERT INTO job_openings (title, description, requirements, department, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $requirements, $department, $status]);
        } else {
            $stmt = $pdo->prepare("UPDATE job_openings SET title = ?, description = ?, requirements = ?, department = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $description, $requirements, $department, $status, $id]);
        }
        echo json_encode(['success' => true]);

    } elseif ($action === 'apply') {
        if (!in_array($role, ['estagiario', 'funcionario'])) throw new Exception("Apenas estagiários ou funcionários podem se candidatar.");
        
        $jobId = $_POST['job_id'] ?? null;
        $skillsDescription = $_POST['skills_description'] ?? '';
        
        // Handle CV Upload
        $cvUrl = null;
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['cv']['tmp_name'];
            $fileName = $_FILES['cv']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['pdf', 'doc', 'docx'];
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception("Apenas arquivos PDF, DOC ou DOCX são permitidos.");
            }
            
            $uploadDir = '../uploads/cvs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $newFileName = 'cv_' . $userId . '_' . time() . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $cvUrl = 'uploads/cvs/' . $newFileName;
            } else {
                throw new Exception("Erro ao salvar o arquivo do CV.");
            }
        }
        
        // Get intern_id
        $stmtInt = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
        $stmtInt->execute([$userId]);
        $intern = $stmtInt->fetch();
        
        if (!$intern) throw new Exception("Perfil de estagiário não encontrado.");
        $internId = $intern['id'];

        // Check if already applied
        $stmtCheck = $pdo->prepare("SELECT id FROM job_applications WHERE opening_id = ? AND intern_id = ?");
        $stmtCheck->execute([$jobId, $internId]);
        if ($stmtCheck->fetch()) throw new Exception("Você já se candidatou a esta vaga.");

        $stmt = $pdo->prepare("INSERT INTO job_applications (opening_id, intern_id, status, cv_url, skills_description) VALUES (?, ?, 'pendente', ?, ?)");
        $stmt->execute([$jobId, $internId, $cvUrl, $skillsDescription]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'update_application') {
        if (!in_array($role, ['admin', 'rh'])) throw new Exception("Access Denied");
        
        $id = $_POST['id'] ?? null;
        $status = $_POST['status'] ?? 'pendente';
        $interviewDate = !empty($_POST['interview_date']) ? str_replace('T', ' ', $_POST['interview_date']) : null;
        $interviewNotes = $_POST['interview_notes'] ?? '';

        $stmt = $pdo->prepare("UPDATE job_applications SET status = ?, interview_date = ?, interview_notes = ? WHERE id = ?");
        $stmt->execute([$status, $interviewDate, $interviewNotes, $id]);

        // Send Notification to Intern
        $stmtApp = $pdo->prepare("
            SELECT ja.intern_id, i.user_id, jo.title as job_title 
            FROM job_applications ja 
            JOIN interns i ON ja.intern_id = i.id 
            JOIN job_openings jo ON ja.opening_id = jo.id 
            WHERE ja.id = ?
        ");
        $stmtApp->execute([$id]);
        $appData = $stmtApp->fetch();

        if ($appData) {
            $title = "Atualização de Candidatura";
            $message = "O status da sua candidatura para a vaga '{$appData['job_title']}' foi alterado para: " . ucfirst($status);
            if ($status === 'entrevista' && !empty($interviewDate)) {
                $message .= ". Entrevista marcada para " . date('d/m/Y H:i', strtotime($interviewDate));
            }
            createNotification($pdo, $appData['user_id'], $title, $message, $status === 'rejeitado' ? 'warning' : 'info');
        }

        echo json_encode(['success' => true]);

    } elseif ($action === 'delete_job') {
        if (!in_array($role, ['admin', 'rh'])) throw new Exception("Access Denied");
        
        $id = $_POST['id'] ?? null;
        if (!$id) throw new Exception("ID da vaga é obrigatório.");

        // First delete all applications for this job
        $stmtApps = $pdo->prepare("DELETE FROM job_applications WHERE opening_id = ?");
        $stmtApps->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM job_openings WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);

    } else {
        throw new Exception("Invalid Action");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
