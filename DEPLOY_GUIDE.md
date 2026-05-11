# Guia de Deploy - Sistema Meck Litter

Siga os passos abaixo para colocar o sistema online na sua hospedagem.

## 1. Preparação da Base de Dados
1. No seu painel de hospedagem (cPanel, Plesk, etc.), crie uma nova base de dados MySQL.
2. Crie um utilizador para essa base de dados e dê-lhe permissões totais.
3. No **phpMyAdmin**, selecione a base de dados criada e use a aba **Importar** para subir o arquivo `database_hosting.sql`.

## 2. Configuração do Arquivo .env
1. Renomeie o arquivo `.env.example` para `.env`.
2. Edite o arquivo `.env` com os dados da base de dados que criou no passo anterior.
3. Altere a `APP_URL` para o endereço do seu site (ex: `https://meusistema.com`).

## 3. Upload dos Arquivos
1. Utilize um cliente FTP (como FileZilla) ou o Gestor de Arquivos do painel para subir todos os arquivos da pasta do projeto para o servidor (geralmente dentro de `public_html`).
2. **Importante:** Certifique-se de que a pasta `uploads/` tem permissões de escrita (chmod 755 ou 777 dependendo do servidor).

## 4. Requisitos do Servidor
*   **PHP:** Versão 8.2 ou superior (recomendado 8.3).
*   **Extensões PHP obrigatórias:**
    *   `pdo_mysql`
    *   `mysqli`
    *   `openssl`
    *   `mbstring`
    *   `curl`
    *   `fileinfo`
    *   `gd`
    *   `intl`
    *   `zip`

## 5. Dicas de Segurança
1. Certifique-se de que o arquivo `.env` não é acessível publicamente. Se estiver a usar Apache, o sistema já deve estar protegido, mas verifique o arquivo `.htaccess` se necessário.
2. Altere a `APP_KEY` no arquivo `.env` para uma sequência de caracteres única e forte.

Se encontrar algum erro após o upload, verifique os logs de erro do PHP no seu painel de controle.
