<?php
// api/search_global.php — API de Pesquisa Global para a Command Palette (Ctrl + K)

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$results = [];
$searchTerm = '%' . $query . '%';

try {
    // 1. Navegação rápida em páginas
    $pages = [
        ['title' => 'Dashboard — Visão Geral', 'url' => 'dashboard.php', 'icon' => 'fa-chart-line', 'category' => 'Navegação'],
        ['title' => 'Gestão de Funcionários & Estagiários', 'url' => 'estagiarios.php', 'icon' => 'fa-users', 'category' => 'Navegação'],
        ['title' => 'Supervisores', 'url' => 'supervisores.php', 'icon' => 'fa-user-tie', 'category' => 'Navegação'],
        ['title' => 'Grupos e Equipas', 'url' => 'grupos.php', 'icon' => 'fa-layer-group', 'category' => 'Navegação'],
        ['title' => 'Controlo de Presenças', 'url' => 'presencas.php', 'icon' => 'fa-calendar-check', 'category' => 'Navegação'],
        ['title' => 'Gestão de Atividades', 'url' => 'atividades.php', 'icon' => 'fa-list-check', 'category' => 'Navegação'],
        ['title' => 'Avaliações de Desempenho', 'url' => 'avaliacoes.php', 'icon' => 'fa-star', 'category' => 'Navegação'],
        ['title' => 'Calendário de Férias', 'url' => 'ferias_calendario.php', 'icon' => 'fa-umbrella-beach', 'category' => 'Navegação'],
        ['title' => 'Processamento de Pagamentos', 'url' => 'pagamentos.php', 'icon' => 'fa-money-bill-transfer', 'category' => 'Navegação'],
        ['title' => 'Gestão de Documentos', 'url' => 'documentos.php', 'icon' => 'fa-folder-open', 'category' => 'Navegação'],
        ['title' => 'Relatórios RH', 'url' => 'relatorios.php', 'icon' => 'fa-chart-pie', 'category' => 'Navegação'],
        ['title' => 'Vagas & Candidaturas', 'url' => 'vagas.php', 'icon' => 'fa-briefcase', 'category' => 'Navegação'],
        ['title' => 'Chamados RH & Suporte', 'url' => 'chamados.php', 'icon' => 'fa-headset', 'category' => 'Navegação'],
        ['title' => 'Salas de Reunião WebRTC', 'url' => 'reuniao.php', 'icon' => 'fa-video', 'category' => 'Navegação'],
        ['title' => 'Chat de Comunicação', 'url' => 'chat.php', 'icon' => 'fa-comments', 'category' => 'Navegação'],
    ];

    foreach ($pages as $p) {
        if (mb_stripos($p['title'], $query) !== false) {
            $results[] = [
                'id' => 'page-' . $p['url'],
                'title' => $p['title'],
                'subtitle' => 'Navegação direta no sistema',
                'url' => $p['url'],
                'icon' => $p['icon'],
                'category' => 'Páginas'
            ];
        }
    }

    // 2. Pesquisa de Colaboradores
    $stmtUser = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role, i.id as intern_id, i.department, i.internship_area 
        FROM users u 
        LEFT JOIN interns i ON u.id = i.user_id 
        WHERE u.name LIKE ? OR u.email LIKE ? OR i.department LIKE ? OR i.internship_area LIKE ?
        LIMIT 6
    ");
    $stmtUser->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    while ($u = $stmtUser->fetch()) {
        $results[] = [
            'id' => 'user-' . $u['id'],
            'title' => $u['name'],
            'subtitle' => ($u['role'] === 'estagiario' ? 'Estagiário • ' : 'Supervisor • ') . ($u['email'] ?? ''),
            'url' => 'estagiarios.php?search=' . urlencode($u['name']),
            'icon' => 'fa-user-gear',
            'category' => 'Colaboradores'
        ];
    }

    // 3. Pesquisa de Documentos
    $stmtDoc = $pdo->prepare("
        SELECT d.id, d.title, d.type 
        FROM documents d 
        WHERE d.title LIKE ? OR d.type LIKE ? 
        LIMIT 5
    ");
    $stmtDoc->execute([$searchTerm, $searchTerm]);
    while ($d = $stmtDoc->fetch()) {
        $results[] = [
            'id' => 'doc-' . $d['id'],
            'title' => $d['title'],
            'subtitle' => 'Tipo: ' . ucfirst($d['type']),
            'url' => 'documentos.php?search=' . urlencode($d['title']),
            'icon' => 'fa-file-lines',
            'category' => 'Documentos'
        ];
    }

    // 4. Pesquisa de Chamados / Tickets
    $stmtTicket = $pdo->prepare("
        SELECT id, subject, status 
        FROM tickets 
        WHERE subject LIKE ? OR description LIKE ? 
        LIMIT 5
    ");
    $stmtTicket->execute([$searchTerm, $searchTerm]);
    while ($t = $stmtTicket->fetch()) {
        $results[] = [
            'id' => 'ticket-' . $t['id'],
            'title' => $t['subject'],
            'subtitle' => 'Estado: ' . ucfirst($t['status']),
            'url' => 'chamados.php?ticket_id=' . $t['id'],
            'icon' => 'fa-ticket',
            'category' => 'Chamados'
        ];
    }

    echo json_encode(['success' => true, 'results' => array_slice($results, 0, 12)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
