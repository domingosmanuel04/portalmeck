import os

files_to_check = [
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
]

def revert_ui(filepath):
    if not os.path.exists(filepath):
        return
    
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Revert specific future ui elements
    content = content.replace('glass-card-premium', 'bg-white dark:bg-slate-800 shadow-xl border border-slate-100 dark:border-slate-700')
    content = content.replace('glass-card', 'bg-white dark:bg-slate-800 shadow-xl border border-slate-100 dark:border-slate-700')
    content = content.replace('<link rel=\"stylesheet\" href=\"css/future-ui.css\">', '')
    content = content.replace('<script src=\"js/theme.js\"></script>', '')
    
    # Write back
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
        
    print(f'Reverted UI in {filepath}')

for f in files_to_check:
    revert_ui(f)
