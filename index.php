<?php
// index.php - Login Page
session_start();

// Simple check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'components/head_tags.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Michroma&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0b1020;
            /* Uniform dark background */
            background-image:
                radial-gradient(circle at 10% 80%, rgba(0, 150, 255, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 80% 20%, rgba(191, 0, 255, 0.1) 0%, transparent 40%),
                linear-gradient(135deg, #101622 0%, #0d121d 50%, #0a0e18 100%);
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        /* Sci-fi texture on background */
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('data:image/svg+xml;utf8,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><filter id="noiseFilter"><feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(%23noiseFilter)"/></svg>');
            opacity: 0.15;
            mix-blend-mode: overlay;
            pointer-events: none;
        }

        /* Line rays decoration to mimic the background */
        .rays {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 250vmax;
            height: 250vmax;
            background: conic-gradient(from 0deg at 50% 50%, transparent 0deg, rgba(30, 144, 255, 0.1) 15deg, transparent 30deg, transparent 60deg, rgba(30, 144, 255, 0.1) 75deg, transparent 90deg, transparent 150deg, rgba(0, 100, 255, 0.15) 180deg, transparent 210deg, transparent 270deg, rgba(30, 144, 255, 0.1) 285deg, transparent 300deg);
            animation: rotate 60s linear infinite;
            transform-origin: center;
            z-index: 0;
            pointer-events: none;
        }

        .explosion-pulse {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 70vmax;
            height: 70vmax;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(160, 60, 255, 0.3) 0%, transparent 60%);
            transform: translate(-50%, -50%) scale(0);
            animation: explodePulse 4s cubic-bezier(0.1, 0.8, 0.3, 1) infinite;
            z-index: 1;
            pointer-events: none;
            mix-blend-mode: screen;
        }

        @keyframes explodePulse {
            0% {
                transform: translate(-50%, -50%) scale(0.2);
                opacity: 0.8;
            }

            40% {
                transform: translate(-50%, -50%) scale(2.5);
                opacity: 0;
            }

            100% {
                transform: translate(-50%, -50%) scale(2.5);
                opacity: 0;
            }
        }

        @keyframes rotate {
            0% {
                transform: translate(-50%, -50%) rotate(0deg) scale(0.9);
            }

            50% {
                transform: translate(-50%, -50%) rotate(180deg) scale(1.15);
                /* explosive zoom in */
            }

            100% {
                transform: translate(-50%, -50%) rotate(360deg) scale(0.9);
            }
        }

        .sci-fi-panel {
            position: relative;
            width: 420px;
            background: rgba(16, 22, 34, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(0, 150, 255, 0.05);
            z-index: 10;
            padding: 40px;
            /* Main clip path matching the shape */
            clip-path: polygon(20px 0,
                    100% 0,
                    100% calc(100% - 20px),
                    calc(100% - 20px) 100%,
                    0 100%,
                    0 20px);
        }

        /* Glassmorphism Noise Texture for the panel */
        .sci-fi-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('data:image/svg+xml;utf8,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><filter id="noiseFilter"><feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="3" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(%23noiseFilter)"/></svg>');
            opacity: 0.2;
            mix-blend-mode: overlay;
            pointer-events: none;
            z-index: -1;
        }

        /* Side details */
        .side-detail {
            position: absolute;
            width: 15px;
            height: 40px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-right: none;
            z-index: 5;
            backdrop-filter: blur(4px);
            clip-path: polygon(0 10px, 100% 0, 100% 100%, 0 calc(100% - 10px));
        }

        .side-detail.left-1 {
            top: 120px;
            left: -14px;
        }

        .side-detail.left-2 {
            top: 180px;
            left: -14px;
        }

        .side-detail.left-3 {
            top: 240px;
            left: -14px;
        }

        .side-detail-right {
            position: absolute;
            width: 15px;
            height: 40px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: none;
            z-index: 5;
            backdrop-filter: blur(4px);
            clip-path: polygon(0 0, 100% 10px, 100% calc(100% - 10px), 0 100%);
        }

        .side-detail.right-1 {
            bottom: 120px;
            right: -14px;
        }

        .side-detail.right-2 {
            bottom: 180px;
            right: -14px;
        }

        .side-detail.right-3 {
            bottom: 240px;
            right: -14px;
        }

        .logo-font {
            font-family: 'Michroma', sans-serif;
            letter-spacing: 2px;
        }

        .panel-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .panel-input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(160, 60, 255, 0.5);
            outline: none;
            box-shadow: 0 0 15px rgba(160, 60, 255, 0.2), inset 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .panel-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
            font-size: 0.85rem;
        }

        .option-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }

        .option-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.4);
            color: #fff;
        }

        .login-btn {
            background: linear-gradient(135deg, #00BFFF 0%, #BF00FF 100%);
            color: #fff;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .login-btn:hover {
            box-shadow: 0 0 20px rgba(191, 0, 255, 0.5), 0 0 40px rgba(0, 191, 255, 0.2);
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        /* Corner accents for the panel */
        .panel-accent-tl {
            position: absolute;
            top: 0;
            left: 20px;
            width: 40px;
            height: 1px;
            background: rgba(255, 255, 255, 0.5);
        }

        .panel-accent-tl-vert {
            position: absolute;
            top: 20px;
            left: 0;
            width: 1px;
            height: 40px;
            background: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>

<body>

    <div class="explosion-pulse"></div>
    <div class="rays"></div>

    <div class="relative">
        <!-- Decoration tabs -->
        <div class="side-detail left-1"></div>
        <div class="side-detail left-2"></div>
        <div class="side-detail left-3"></div>

        <div class="side-detail-right right-1"></div>
        <div class="side-detail-right right-2"></div>
        <div class="side-detail-right right-3"></div>

        <div class="sci-fi-panel">
            <div class="panel-accent-tl"></div>
            <div class="panel-accent-tl-vert"></div>

            <div class="flex flex-col items-center justify-center text-center mb-10">
                <img src="logo.png" alt="Logo"
                    class="max-h-40 w-auto mb-2 transform hover:scale-105 transition duration-500">
                <p
                    class="text-[0.7rem] text-blue-200/80 leading-relaxed uppercase tracking-[0.2em] font-medium opacity-80 max-w-[85%]">
                    Plataforma de Gestão de Estágios Profissionais
                </p>
            </div>

            <!-- Options (Hidden as they were fillers) -->
            <div class="hidden flex gap-4 mb-8">
                <button type="button"
                    class="option-btn flex-1 py-2 px-4 rounded-full flex items-center justify-center gap-2 text-sm">
                    <i class="fa-solid fa-code text-gray-300"></i> Option 1
                </button>
                <button type="button"
                    class="option-btn flex-1 py-2 px-4 rounded-full flex items-center justify-center gap-2 text-sm">
                    <i class="fa-solid fa-code text-gray-300"></i> Option 2
                </button>
            </div>

            <hr class="border-white/10 mb-8">

            <!-- Form -->
            <form action="api/auth.php" method="POST" class="space-y-6">
                <!-- Erro msg se existir -->
                <?php if (isset($_GET['error'])): ?>
                    <div
                        class="text-red-400 text-xs font-semibold px-2 py-1 bg-red-900/30 rounded border border-red-500/20">
                        Credenciais inválidas! Tente novamente.
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-2 pl-1">Endereço de Email</label>
                    <input type="email" name="email" required placeholder="exemplo@email.com"
                        class="panel-input w-full px-4 py-3 rounded-full text-sm">
                </div>

                <div class="relative">
                    <label class="block text-xs font-medium text-gray-300 mb-2 pl-1">Palavra-passe</label>
                    <input type="password" name="password" required placeholder="Insira a sua palavra-passe"
                        class="panel-input w-full px-4 py-3 rounded-full text-sm">
                    <button type="button"
                        class="absolute right-4 top-[34px] text-gray-400 hover:text-white transition-colors">
                        <i class="fa-regular fa-eye-slash text-sm"></i>
                    </button>
                </div>

                <div class="pt-4 drop-shadow-lg">
                    <button type="submit" id="login-submit"
                        class="login-btn btn btn-primary w-full py-3.5 rounded-xl text-sm shadow-xl inline-flex items-center justify-center gap-2 min-h-[3.25rem]">
                        <i class="fa-solid fa-right-to-bracket" data-login-icon aria-hidden="true"></i>
                        <span data-login-label>Aceder ao Painel</span>
                    </button>
                </div>
            </form>

            <script>
                document.querySelector('form').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const form = this;
                    const btn = form.querySelector('#login-submit');
                    const icon = btn.querySelector('[data-login-icon]');
                    const label = btn.querySelector('[data-login-label]');
                    if (btn.disabled) return;

                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    if (icon) icon.className = 'fa-solid fa-circle-notch fa-spin';
                    if (label) label.textContent = 'A processar...';

                    const resetBtn = () => {
                        btn.disabled = false;
                        btn.removeAttribute('aria-busy');
                        if (icon) icon.className = 'fa-solid fa-right-to-bracket';
                        if (label) label.textContent = 'Aceder ao Painel';
                    };

                    try {
                        const res = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        });
                        const data = await res.json();

                        if (data.success) {
                            if (label) label.textContent = 'A entrar...';
                            if (typeof MeckNotifications !== 'undefined') {
                                MeckNotifications.toast('Login bem-sucedido!', 'success');
                            }
                            window.location.href = data.redirect || 'dashboard.php';
                            return;
                        }

                        resetBtn();
                        if (typeof MeckNotifications !== 'undefined') {
                            MeckNotifications.error('Erro de Login', data.error || 'Credenciais inválidas');
                        } else {
                            alert(data.error || 'Credenciais inválidas');
                        }
                    } catch (err) {
                        resetBtn();
                        if (typeof MeckNotifications !== 'undefined') {
                            MeckNotifications.error('Erro', 'Ocorreu um erro ao processar o login.');
                        } else {
                            alert('Ocorreu um erro ao processar o login.');
                        }
                    }
                });

                // Toggle password visibility
                document.querySelector('button[type="button"]').addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const icon = this.querySelector('i');
                    if (!input || input.tagName !== 'INPUT' || !icon) return;
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    icon.classList.toggle('fa-eye-slash', !show);
                    icon.classList.toggle('fa-eye', show);
                });
            </script>
        </div>
    </div>

</body>

</html>