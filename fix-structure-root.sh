#!/bin/bash
# fix-structure-root.sh - Script para corrigir estrutura na raiz

echo "=========================================="
echo "SeederLinux Lite - Correção de Estrutura"
echo "=========================================="

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Variáveis
BASE_DIR="/var/www/seederlinux-lite"

# Função para verificar se é root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        echo -e "${RED}Este script deve ser executado como root${NC}"
        exit 1
    fi
}

# Função para fazer backup
backup_files() {
    echo -e "${YELLOW}Criando backup da estrutura atual...${NC}"
    BACKUP_DIR="/tmp/seederlinux-backup-$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    cp -r "$BASE_DIR" "$BACKUP_DIR/"
    echo -e "${GREEN}Backup criado em: $BACKUP_DIR${NC}"
}

# Função para limpar estrutura duplicada
clean_structure() {
    echo -e "${YELLOW}Limpando estrutura duplicada...${NC}"
    
    # Remover pasta public e seu conteúdo
    rm -rf "$BASE_DIR/public" 2>/dev/null
    
    # Remover pastas vazias
    rm -rf "$BASE_DIR/bundles" 2>/dev/null
    rm -rf "$BASE_DIR/storage" 2>/dev/null
    rm -rf "$BASE_DIR/scripts/custom" 2>/dev/null
    
    echo -e "${GREEN}Estrutura limpa${NC}"
}

# Função para criar diretórios necessários
create_directories() {
    echo -e "${YELLOW}Criando diretórios necessários...${NC}"
    
    mkdir -p "$BASE_DIR/api"
    mkdir -p "$BASE_DIR/assets/css"
    mkdir -p "$BASE_DIR/assets/js"
    mkdir -p "$BASE_DIR/includes"
    mkdir -p "$BASE_DIR/lib"
    mkdir -p "$BASE_DIR/downloads"
    mkdir -p "$BASE_DIR/storage/logs"
    mkdir -p "$BASE_DIR/scripts/custom"
    mkdir -p "$BASE_DIR/bundles"
    
    echo -e "${GREEN}Diretórios criados${NC}"
}

# Função para definir permissões
set_permissions() {
    echo -e "${YELLOW}Definindo permissões...${NC}"
    
    chown -R www-data:www-data "$BASE_DIR"
    chmod -R 755 "$BASE_DIR"
    chmod -R 775 "$BASE_DIR/storage"
    chmod -R 775 "$BASE_DIR/bundles"
    chmod -R 775 "$BASE_DIR/scripts/custom"
    
    echo -e "${GREEN}Permissões definidas${NC}"
}

# Função para criar .htaccess na raiz
create_htaccess() {
    echo -e "${YELLOW}Criando .htaccess...${NC}"
    
    cat > "$BASE_DIR/.htaccess" << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Se o arquivo ou diretório existe, serve diretamente
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    
    # Redirecionar API requests
    RewriteRule ^api/?$ api/index.php [QSA,L]
    RewriteRule ^api/(.*)$ api/index.php?action=$1 [QSA,L]
    
    # Redirecionar todas as outras requests para a página inicial
    RewriteRule ^ index.html [L]
</IfModule>

# Proteger arquivos sensíveis
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

<FilesMatch "(config\.php|db\.php|auth\.php|functions\.php|\.env)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Desabilitar listagem de diretórios
Options -Indexes

# Permitir acesso apenas a arquivos específicos em downloads
<Directory "downloads">
    Order allow,deny
    Allow from all
</Directory>
EOF

    echo -e "${GREEN}.htaccess criado${NC}"
}

# Função para criar .env
create_env() {
    echo -e "${YELLOW}Criando .env...${NC}"
    
    cat > "$BASE_DIR/.env" << 'EOF'
# Database Configuration
DB_HOST=localhost
DB_PORT=5432
DB_NAME=seederlinux
DB_USER=seeder
DB_PASS=seeder123

# Application Configuration
APP_NAME=SeederLinux Lite
APP_VERSION=1.0.0
DEBUG=true

# Security
SESSION_SECURE=false
SESSION_LIFETIME=86400
EOF

    echo -e "${GREEN}.env criado${NC}"
}

# Função para criar arquivo de diagnóstico
create_diagnostic() {
    echo -e "${YELLOW}Criando arquivo de diagnóstico...${NC}"
    
    cat > "$BASE_DIR/diagnostic.php" << 'EOF'
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico SeederLinux</h1>";

// Verificar arquivos
$files = [
    'api/index.php',
    'includes/auth.php', 
    'lib/config.php',
    'lib/db.php',
    'lib/functions.php'
];

echo "<h2>Verificando arquivos:</h2>";
echo "<ul>";
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    $exists = file_exists($path);
    echo "<li>$file: " . ($exists ? "✅ OK" : "❌ FALTA") . "</li>";
}
echo "</ul>";

// Verificar banco de dados
echo "<h2>Verificando banco:</h2>";
try {
    require_once __DIR__ . '/lib/db.php';
    $db = Database::getInstance();
    echo "<p style='color:green'>✅ Conexão com banco OK</p>";
    
    $tables = Database::fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
    echo "<p>Tabelas encontradas: " . count($tables) . "</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . $table['table_name'] . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erro: " . $e->getMessage() . "</p>";
}

// Verificar usuário admin
echo "<h2>Verificando usuário admin:</h2>";
try {
    $user = Database::fetchOne("SELECT username, role FROM users WHERE role = 'admin'");
    if ($user) {
        echo "<p style='color:green'>✅ Usuário admin encontrado: " . $user['username'] . "</p>";
    } else {
        echo "<p style='color:red'>❌ Nenhum usuário admin encontrado</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erro: " . $e->getMessage() . "</p>";
}

// Verificar configuração
echo "<h2>Configuração:</h2>";
echo "<ul>";
echo "<li>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li>Script Name: " . $_SERVER['SCRIPT_NAME'] . "</li>";
echo "<li>PHP Version: " . PHP_VERSION . "</li>";
echo "<li>Loaded Extensions: " . implode(', ', get_loaded_extensions()) . "</li>";
echo "</ul>";
?>
EOF

    echo -e "${GREEN}diagnostic.php criado${NC}"
}

# Função para configurar Apache
configure_apache() {
    echo -e "${YELLOW}Configurando Apache...${NC}"
    
    # Desabilitar site antigo se existir
    a2dissite seederlinux.conf 2>/dev/null
    
    # Criar arquivo de configuração do site
    cat > "/etc/apache2/sites-available/seederlinux.conf" << 'EOF'
<VirtualHost *:80>
    ServerName seederlinux.local
    DocumentRoot /var/www/seederlinux-lite
    
    <Directory /var/www/seederlinux-lite>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # PHP error reporting for development
        php_flag display_errors on
        php_flag display_startup_errors on
        php_value error_reporting E_ALL
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/seederlinux-error.log
    CustomLog ${APACHE_LOG_DIR}/seederlinux-access.log combined
</VirtualHost>
EOF

    # Habilitar site e módulos
    a2ensite seederlinux.conf 2>/dev/null
    a2dissite 000-default.conf 2>/dev/null
    a2enmod rewrite 2>/dev/null
    
    echo -e "${GREEN}Apache configurado${NC}"
}

# Função principal
main() {
    check_root
    backup_files
    clean_structure
    create_directories
    create_htaccess
    create_env
    create_diagnostic
    set_permissions
    configure_apache
    
    echo -e "${GREEN}=========================================="
    echo "Correção concluída com sucesso!"
    echo "==========================================${NC}"
    echo ""
    echo "Próximos passos:"
    echo "1. Reinicie o Apache: sudo systemctl restart apache2"
    echo "2. Verifique o banco: sudo systemctl status postgresql"
    echo "3. Execute o schema: sudo -u postgres psql -d seederlinux -f $BASE_DIR/install/schema.sql"
    echo "4. Acesse: http://seu-servidor/diagnostic.php para verificar"
    echo "5. Acesse: http://seu-servidor/login.html para fazer login"
}

# Executar
main