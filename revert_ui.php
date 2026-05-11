<?php
\ = [
    'dashboard.php', 
    'estagiarios.php', 
    'atividades.php', 
    'avaliacoes.php', 
    'relatorios.php', 
    'supervisores.php', 
    'presencas.php',
    'chamados.php',
    'documentos.php',
    'organizacao.php'
];

foreach (\ as \) {
    if (!file_exists(\)) continue;
    \ = file_get_contents(\);
    
    // Remove future ui link and theme script
    \ = str_replace('<link rel="stylesheet" href="css/future-ui.css">', '', \);
    \ = str_replace('<script src="js/theme.js"></script>', '', \);
    
    // Replace glass classes with solid standard classes
    \ = str_replace('glass-card-premium', 'bg-white dark:bg-slate-800 shadow-xl border border-slate-100 dark:border-slate-700', \);
    \ = str_replace('glass-card', 'bg-white dark:bg-slate-800 shadow-xl border border-slate-100 dark:border-slate-700', \);
    
    // Clean up gradients text specifically introduced
    \ = str_replace('text-transparent bg-clip-text', 'text-slate-900 dark:text-white', \);
    
    file_put_contents(\, \);
    echo "Reverted UI in " . \ . "\n";
}
?>
