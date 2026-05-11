<?php
// configuracoes.php - Configurações da Empresa
session_start();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';

if (!$userId || !in_array($role, ['admin', 'rh'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'api/db.php';

// Fetch current settings
$stmt = $pdo->query("SELECT * FROM company_settings WHERE id = 1");
$settings = $stmt->fetch();

if (!$settings) {
    // Should not happen as we insert in db.php, but just in case
    $pdo->exec("INSERT INTO company_settings (name) VALUES ('Minha Empresa')");
    $settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch();
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Configuração da Empresa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #101622; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="settingsApp()">
    <div class="flex h-screen overflow-hidden bg-[#101622]">
        <?php include 'components/sidebar.php'; ?>
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Configuração da Empresa</h1>
                    <div class="flex items-center gap-6 ml-auto">
                        <?php include 'components/header_user.php'; ?>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">
                <div class="max-w-4xl mx-auto">
                    <div class="bg-slate-900/50 border border-slate-800 rounded-3xl p-8 shadow-xl">
                        <form @submit.prevent="saveSettings">
                            <!-- Logo Section -->
                            <div class="mb-10 flex flex-col items-center">
                                <div class="relative group">
                                    <img :src="logoPreview || '<?= $settings['logotype'] ?: 'logo.png' ?>'" 
                                         class="w-48 h-48 object-contain rounded-2xl border-4 border-slate-800 bg-slate-900/50 p-4 transition-all group-hover:border-blue-500/50" 
                                         alt="Empresa Logo">
                                    <label class="absolute inset-0 flex items-center justify-center bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl cursor-pointer">
                                        <i class="fa-solid fa-camera text-3xl text-white"></i>
                                        <input type="file" class="hidden" @change="handleLogoChange" accept="image/*">
                                    </label>
                                </div>
                                <p class="text-slate-500 text-xs mt-3 uppercase font-black tracking-widest">Logotipo da Empresa</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                <!-- Dados Gerais -->
                                <div class="space-y-6">
                                    <h3 class="text-lg font-bold text-white flex items-center gap-3 mb-4">
                                        <i class="fa-solid fa-building text-blue-500"></i> Dados Gerais
                                    </h3>
                                    
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Nome da Empresa *</label>
                                        <input type="text" x-model="form.name" required
                                               class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Email de Contacto</label>
                                        <input type="email" x-model="form.email"
                                               class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">NIF da Empresa</label>
                                        <input type="text" x-model="form.nif"
                                               class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Telefone</label>
                                        <input type="text" x-model="form.phone"
                                               class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                    </div>
                                </div>

                                <!-- Localização e Descrição -->
                                <div class="space-y-6">
                                    <h3 class="text-lg font-bold text-white flex items-center gap-3 mb-4">
                                        <i class="fa-solid fa-location-dot text-emerald-500"></i> Localização e Descrição
                                    </h3>

                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Website</label>
                                        <input type="url" x-model="form.website"
                                               class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 text-white focus:border-blue-500 outline-none transition-all font-medium"
                                               placeholder="https://exemplo.com">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Localização (Morada)</label>
                                        <textarea x-model="form.location" rows="3"
                                                  class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 text-white focus:border-blue-500 outline-none transition-all font-medium"></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Descrição da Empresa</label>
                                        <textarea x-model="form.description" rows="3"
                                                  class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 text-white focus:border-blue-500 outline-none transition-all font-medium"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Redes Sociais -->
                            <div class="mb-10">
                                <h3 class="text-lg font-bold text-white flex items-center gap-3 mb-6">
                                    <i class="fa-solid fa-share-nodes text-indigo-500"></i> Redes Sociais
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-blue-400">
                                            <i class="fa-brands fa-facebook-f text-lg"></i>
                                        </span>
                                        <input type="text" x-model="form.facebook" placeholder="Link do Facebook"
                                               class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 pl-12 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                    </div>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-pink-500">
                                            <i class="fa-brands fa-instagram text-lg"></i>
                                        </span>
                                        <input type="text" x-model="form.instagram" placeholder="Link do Instagram"
                                               class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 pl-12 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                    </div>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-blue-600">
                                            <i class="fa-brands fa-linkedin-in text-lg"></i>
                                        </span>
                                        <input type="text" x-model="form.linkedin" placeholder="Link do LinkedIn"
                                               class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 pl-12 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                    </div>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-sky-400">
                                            <i class="fa-brands fa-twitter text-lg"></i>
                                        </span>
                                        <input type="text" x-model="form.twitter" placeholder="Link do Twitter"
                                               class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 pl-12 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                    </div>
                                </div>
                            </div>

                            <!-- Pagamentos -->
                            <div class="mb-10">
                                <h3 class="text-lg font-bold text-white flex items-center gap-3 mb-6">
                                    <i class="fa-solid fa-money-bill-transfer text-emerald-500"></i> Configurações de Pagamento
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Desconto por Atraso (AOA) - Opcional</label>
                                        <div class="relative">
                                            <input type="number" step="0.01" x-model="form.late_discount_amount"
                                                   class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 text-white focus:border-blue-500 outline-none transition-all font-medium"
                                                   placeholder="0.00">
                                        </div>
                                        <p class="text-[10px] text-slate-500 mt-2">Valor deduzido por cada registro de entrada após o horário.</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Desconto por Falta (AOA) - Opcional</label>
                                        <div class="relative">
                                            <input type="number" step="0.01" x-model="form.absence_discount_amount"
                                                   class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3.5 text-white focus:border-blue-500 outline-none transition-all font-medium"
                                                   placeholder="0.00">
                                        </div>
                                        <p class="text-[10px] text-slate-500 mt-2">Valor deduzido por cada dia de falta não justificada.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit -->
                            <div class="flex justify-end pt-6 border-t border-slate-800">
                                <button type="submit" :disabled="loading"
                                        class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold py-4 px-10 rounded-2xl shadow-xl shadow-blue-500/20 transition-all flex items-center gap-3">
                                    <template x-if="!loading">
                                        <div class="flex items-center gap-3">
                                            <i class="fa-solid fa-save text-lg"></i> Salvar Configurações
                                        </div>
                                    </template>
                                    <template x-if="loading">
                                        <div class="flex items-center gap-3">
                                            <i class="fa-solid fa-circle-notch fa-spin"></i> Salvando...
                                        </div>
                                    </template>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        function settingsApp() {
            return {
                sidebarOpen: false,
                loading: false,
                logoPreview: null,
                logoFile: null,
                form: {
                    name: '<?= addslashes($settings['name']) ?>',
                    email: '<?= addslashes($settings['email']) ?>',
                    nif: '<?= addslashes($settings['nif'] ?? '') ?>',
                    phone: '<?= addslashes($settings['phone']) ?>',
                    location: '<?= addslashes($settings['location']) ?>',
                    description: '<?= addslashes($settings['description']) ?>',
                    website: '<?= addslashes($settings['website']) ?>',
                    facebook: '<?= addslashes($settings['facebook']) ?>',
                    instagram: '<?= addslashes($settings['instagram']) ?>',
                    linkedin: '<?= addslashes($settings['linkedin']) ?>',
                    twitter: '<?= addslashes($settings['twitter']) ?>',
                    late_discount_amount: '<?= $settings['late_discount_amount'] ?>',
                    absence_discount_amount: '<?= $settings['absence_discount_amount'] ?>'
                },

                handleLogoChange(e) {
                    const file = e.target.files[0];
                    if (file) {
                        this.logoFile = file;
                        this.logoPreview = URL.createObjectURL(file);
                    }
                },

                async saveSettings() {
                    this.loading = true;
                    const formData = new FormData();
                    for (let key in this.form) {
                        formData.append(key, this.form[key]);
                    }
                    if (this.logoFile) {
                        formData.append('logotype', this.logoFile);
                    }

                    try {
                        const res = await fetch('api/settings_update.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        if (data.success) {
                            MeckNotifications.success('Sucesso', 'Configurações atualizadas com sucesso!');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            MeckNotifications.error('Erro', data.error || 'Erro ao salvar configurações');
                        }
                    } catch (e) {
                        MeckNotifications.error('Erro', 'Erro de conexão: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>
