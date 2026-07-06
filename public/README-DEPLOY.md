# SeederLinux Lite - Instalação e Deploy

## Estrutura Correta para Deploy

O `DocumentRoot` do Apache deve apontar para a pasta `public/`:

```
DocumentRoot /var/www/seederlinux/public

public/
├── api/
│   ├── index.php         # API Router
│   └── download.php      # Download handler
├── assets/
│   ├── css/style.css
│   └── js/app.js, admin.js
├── includes/
│   └── auth.php          # Autenticação
├── lib/
│   ├── config.php
│   ├── db.php
│   └── functions.php
├── downloads/
│   └── agent.py
├── storage/             # Bundles gerados
├── scripts/custom/       # Scripts customizados
├── .htaccess             # Configuração Apache
├── index.html            # Página inicial
├── login.html            # Página de login
└── admin.html            # Painel admin
```

## Instalação no Servidor

### 1. Copiar arquivos

```bash
# Copie a pasta public/ para o servidor
sudo cp -r public/* /var/www/seederlinux/public/

# Ou clone o repositório e configure o DocumentRoot
```

### 2. Configurar Apache

Crie `/etc/apache2/sites-available/seederlinux.conf`:

```apache
<VirtualHost *:80>
    ServerName seederlinux.comara.intraer
    DocumentRoot /var/www/seederlinux/public

    <Directory /var/www/seederlinux/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/seederlinux_error.log
    CustomLog ${APACHE_LOG_DIR}/seederlinux_access.log combined
</VirtualHost>
```

### 3. Habilitar módulos

```bash
sudo a2enmod rewrite headers
sudo a2ensite seederlinux
sudo systemctl restart apache2
```

### 4. Criar banco de dados

```bash
sudo -u postgres psql -f install/schema.sql
```

### 5. Configurar ambiente

Crie `/var/www/seederlinux/public/lib/config.env.php` ou use variáveis de ambiente:

```php
<?php
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_PORT'] = '5432';
$_ENV['DB_NAME'] = 'seederlinux';
$_ENV['DB_USER'] = 'seeder';
$_ENV['DB_PASS'] = 'sua_senha';
```

### 6. Permissões

```bash
sudo chown -R www-data:www-data /var/www/seederlinux/public
sudo chmod -R 755 /var/www/seederlinux/public
sudo chmod -R 775 /var/www/seederlinux/public/storage
sudo chmod -R 775 /var/www/seederlinux/public/scripts/custom
```

## URLs após instalação

- `https://seederlinux.comara.intraer/` -> index.html
- `https://seederlinux.comara.intraer/login.html` -> login.html
- `https://seederlinux.comara.intraer/admin.html` -> admin.html
- `https://seederlinux.comara.intraer/api/?action=...` -> API

## Credenciais padrão

- Usuário: `admin`
- Senha: `admin123`

**IMPORTANTE:** Altere a senha após o primeiro login!

## Solução de problemas

### Erro 404 na API

Verifique se o mod_rewrite está habilitado:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Erro de conexão com banco

Verifique as credenciais em `lib/db.php` ou configure as variáveis de ambiente.

### Sessão não funciona

Verifique se a sessão PHP está configurada corretamente no php.ini:
```ini
session.save_path = "/var/lib/php/sessions"
```
