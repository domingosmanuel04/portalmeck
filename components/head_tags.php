<!-- Meck Design System -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/meck-ui.css">

<script>
    // Brand tokens for Tailwind CDN pages
    if (typeof tailwind !== 'undefined') {
        tailwind.config = Object.assign({}, tailwind.config || {}, {
            theme: {
                extend: Object.assign({}, (tailwind.config && tailwind.config.theme && tailwind.config.theme.extend) || {}, {
                    colors: Object.assign({}, (tailwind.config && tailwind.config.theme && tailwind.config.theme.extend && tailwind.config.theme.extend.colors) || {}, {
                        meck: {
                            cyan: '#22d3ee',
                            blue: '#6366f1',
                            purple: '#c026ff',
                            violet: '#a855f7',
                            bg: '#0b1020',
                            panel: '#151c31',
                        },
                        teams: {
                            50: '#f3f2f8',
                            100: '#e8e7f2',
                            400: '#22d3ee',
                            500: '#6366f1',
                            600: '#7c3aed',
                            700: '#6d28d9',
                            800: '#33344a',
                            900: '#201f1f',
                            dark: '#0b1020',
                            panel: '#151c31',
                            chat: '#0b1020',
                        }
                    }),
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'Segoe UI', 'system-ui', 'sans-serif']
                    },
                    borderRadius: {
                        meck: '0.9rem'
                    }
                })
            }
        });
    }
</script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Meck Global Notifications -->
<script src="js/notifications.js"></script>
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/../api/security_helpers.php';
    meckConfigureSession();
    @session_start();
}
if (!empty($_SESSION['user_id'])) {
    if (!function_exists('meckCsrfToken')) {
        require_once __DIR__ . '/../api/security_helpers.php';
    }
    $meckCsrf = meckCsrfToken();
    echo '<meta name="csrf-token" content="' . htmlspecialchars($meckCsrf, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
?>
<meta http-equiv="X-Content-Type-Options" content="nosniff">
<meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
<!-- CSRF + UI (sem defer: tem de instalar o interceptor cedo) -->
<script src="js/meck-ui.js"></script>
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#22d3ee">
<script>
// Apply saved theme immediately on load
(function() {
    var savedTheme = localStorage.getItem('meck_theme');
    if (savedTheme === 'light') {
        document.documentElement.classList.add('theme-light');
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('theme-light');
        });
    }
})();
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(() => {});
    });
}
// Push local do browser (quando o utilizador permite)
if ('Notification' in window && Notification.permission === 'default') {
    setTimeout(() => Notification.requestPermission().catch(() => {}), 4000);
}
window.meckPush = function (title, body) {
    try {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body: body || '', icon: 'icone.png' });
        }
    } catch (_) {}
};

// PWA Installation handling
let meckDeferredPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    meckDeferredPrompt = e;
    const btn = document.getElementById('pwaInstallBtn');
    if (btn) btn.classList.remove('hidden');
});
window.installMeckPwa = async function () {
    if (!meckDeferredPrompt) {
        if (typeof MeckNotifications !== 'undefined') {
            MeckNotifications.toast('A aplicação Meck RH já está instalada ou este navegador não suporta PWA nativo.');
        } else {
            alert('A aplicação Meck RH já está instalada ou o navegador não suporta PWA.');
        }
        return;
    }
    meckDeferredPrompt.prompt();
    const { outcome } = await meckDeferredPrompt.userChoice;
    if (outcome === 'accepted') {
        const btn = document.getElementById('pwaInstallBtn');
        if (btn) btn.classList.add('hidden');
    }
    meckDeferredPrompt = null;
};
</script>
