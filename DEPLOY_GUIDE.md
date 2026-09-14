# Guia de Deploy — Meck RH / Meck Litter

## Requisitos
- PHP **8.1+** (recomendado 8.3)
- MySQL 5.7+ / MariaDB 10.3+
- Extensões: `pdo_mysql`, `mysqli`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`, `intl`, `zip`
- Apache com `mod_rewrite` + `AllowOverride All` **ou** LiteSpeed / Nginx equivalente

---

## A) Hospedagem partilhada (cPanel / Plesk)

### 1. Base de dados
1. Crie a base MySQL e um utilizador com todos os privilégios nessa base.
2. No phpMyAdmin, importe **`database_hosting.sql`**.
3. (Opcional) Importe **`seed_users.sql`** para contas de teste.
4. **Altere imediatamente** a password do admin após o primeiro login.

Conta seed (se importar `seed_users.sql` + admin do SQL):
- `admin@mecklitter.com` / `password`
- `rh@mecklitter.com` / `password`
- `supervisor@mecklitter.com` / `password`

### 2. Ficheiros
1. Envie o projeto para `public_html` (ou subdomínio).
2. Copie `.env.example` → `.env` e preencha:

```env
APP_ENV=production
APP_URL=https://seu-dominio.com
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
APP_KEY=chave-longa-aleatoria
```

3. Permissões de escrita (755 ou 775 conforme o host):
   - `uploads/` (+ subpastas)
   - `gravacoes/`
   - `tmp/`
   - `backups/`

### 3. Verificar
Abra `https://seu-dominio.com/health.php` — deve devolver `"status": "ok"`.

Depois faça login e **apague ou bloqueie** `health.php` se quiser (já está protegido o acesso a `.sql` / `.env` via `.htaccess`).

### 4. Cron (recomendado)
Veja `cron.example`:
```cron
0 2 * * * cd /home/USER/public_html && php scripts/backup_db.php
*/15 * * * * cd /home/USER/public_html && php scripts/sla_check.php
```

### 5. Segurança pós-deploy
1. Mudar passwords de todas as contas seed.
2. Confirmar HTTPS activo.
3. Confirmar que `.env` e pastas `scripts/`, `tmp/`, `backups/` não são acessíveis pela web.
4. Em cPanel MultiPHP INI: `display_errors = Off`.

---

## B) Docker (VPS)

```bash
cp .env.example .env
# editar passwords e APP_URL

docker compose build
docker compose up -d

# Com TURN/WebRTC:
docker compose --profile webrtc up -d
```

App em `http://localhost:8080` (ou `APP_PORT`).

---

## C) Checklist go-live
- [ ] `.env` com `APP_ENV=production` e passwords fortes
- [ ] `health.php` → ok
- [ ] Login admin funciona
- [ ] Upload de foto/documento funciona
- [ ] Tickets técnicos abrem e listam
- [ ] CSRF: criar tarefa / enviar chat sem “Token inválido” (Ctrl+F5 após login)
- [ ] Cron de backup + SLA configurado
- [ ] Contas seed com passwords alteradas

## Troubleshooting
| Sintoma | Causa provável |
|---------|----------------|
| Erro de ligação BD | Credenciais `.env` / host DB |
| Página em branco | `display_errors` / logs PHP; pasta sem permissão |
| 403 em documentos | Normal — ficheiros sensíveis só via `api/serve_file.php` |
| Token CSRF inválido | Cache antigo — Ctrl+F5; cookie de sessão |
| Papel tecnico falha | Schema antigo — abrir qualquer página (auto-migrate) ou reimportar SQL |

O schema complementar (tickets, auditoria, gravações) é criado automaticamente na primeira ligação via `api/db.php`.
