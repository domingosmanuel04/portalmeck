<?php
// documentos.php - Gestão Documental
session_start();
// if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';

if (!$userId) {
    header('Location: index.php');
    exit;
}

require_once 'api/db.php';

// Fetch current user details
$stmt = $pdo->prepare("
    SELECT u.*, i.address 
    FROM users u 
    LEFT JOIN interns i ON u.id = i.user_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Fetch documents
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$searchParam = "%$search%";

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    if ($role === 'estagiario') {
        // Intern sees only their docs
        $stmtMe = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
        $stmtMe->execute([$userId]);
        $me = $stmtMe->fetch();
        $internId = $me['id'] ?? null;

        if ($internId) {
            // Count total for pagination
            $countQuery = "SELECT COUNT(*) FROM documents WHERE intern_id = ?";
            $countParams = [$internId];
            if ($search) {
                $countQuery .= " AND title LIKE ?";
                $countParams[] = $searchParam;
            }
            if ($type) {
                $countQuery .= " AND type = ?";
                $countParams[] = $type;
            }
            $stmtCount = $pdo->prepare($countQuery);
            $stmtCount->execute($countParams);
            $totalRecords = $stmtCount->fetchColumn();

            $query = "SELECT * FROM documents WHERE intern_id = ?";
            $params = [$internId];

            if ($search) {
                $query .= " AND title LIKE ?";
                $params[] = $searchParam;
            }
            if ($type) {
                $query .= " AND type = ?";
                $params[] = $type;
            }

            $query .= " ORDER BY uploaded_at DESC LIMIT $limit OFFSET $offset";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $documents = [];
            $totalRecords = 0;
        }
    } else {
        // Admin/RH/Supervisor see all
        $queryBase = "FROM documents d JOIN interns i ON d.intern_id = i.id JOIN users u ON i.user_id = u.id WHERE 1=1";
        $params = [];

        if ($search) {
            $queryBase .= " AND (d.title LIKE ? OR u.name LIKE ?)";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        if ($type) {
            $queryBase .= " AND d.type = ?";
            $params[] = $type;
        }

        // Count total for pagination
        $stmtCount = $pdo->prepare("SELECT COUNT(*) " . $queryBase);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();

        $query = "SELECT d.*, u.name as intern_name " . $queryBase;
        $query .= " ORDER BY d.uploaded_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $totalPages = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    $documents = [];
    $totalRecords = 0;
    $totalPages = 0;
}

// Load intern list for upload dropdown
$interns_list = [];
if ($role !== 'estagiario') {
    if ($role === 'supervisor') {
        $stmtInt = $pdo->prepare("
            SELECT i.id, u.name 
            FROM interns i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.internship_area = ? AND i.department = ?
            ORDER BY u.name ASC
        ");
        $stmtInt->execute([$currentUser['internship_area'], $currentUser['department']]);
        $interns_list = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no interns found, show all
        if (empty($interns_list)) {
            $interns_list = $pdo->query("
                SELECT i.id, u.name 
                FROM interns i 
                JOIN users u ON i.user_id = u.id 
                ORDER BY u.name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $interns_list = $pdo->query("
            SELECT i.id, u.name 
            FROM interns i 
            JOIN users u ON i.user_id = u.id 
            ORDER BY u.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Documentos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #101622;
        }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="documentApp()">

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Gestão Documental</h1>
                    <div class="flex items-center gap-6 ml-auto">
                        <?php include 'components/header_user.php'; ?>
                    </div>
                </div>
            </header>

            <!-- Main Scroll Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">

                <div class="flex flex-col sm:flex-row justify-between mb-6 gap-4">
                    <!-- Search / Filter -->
                    <form method="GET" class="flex gap-4 w-full sm:max-w-lg">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3"><i
                                    class="fa-solid fa-search text-slate-500"></i></span>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                class="w-full py-2 pl-10 pr-4 bg-[#101622] border border-slate-700 rounded-lg focus:outline-none focus:ring-blue-500 shadow-sm text-white"
                                placeholder="Buscar documento...">
                            <?php if ($search || $type): ?>
                                <a href="documentos.php" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-red-400">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <select name="type" onchange="this.form.submit()"
                            class="border border-slate-700 rounded-lg px-4 py-2 focus:ring-blue-500 bg-[#101622] shadow-sm text-slate-300">
                            <option value="">Todos Tipos</option>
                            <option value="contrato" <?= $type === 'contrato' ? 'selected' : '' ?>>Contratos</option>
                            <option value="termo" <?= $type === 'termo' ? 'selected' : '' ?>>Termos</option>
                            <option value="pessoal" <?= $type === 'pessoal' ? 'selected' : '' ?>>Documentos Pessoais</option>
                            <option value="outro" <?= $type === 'outro' ? 'selected' : '' ?>>Outros</option>
                        </select>
                    </form>

                    <?php if ($role !== 'estagiario'): ?>
                        <button @click="modalOpen = true"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-5 rounded-lg shadow-md transition-colors flex items-center gap-2">
                            <i class="fa-solid fa-upload mr-1"></i> Fazer Upload
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div
                        class="mb-6 p-4 bg-emerald-900/30 text-emerald-400 border border-emerald-800/50 rounded-xl animate__animated animate__fadeIn flex items-center gap-3">
                        <i class="fa-solid fa-circle-check text-xl"></i>
                        <span class="font-medium">Documento adicionado com sucesso!</span>
                    </div>
                <?php endif; ?>

                <!-- Documents Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 animate__animated animate__fadeIn">

                    <?php if (empty($documents)): ?>
                        <div
                            class="col-span-full py-12 text-center bg-[#101622] rounded-2xl border-2 border-dashed border-slate-800">
                            <i class="fa-solid fa-folder-open text-4xl text-slate-700 mb-3"></i>
                            <p class="text-slate-500">Nenhum documento encontrado.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($documents as $doc):
                        $ext = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
                        $iconClass = 'fa-file';
                        $bgClass = 'bg-slate-900';
                        $textClass = 'text-slate-400';

                        if (strtolower($ext) == 'pdf') {
                            $iconClass = 'fa-file-pdf';
                            $bgClass = 'bg-red-900/20';
                            $textClass = 'text-red-500';
                        } elseif (in_array(strtolower($ext), ['doc', 'docx'])) {
                            $iconClass = 'fa-file-word';
                            $bgClass = 'bg-blue-900/20';
                            $textClass = 'text-blue-500';
                        } elseif (in_array(strtolower($ext), ['jpg', 'png', 'jpeg'])) {
                            $iconClass = 'fa-file-image';
                            $bgClass = 'bg-amber-900/20';
                            $textClass = 'text-amber-500';
                        }

                        $categoryBg = 'bg-slate-800 text-slate-400';
                        if ($doc['type'] == 'contrato')
                            $categoryBg = 'bg-blue-900/30 text-blue-400';
                        elseif ($doc['type'] == 'termo')
                            $categoryBg = 'bg-indigo-900/30 text-indigo-400';
                        elseif ($doc['type'] == 'pessoal')
                            $categoryBg = 'bg-emerald-900/30 text-emerald-400';
                        ?>
                        <div
                            class="bg-[#101622] p-5 rounded-xl border border-slate-800 shadow-sm hover:shadow-md transition group relative animate__animated animate__fadeInUp">
                            <?php if ($doc['valid_until'] && strtotime($doc['valid_until']) < strtotime('+10 days')): ?>
                                <span class="absolute top-4 right-4 flex h-3 w-3">
                                    <span
                                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"
                                        title="Validar! Documento a Expirar"></span>
                                </span>
                            <?php endif; ?>

                            <div
                                class="w-14 h-14 <?= $bgClass ?> <?= $textClass ?> rounded-lg flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">
                                <i class="fa-solid <?= $iconClass ?>"></i>
                            </div>

                            <h3 class="font-bold text-white text-sm mb-1 truncate"
                                title="<?= htmlspecialchars($doc['title']) ?>">
                                <?= htmlspecialchars($doc['title']) ?>
                            </h3>
                            <p class="text-[10px] text-slate-500 mb-1 uppercase tracking-widest">
                                <?= date('d M Y', strtotime($doc['uploaded_at'])) ?>
                            </p>

                            <?php if ($role !== 'estagiario'): ?>
                                <p class="text-xs text-blue-400 font-medium truncate mb-4">
                                    <i class="fa-solid fa-user text-[10px] mr-1"></i>
                                    <?= htmlspecialchars($doc['intern_name']) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-xs text-slate-500 h-4 mb-4"></p>
                            <?php endif; ?>

                            <div class="flex justify-between items-center pt-3 border-t border-slate-800">
                                <span
                                    class="<?= $categoryBg ?> text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wide">
                                    <?= htmlspecialchars($doc['type']) ?>
                                </span>
                                <div class="flex gap-2">
                                    <button @click="openViewer('<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars($doc['title']) ?>')"
                                        class="text-slate-500 hover:text-emerald-400 transition" title="Visualizar">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <a href="<?= htmlspecialchars($doc['file_path']) ?>" download
                                        class="text-slate-500 hover:text-blue-400 transition" title="Baixar">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    <a href="documento_detalhe.php?id=<?= $doc['id'] ?>"
                                        class="text-slate-500 hover:text-slate-300 transition" title="Ver Detalhes">
                                        <i class="fa-solid fa-info-circle"></i>
                                    </a>
                                    <?php if ($role === 'admin' || $role === 'rh'): ?>
                                        <button @click="deleteDocument(<?= $doc['id'] ?>)" class="text-slate-500 hover:text-red-500 transition" title="Eliminar">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-8 flex justify-between items-center bg-slate-900/50 p-4 rounded-xl border border-slate-800">
                        <p class="text-sm text-slate-500">
                            Mostrando <span class="font-medium text-slate-300"><?= $offset + 1 ?></span> a <span class="font-medium text-slate-300"><?= min($offset + $limit, $totalRecords) ?></span> de <span class="font-medium text-slate-300"><?= $totalRecords ?></span> documentos
                        </p>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $type ? '&type=' . urlencode($type) : '' ?>" 
                                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors flex items-center gap-2 text-sm font-medium">
                                    <i class="fa-solid fa-chevron-left"></i> Anterior
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $type ? '&type=' . urlencode($type) : '' ?>" 
                                       class="px-4 py-2 <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-300' ?> rounded-lg transition-colors text-sm font-medium">
                                        <?= $i ?>
                                    </a>
                                <?php elseif ($i == 2 || $i == $totalPages - 1): ?>
                                    <span class="px-2 py-2 text-slate-500">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $type ? '&type=' . urlencode($type) : '' ?>" 
                                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors flex items-center gap-2 text-sm font-medium">
                                    Próximo <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Modal: Fazer Upload -->
                <div x-show="modalOpen"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 p-4"
                    style="display: none;" x-transition>
                    <div @click.away="modalOpen = false"
                        class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-900 flex justify-between items-center">
                            <h2 class="text-lg font-bold text-white">Upload de Documento</h2>
                            <button @click="modalOpen = false" class="text-slate-400 hover:text-red-500"><i
                                    class="fa-solid fa-xmark text-xl"></i></button>
                        </div>
                        <form @submit.prevent="submitDocument" class="px-6 py-5 space-y-4">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-slate-300 mb-1">Título / Nomeção do Ficheiro
                                    *</label>
                                <input type="text" x-model="title"
                                    class="w-full bg-slate-900 border border-slate-700 rounded p-3 text-white focus:ring-blue-500 focus:outline-none"
                                    required autocomplete="off">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-slate-300 mb-1">Pertence ao Estagiário
                                    *</label>
                                <select x-model="selectedIntern"
                                    class="w-full bg-slate-900 border border-slate-700 rounded p-3 text-white focus:ring-blue-500 focus:outline-none" required>
                                    <option value="" class="bg-[#101622]">Selecionar Estagiário...</option>
                                    <template x-for="intern in interns" :key="intern.id">
                                        <option :value="intern.id" class="bg-[#101622]" x-text="intern.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Categoria *</label>
                                    <select x-model="category"
                                        class="w-full bg-slate-900 border border-slate-700 rounded p-3 text-white focus:ring-blue-500 focus:outline-none">
                                        <option value="contrato" class="bg-[#101622]">Contrato</option>
                                        <option value="termo" class="bg-[#101622]">Termo / Universidade</option>
                                        <option value="pessoal" class="bg-[#101622]">Doc Pessoal (BI)</option>
                                        <option value="outro" class="bg-[#101622]">Outro (Relatório)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Validade
                                        (Opcional)</label>
                                    <input type="date" x-model="validity"
                                        class="w-full bg-slate-900 border border-slate-700 rounded p-3 text-white focus:ring-blue-500 focus:outline-none">
                                </div>
                            </div>

                            <!-- Drag Drop Area -->
                            <div class="mt-2 flex justify-center w-full">
                                <label
                                    class="flex flex-col rounded-lg border-2 border-dashed border-slate-700 w-full h-32 p-4 group text-center cursor-pointer hover:bg-slate-900 hover:border-indigo-500 transition-colors"
                                    :class="fileName ? 'border-indigo-500 bg-indigo-500/5' : ''">
                                    <div class="h-full w-full text-center flex flex-col items-center justify-center">
                                        <div class="flex flex-auto max-h-48 w-2/5 mx-auto -mt-10">
                                            <i class="fa-solid text-3xl mb-2 transition-colors"
                                               :class="fileName ? 'fa-file-circle-check text-indigo-400' : 'fa-cloud-arrow-up text-slate-500 group-hover:text-indigo-500'"></i>
                                        </div>
                                        <p class="pointer-none text-slate-400 text-sm">
                                            <span x-show="!fileName">
                                                <span class="text-indigo-500 font-bold hover:underline">Selecione um ficheiro</span> ou arraste aqui
                                            </span>
                                            <span x-show="fileName" class="text-indigo-400 font-bold" x-text="fileName"></span>
                                        </p>
                                        <p class="text-xs text-slate-500 mt-1" x-show="!fileName">PDF, DOCX, JPG ou PNG até 10MB</p>
                                    </div>
                                    <input type="file" @change="handleFile($event)" class="hidden" accept=".pdf,.docx,.jpg,.jpeg,.png" required>
                                </label>
                            </div>

                            <!-- Progress Bar -->
                            <div x-show="isUploading" class="mt-4">
                                <div class="flex justify-between mb-1">
                                    <span class="text-xs font-medium text-indigo-400">Enviando documento...</span>
                                    <span class="text-xs font-medium text-indigo-400" x-text="uploadProgress + '%'"></span>
                                </div>
                                <div class="w-full bg-slate-800 rounded-full h-2">
                                    <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" :style="'width: ' + uploadProgress + '%'"></div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 mt-6">
                                <button type="button" @click="modalOpen = false" :disabled="isUploading"
                                    class="py-2 px-4 rounded-lg font-medium text-slate-300 bg-slate-800 hover:bg-slate-700 transition disabled:opacity-50"><i class="fa-solid fa-xmark mr-1"></i> Cancelar</button>
                                <button type="submit" :disabled="isUploading"
                                    class="py-2 px-6 rounded-lg font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-lg shadow-indigo-900/20 flex items-center gap-2 disabled:opacity-50">
                                    <i class="fa-solid" :class="isUploading ? 'fa-circle-notch fa-spin' : 'fa-upload'"></i> 
                                    <span x-text="isUploading ? 'Aguarde...' : 'Upload'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Modal: Visualizar Documento -->
                <div x-show="viewModalOpen"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 p-4"
                    style="display: none;" x-transition>
                    <div @click.away="viewModalOpen = false"
                        class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden animate__animated animate__fadeInUp animate__faster border border-slate-800 flex flex-col">
                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-900 flex justify-between items-center">
                            <h2 class="text-lg font-bold text-white truncate" x-text="viewDocTitle"></h2>
                            <button @click="viewModalOpen = false" class="text-slate-400 hover:text-red-500"><i
                                    class="fa-solid fa-xmark text-xl"></i></button>
                        </div>
                        <div class="flex-1 overflow-auto">
                            <!-- PDF Viewer -->
                            <div x-show="viewDocType === 'pdf'" class="h-full">
                                <iframe :src="viewDocPath" width="100%" height="100%" style="border:none;"></iframe>
                            </div>
                            <!-- Image Viewer -->
                            <div x-show="viewDocType === 'image'" class="h-full flex items-center justify-center">
                                <img :src="viewDocPath" alt="Documento" class="max-w-full max-h-full object-contain">
                            </div>
                            <!-- Text Viewer -->
                            <div x-show="viewDocType === 'text'" class="h-full p-4">
                                <pre class="text-white bg-slate-900 p-4 rounded overflow-auto max-h-full whitespace-pre-wrap" x-text="viewDocContent"></pre>
                            </div>
                            <!-- Other Files -->
                            <div x-show="viewDocType === 'other'" class="h-full flex items-center justify-center p-8">
                                <div class="text-center">
                                    <i class="fa-solid fa-file text-6xl text-slate-600 mb-4"></i>
                                    <p class="text-slate-400 mb-4">Este tipo de arquivo não pode ser visualizado diretamente no navegador.</p>
                                    <p class="text-slate-500 text-sm">Clique em "Baixar" para abrir o arquivo no seu dispositivo.</p>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t border-slate-800 bg-slate-900 flex justify-end gap-3">
                            <a :href="viewDocPath" target="_blank" rel="noopener noreferrer"
                                class="py-2 px-4 rounded-lg font-medium text-white bg-emerald-600 hover:bg-emerald-700 transition flex items-center gap-2 text-sm">
                                <i class="fa-solid fa-external-link"></i> Abrir em Nova Aba
                            </a>
                            <a :href="viewDocPath" download
                                class="py-2 px-4 rounded-lg font-medium text-white bg-blue-600 hover:bg-blue-700 transition flex items-center gap-2 text-sm">
                                <i class="fa-solid fa-download"></i> Baixar
                            </a>
                        </div>
                    </div>
                </div>


            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <script>
        function documentApp() {
            return {
                sidebarOpen: false,
                modalOpen: false,
                viewModalOpen: false,
                deleteModalOpen: false,
                viewDocId: null,
                viewDocPath: null,
                viewDocTitle: null,
                viewDocType: null,
                viewDocContent: '',
                deleteDocId: null,
                title: '',
                selectedIntern: '',
                category: 'contrato',
                validity: '',
                selectedFile: null,
                fileName: '',
                uploadProgress: 0,
                isUploading: false,
                interns: [],
                
                init() {
                    this.fetchInterns();
                },
                
                handleFile(event) { 
                    const file = event.target.files[0];
                    if (file) {
                        this.selectedFile = file;
                        this.fileName = file.name;
                    }
                },
                
                async fetchInterns() {
                    try {
                        const res = await fetch('api/intern_get.php');
                        this.interns = await res.json();
                    } catch (error) {
                        console.error('Erro ao buscar estagiários:', error);
                    }
                },
                
                async submitDocument() {
                    if (!this.selectedFile) {
                        MeckNotifications.warning('Aviso', 'Por favor, selecione um arquivo.');
                        return;
                    }
                    
                    if (!this.title || !this.selectedIntern) {
                        MeckNotifications.warning('Aviso', 'Por favor, preencha o título e selecione um estagiário.');
                        return;
                    }

                    this.isUploading = true;
                    this.uploadProgress = 0;

                    const formData = new FormData();
                    formData.append('title', this.title);
                    formData.append('intern_id', this.selectedIntern);
                    formData.append('type', this.category);
                    formData.append('valid_until', this.validity);
                    formData.append('file_upload', this.selectedFile);

                    const xhr = new XMLHttpRequest();
                    
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                        }
                    });

                    xhr.onload = () => {
                        this.isUploading = false;
                        let result;
                        try {
                            result = JSON.parse(xhr.responseText);
                            if (xhr.status === 200 && result.success) {
                                MeckNotifications.success('Sucesso', 'Documento enviado com sucesso!');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                const errorMsg = result.error || result.message || 'Erro ao enviar documento.';
                                MeckNotifications.error('Erro', errorMsg);
                            }
                        } catch (e) {
                            console.error('Erro ao processar resposta:', xhr.responseText, e);
                            MeckNotifications.error('Erro', 'Erro no servidor: ' + (xhr.statusText || 'Erro desconhecido'));
                        }
                    };

                    xhr.onerror = () => {
                        this.isUploading = false;
                        MeckNotifications.error('Erro', 'Erro de conexão ou interrupção do upload.');
                    };

                    xhr.open('POST', 'api/document_upload.php');
                    xhr.send(formData);
                },
                
                openViewer(docPath, docTitle) {
                    this.viewDocPath = docPath;
                    this.viewDocTitle = docTitle;
                    const ext = docPath.split('.').pop().toLowerCase();
                    if (['pdf'].includes(ext)) {
                        this.viewDocType = 'pdf';
                    } else if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(ext)) {
                        this.viewDocType = 'image';
                    } else if (['txt', 'csv', 'json', 'xml', 'html'].includes(ext)) {
                        this.viewDocType = 'text';
                        fetch(docPath)
                            .then(res => res.text())
                            .then(text => this.viewDocContent = text)
                            .catch(error => this.viewDocContent = 'Erro ao carregar conteúdo do arquivo.');
                    } else {
                        this.viewDocType = 'other';
                    }
                    this.viewModalOpen = true;
                },
                
                async deleteDocument(docId) {
                    const confirmed = await MeckNotifications.confirm(
                        "Deseja realmente eliminar este documento?",
                        "Esta ação não pode ser desfeita.",
                        "Sim, eliminar"
                    );

                    if (!confirmed.isConfirmed) return;

                    try {
                        const res = await fetch('api/document_delete.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: docId })
                        });
                        const result = await res.json();
                        if (result.success) {
                            MeckNotifications.success('Sucesso', 'Documento eliminado com sucesso!');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            MeckNotifications.error('Erro', result.error || 'Erro ao eliminar documento.');
                        }
                    } catch (error) {
                        MeckNotifications.error('Erro', 'Erro de conexão.');
                    }
                }
            };
        }
    </script>
</body>

</html>