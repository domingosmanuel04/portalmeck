<?php
// documento_detalhe.php - Detalhes do Documento
session_start();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';

if (!$userId) {
    header('Location: index.php');
    exit;
}

require_once 'api/db.php';

$documentId = $_GET['id'] ?? null;
if (!$documentId) {
    header('Location: documentos.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT d.*, i.user_id as intern_user_id, u.name as intern_name, i.course, i.department
         FROM documents d
         JOIN interns i ON d.intern_id = i.id
         JOIN users u ON i.user_id = u.id
         WHERE d.id = ?"
    );
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $document = false;
}

if (!$document) {
    header('Location: documentos.php');
    exit;
}

// Verificar permissões
if ($role === 'estagiario' && $document['intern_user_id'] != $userId) {
    header('Location: documentos.php');
    exit;
}

$ext = strtoupper(pathinfo($document['file_path'], PATHINFO_EXTENSION));
$fileSize = file_exists($document['file_path']) ? filesize($document['file_path']) : 0;
$fileSizeKB = round($fileSize / 1024, 2);

$categoryLabels = [
    'contrato' => 'Contrato',
    'termo' => 'Termo / Universidade',
    'pessoal' => 'Documento Pessoal',
    'outro' => 'Outro'
];

$categoryColor = 'bg-slate-800';
if ($document['type'] == 'contrato') $categoryColor = 'bg-blue-900/30 text-blue-400';
elseif ($document['type'] == 'termo') $categoryColor = 'bg-indigo-900/30 text-indigo-400';
elseif ($document['type'] == 'pessoal') $categoryColor = 'bg-emerald-900/30 text-emerald-400';
else $categoryColor = 'bg-slate-800 text-slate-400';

$isExpiring = $document['valid_until'] && strtotime($document['valid_until']) < strtotime('+10 days');
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Detalhes do Documento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>body { font-family: 'Inter', sans-serif; background-color: #101622; }</style>
</head>
<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="documentDetailApp()">
    <div class="flex h-screen overflow-hidden bg-[#101622]">
        <?php include 'components/sidebar.php'; ?>
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Detalhes do Documento</h1>
                    <div class="flex items-center gap-6 ml-auto">
                        <?php include 'components/header_user.php'; ?>
                    </div>
                </div>
            </header>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">
                <div class="max-w-5xl mx-auto">
                    <div class="mb-6">
                        <a href="documentos.php" class="text-sm text-slate-400 hover:text-white flex items-center gap-2">
                            <i class="fa-solid fa-arrow-left"></i> Voltar para Documentos
                        </a>
                    </div>

                    <div class="grid gap-6 md:grid-cols-3">
                        <!-- Coluna Principal -->
                        <div class="md:col-span-2 space-y-6">
                            <!-- Visualizador de Arquivo -->
                            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8">
                                <?php if (strtolower($ext) === 'PDF'): ?>
                                    <div class="aspect-video bg-slate-800 rounded-lg overflow-hidden flex items-center justify-center mb-4">
                                        <iframe src="<?= htmlspecialchars($document['file_path']) ?>" width="100%" height="100%" style="border:none; border-radius:8px;"></iframe>
                                    </div>
                                <?php else: ?>
                                    <div class="aspect-video bg-slate-800 rounded-lg overflow-hidden flex flex-col items-center justify-center">
                                        <i class="fa-solid fa-file text-6xl text-slate-600 mb-4"></i>
                                        <p class="text-slate-400 text-center">Arquivo: <?= htmlspecialchars($document['title']) ?></p>
                                        <p class="text-sm text-slate-500 mt-2">(<?= $ext ?>) - <?= $fileSizeKB ?> KB</p>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-4 flex gap-3">
                                    <?php if (strtolower($ext) === 'PDF'): ?>
                                        <a href="<?= htmlspecialchars($document['file_path']) ?>" target="_blank" rel="noopener noreferrer"
                                            class="flex-1 py-3 px-4 rounded-lg font-medium text-white bg-emerald-600 hover:bg-emerald-700 transition flex items-center justify-center gap-2">
                                            <i class="fa-solid fa-eye"></i> Abrir em Nova Aba
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= htmlspecialchars($document['file_path']) ?>" download
                                        class="flex-1 py-3 px-4 rounded-lg font-medium text-white bg-blue-600 hover:bg-blue-700 transition flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-download"></i> Baixar
                                    </a>
                                </div>
                            </div>

                            <!-- Informações do Documento -->
                            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                                <h3 class="text-lg font-semibold text-white mb-4">Informações do Documento</h3>
                                <div class="grid gap-4">
                                    <div>
                                        <p class="text-xs text-slate-500 uppercase tracking-widest mb-1">Título / Nome</p>
                                        <p class="text-white font-medium"><?= htmlspecialchars($document['title']) ?></p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-xs text-slate-500 uppercase tracking-widest mb-1">Tipo</p>
                                            <span class="<?= $categoryColor ?> text-sm px-3 py-1 rounded font-bold uppercase tracking-wide">
                                                <?= $categoryLabels[$document['type']] ?? $document['type'] ?>
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500 uppercase tracking-widest mb-1">Formato</p>
                                            <p class="text-white font-medium"><?= $ext ?> (<?= $fileSizeKB ?> KB)</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-xs text-slate-500 uppercase tracking-widest mb-1">Enviado em</p>
                                            <p class="text-white font-medium"><?= date('d/m/Y H:i', strtotime($document['uploaded_at'])) ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500 uppercase tracking-widest mb-1">Válido até</p>
                                            <?php if ($document['valid_until']): ?>
                                                <p class="text-white font-medium <?= $isExpiring ? 'text-red-400' : '' ?>">
                                                    <?= date('d/m/Y', strtotime($document['valid_until'])) ?>
                                                    <?php if ($isExpiring): ?>
                                                        <span class="text-xs text-red-400 ml-2">(⚠️ Expirando)</span>
                                                    <?php endif; ?>
                                                </p>
                                            <?php else: ?>
                                                <p class="text-slate-400">Sem data de validade</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Coluna Lateral -->
                        <div class="space-y-6">
                            <!-- Informações do Estagiário -->
                            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                                <h3 class="text-lg font-semibold text-white mb-4">Estagiário</h3>
                                <div class="text-center mb-4">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($document['intern_name']) ?>&background=1E293B&color=F8FAFC"
                                        class="w-16 h-16 rounded-full mx-auto mb-2 border border-slate-700">
                                    <p class="text-white font-semibold"><?= htmlspecialchars($document['intern_name']) ?></p>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <p class="text-slate-400"><strong>Departamento:</strong> <?= htmlspecialchars($document['department'] ?: 'N/A') ?></p>
                                    <p class="text-slate-400"><strong>Curso:</strong> <?= htmlspecialchars($document['course'] ?: 'N/A') ?></p>
                                </div>
                            </div>

                            <!-- Ações -->
                            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                                <h3 class="text-lg font-semibold text-white mb-4">Ações</h3>
                                <div class="space-y-2">
                                    <?php if (strtolower($ext) === 'PDF'): ?>
                                        <a href="<?= htmlspecialchars($document['file_path']) ?>" target="_blank" rel="noopener noreferrer"
                                            class="w-full py-2 px-4 rounded-lg font-medium text-white bg-emerald-600 hover:bg-emerald-700 transition flex items-center justify-center gap-2 text-sm">
                                            <i class="fa-solid fa-eye"></i> Abrir PDF
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= htmlspecialchars($document['file_path']) ?>" download
                                        class="w-full py-2 px-4 rounded-lg font-medium text-white bg-blue-600 hover:bg-blue-700 transition flex items-center justify-center gap-2 text-sm">
                                        <i class="fa-solid fa-download"></i> Baixar
                                    </a>
                                    <?php if ($role === 'admin' || $role === 'rh'): ?>
                                        <button @click="deleteDocument(<?= $document['id'] ?>)"
                                            class="w-full py-2 px-4 rounded-lg font-medium text-white bg-red-600 hover:bg-red-700 transition flex items-center justify-center gap-2 text-sm mt-4">
                                            <i class="fa-solid fa-trash"></i> Eliminar Documento
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>
    <script>
        function documentDetailApp() {
            return {
                sidebarOpen: false,
                async deleteDocument(docId) {
                    const confirmed = await MeckNotifications.confirm(
                        'Deseja realmente eliminar este documento?',
                        'Esta ação não pode ser desfeita.',
                        'Sim, eliminar'
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
                            setTimeout(() => { window.location.href = 'documentos.php'; }, 1500);
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
