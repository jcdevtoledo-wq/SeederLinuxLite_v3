# SeederLinux Lite - Guia de Instalação Atualizado

## Problema Corrigido

O loop de redirecionamento entre login.html e admin.html era causado por:
1. **Caminhos relativos nos JavaScript** - `api/` em vez de `/api/`
2. **Redirecionamentos relativos** - `login.html` em vez de `/login.html`
3. **Caminhos de assets relativos** - `assets/` em vez de `/assets/`

## Estrutura Correta para Deploy

```
DocumentRoot = /var/www/seederlinux-lite

/var/www/seederlinux-lite/
├── api/
│   ├── index.php           ← API Router (único entry point)
│   └── download.php        ← Downloads
├── assets/
│   ├── css/style.css
│   └── js/app.js, admin.js
├── includes/
│   └── auth.php
├── lib/
│   ├── config.php
│   ├── db.php
│   └── functions.php
├── downloads/
│   └── agent.py
├── storage/
├── scripts/custom/
├── index.html              ← Página inicial
├── login.html              ← Login
├── admin.html              ← Painel admin
├── .htaccess
├── debug.html              ← DELETE após instalação!
├── test_session.php        ← DELETE após instalação!
└── setup.php               ← DELETE após instalação!
```

## Passos de Instalação

### 1. Copiar Arquivos

```bash
# Copie todo o conteúdo da pasta public/ para o DocumentRoot
sudo cp -r public/* /var/www/seederlinux-lite/
```

### 2. Configurar Apache

```apache
<VirtualHost *:443>
    ServerName seederlinux.comara.intraer
    DocumentRoot /var/www/seederlinux-lite

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/seederlinux.crt
    SSLCertificateKeyFile /etc/ssl/private/seederlinux.key

    <Directory /var/www/seederlinux-lite>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # PHP settings
    php_value session.cookie_secure 1
    php_value session.cookie_httponly 1
    php_value session.cookie_samesite Lax
    php_value session.cookie_path /

    ErrorLog ${APACHE_LOG_DIR}/seederlinux_error.log
    CustomLog ${APACHE_LOG_DIR}/seederlinux_access.log combined
</VirtualHost>
```

### 3. Verificar Permissões

```bash
sudo chown -R www-data:www-data /var/www/seederlinux-lite
sudo chmod -R 755 /var/www/seederlinux-lite
sudo chmod -R 775 /var/www/seederlinux-lite/storage
sudo chmod -R 775 /var/www/seederlinux-lite/scripts/custom
```

### 4. Verificar Sessão PHP

```bash
# Verificar session.save_path
php -r "echo session_save_path() . PHP_EOL;"

# Verificar permissões
sudo mkdir -p /var/lib/php/sessions
sudo chmod 1733 /var/lib/php/sessions
sudo chown www-data:www-data /var/lib/php/sessions
```

### 5. Criar Banco de Dados

```bash
sudo -u postgres psql -c "CREATE DATABASE seederlinux;"
sudo -u postgres psql -c "CREATE USER seeder WITH PASSWORD 'sua_senha';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE seederlinux TO seeder;"
sudo -u postgres psql -d seederlinux -f /var/www/seederlinux-lite/install/schema.sql
```

### 6. Verificar Configuração

Acesse: `https://seederlinux.comara.intraer/test_session.php`

Deve retornar algo como:
```json
{
  "session_id": "...",
  "session_status": "active",
  "session_config": {
    "cookie_params": {
      "lifetime": 86400,
      "path": "/",
      "secure": true,
      "httponly": true,
      "samesite": "Lax"
    }
  }
}
```

### 7. Testar Login

Acesse: `https://seederlinux.comara.intraer/debug.html`

1. Clique em "Check Session" - deve retornar `success: false` (não logado)
2. Clique em "Test Login" - deve retornar `success: true`
3. Clique em "Check Session After Login" - deve retornar `success: true` com dados do usuário

### 8. Deletar Arquivos de Teste

```bash
sudo rm /var/www/seederlinux-lite/debug.html
sudo rm /var/www/seederlinux-lite/test_session.php
sudo rm /var/www/seederlinux-lite/setup.php
```

## Credenciais Padrão

- **Usuário:** admin
- **Senha:** admin123

**IMPORTANTE:** Altere a senha após o primeiro login!

## Troubleshooting

### Erro: "Not authenticated" após login

Verifique se o cookie está sendo salvo:
1. Abra DevTools (F12) → Application → Cookies
2. Deve haver um cookie `PHPSESSID`
3. Após login, deve haver cookies de sessão

### Erro: Cookie não salvo

Verifique se:
1. HTTPS está funcionando
2. `session.cookie_secure = 1` no php.ini
3. `session.cookie_path = /` no php.ini
4. Permissões de `/var/lib/php/sessions`

### Logs de Erro

```bash
# Apache error log
sudo tail -f /var/log/apache2/seederlinux_error.log

# PHP error log
sudo tail -f /var/log/php_errors.log
```

## Comandos Úteis

```bash
# Reiniciar Apache
sudo systemctl restart apache2

# Verificar configuração Apache
sudo apache2ctl configtest

# Verificar módulos habilitados
sudo apache2ctl -M | grep -E 'rewrite|headers|ssl'

# Habilitar módulos necessários
sudo a2enmod rewrite headers ssl
```
