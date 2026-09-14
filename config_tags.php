<?php
// config_tags.php - Gestão de etiquetas (layout tipo painel + identidade Meck)
session_start();
require_once 'api/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';
if (!$userId || !in_array($role, ['admin', 'rh'], true)) {
    header('Location: configuracoes.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL DEFAULT '#6366f1',
    description VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$cols = $pdo->query("SHOW COLUMNS FROM tags")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('sort_order', $cols, true)) {
    $pdo->exec("ALTER TABLE tags ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER description");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS tag_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tag_user (tag_id, user_id)
)");

// Seed demo tags if empty
$tagCount = (int)$pdo->query("SELECT COUNT(*) FROM tags")->fetchColumn();
if ($tagCount === 0) {
    $seed = $pdo->prepare("INSERT INTO tags (name, color, sort_order) VALUES (?, ?, ?)");
    $defaults = [
        ['VIP', '#F59E0B', 1],
        ['Indústria', '#3B82F6', 2],
        ['Prioridade', '#EF4444', 3],
        ['Via parceiro', '#6366F1', 4],
        ['Teste', '#10B981', 16],
    ];
    foreach ($defaults as $d) {
        $seed->execute($d);
    }
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $color = strtoupper(trim($_POST['color'] ?? '#6366F1'));
        if ($color !== '' && $color[0] !== '#') {
            $color = '#' . $color;
        }
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);

        if ($name === '') {
            $error = 'Indique o nome da tag.';
        } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $error = 'Cor inválida. Use formato #RRGGBB.';
        } else {
            try {
                if ($action === 'create') {
                    if ($sortOrder <= 0) {
                        $max = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM tags")->fetchColumn();
                        $sortOrder = $max + 1;
                    }
                    $stmt = $pdo->prepare("INSERT INTO tags (name, color, sort_order) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $color, $sortOrder]);
                    $message = 'Tag criada com sucesso.';
                } else {
                    $stmt = $pdo->prepare("UPDATE tags SET name = ?, color = ?, sort_order = ? WHERE id = ?");
                    $stmt->execute([$name, $color, $sortOrder, $id]);
                    $message = 'Tag atualizada com sucesso.';
                }
            } catch (PDOException $e) {
                $error = 'Não foi possível guardar a tag (nome duplicado?).';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM tag_assignments WHERE tag_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
        $message = 'Tag excluída.';
    }
}

$tags = $pdo->query("
    SELECT t.*,
           (SELECT COUNT(*) FROM tag_assignments ta WHERE ta.tag_id = t.id) AS client_count
    FROM tags t
    ORDER BY t.sort_order ASC, t.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$currentUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$currentUserStmt->execute([$userId]);
$currentUser = $currentUserStmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Tags</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
</head>
<body class="h-full antialiased text-slate-200 meck-app"
    x-data="tagsPage(<?= htmlspecialchars(json_encode($tags, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">

<div class="flex h-screen overflow-hidden">
    <?php include 'components/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        <header class="flex items-center justify-between px-6 py-4 border-b border-slate-800/80 bg-[#0b1020]/80 backdrop-blur-md">
                <div class="flex-1 flex justify-between items-center px-2 md:px-4">
                <h1 class="text-2xl font-semibold text-white hidden md:block">Tags</h1>
                <?php include 'components/header_user.php'; ?>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="max-w-6xl mx-auto space-y-5 animate__animated animate__fadeIn">

                <?php if ($message): ?>
                    <div class="meck-card px-4 py-3 text-emerald-300 text-sm border border-emerald-500/20"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="meck-card px-4 py-3 text-red-300 text-sm border border-red-500/20"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Page header (como na imagem) -->
                <section class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <a href="configuracoes.php" class="inline-flex items-center gap-1.5 text-sm font-semibold mb-3 transition-colors hover:opacity-90" style="color:#fbbf24;">
                            <i class="fa-solid fa-arrow-left text-xs"></i> Configurações
                        </a>
                        <h2 class="text-3xl font-extrabold tracking-tight text-white">Tags</h2>
                        <p class="mt-2 text-slate-400 text-sm max-w-xl">
                            Organize colaboradores com etiquetas coloridas. Use-as nos filtros e na ficha de cada pessoa.
                        </p>
                    </div>
                    <button type="button" @click="openCreate()" class="btn btn-primary shrink-0 self-start sm:self-center">
                        <i class="fa-solid fa-circle-plus"></i> Nova tag
                    </button>
                </section>

                <!-- Table card -->
                <section class="meck-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead>
                                <tr class="border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-500">
                                    <th class="px-5 py-4 font-semibold">Tag</th>
                                    <th class="px-5 py-4 font-semibold">Cor</th>
                                    <th class="px-5 py-4 font-semibold">Clientes</th>
                                    <th class="px-5 py-4 font-semibold">Ordem</th>
                                    <th class="px-5 py-4 font-semibold text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/80">
                                <template x-if="tags.length === 0">
                                    <tr>
                                        <td colspan="5" class="px-5 py-10 text-center text-slate-500">
                                            Nenhuma tag criada. Clique em <strong class="text-white">Nova tag</strong> para começar.
                                        </td>
                                    </tr>
                                </template>
                                <template x-for="tag in tags" :key="tag.id">
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold text-white shadow-sm"
                                                  :style="'background:' + tag.color"
                                                  x-text="tag.name"></span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2.5">
                                                <span class="w-3.5 h-3.5 rounded-full ring-2 ring-white/10 shrink-0" :style="'background:' + tag.color"></span>
                                                <span class="font-mono text-xs text-slate-300 uppercase" x-text="tag.color"></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="font-bold text-white" x-text="tag.client_count ?? 0"></span>
                                        </td>
                                        <td class="px-5 py-4 text-slate-300" x-text="tag.sort_order"></td>
                                        <td class="px-5 py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="button" @click="openEdit(tag)"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-200 bg-slate-900/80 border border-slate-700 hover:border-cyan-400/40 hover:text-white transition">
                                                    <i class="fa-solid fa-pen-to-square text-[11px] text-slate-400"></i> Editar
                                                </button>
                                                <form method="post" class="inline" @submit.prevent="confirmDelete($event)">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" :value="tag.id">
                                                    <button type="submit"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-300 bg-slate-900/80 border border-rose-500/30 hover:bg-rose-500/10 transition">
                                                        <i class="fa-solid fa-trash text-[11px]"></i> Excluir
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>

        <?php include 'components/footer.php'; ?>
    </div>
</div>

<!-- Modal Nova / Editar tag -->
<div x-show="modalOpen" x-cloak
     class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
     @keydown.escape.window="modalOpen = false">
    <div class="meck-card w-full max-w-md p-6 shadow-2xl" @click.outside="modalOpen = false">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-white" x-text="form.id ? 'Editar tag' : 'Nova tag'"></h3>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" @click="modalOpen = false">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="post" class="space-y-4">
            <input type="hidden" name="action" :value="form.id ? 'update' : 'create'">
            <input type="hidden" name="id" :value="form.id || ''">

            <div>
                <label class="text-xs font-semibold text-slate-400 mb-1.5 block">Nome da tag</label>
                <input name="name" x-model="form.name" required
                       class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5 rounded-xl"
                       placeholder="Ex: VIP">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-slate-400 mb-1.5 block">Cor</label>
                    <div class="flex items-center gap-2">
                        <input type="color" x-model="form.color"
                               class="w-12 h-11 rounded-xl border border-slate-700 bg-slate-900 p-1 cursor-pointer">
                        <input name="color" x-model="form.color"
                               class="flex-1 bg-slate-900 border border-slate-700 text-white px-3 py-2.5 rounded-xl font-mono text-xs uppercase"
                               pattern="^#?[0-9A-Fa-f]{6}$" required>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-400 mb-1.5 block">Ordem</label>
                    <input type="number" name="sort_order" x-model="form.sort_order" min="0"
                           class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5 rounded-xl">
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <span class="text-xs text-slate-500">Pré-visualização:</span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold text-white"
                      :style="'background:' + form.color" x-text="form.name || 'Tag'"></span>
            </div>

            <div class="flex justify-end gap-2 pt-3">
                <button type="button" class="btn btn-secondary" @click="modalOpen = false">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span x-text="form.id ? 'Guardar' : 'Criar tag'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function tagsPage(initialTags) {
    return {
        sidebarOpen: false,
        modalOpen: false,
        tags: Array.isArray(initialTags) ? initialTags : [],
        form: { id: null, name: '', color: '#6366F1', sort_order: 1 },

        openCreate() {
            const maxOrder = this.tags.reduce((m, t) => Math.max(m, Number(t.sort_order) || 0), 0);
            this.form = { id: null, name: '', color: '#6366F1', sort_order: maxOrder + 1 };
            this.modalOpen = true;
        },

        openEdit(tag) {
            this.form = {
                id: tag.id,
                name: tag.name,
                color: (tag.color || '#6366F1').toUpperCase(),
                sort_order: Number(tag.sort_order) || 0
            };
            this.modalOpen = true;
        },

        confirmDelete(e) {
            if (confirm('Excluir esta tag? Esta ação não pode ser desfeita.')) {
                const form = e.target.closest('form');
                if (form) form.submit();
            }
        }
    }
}
</script>
</body>
</html>
