#!/bin/bash
# SeederLinux Lite - Script de Deploy
# Use este script para configurar o projeto no servidor

set -e

TARGET_DIR="${1:-/var/www/seederlinux}"

echo "=== SeederLinux Lite - Deploy ==="
echo "Instalando em: $TARGET_DIR/public"

# Verificar se estamos no diretório correto
if [ ! -f "public/index.html" ]; then
    echo "ERRO: Execute este script a partir do diretório raiz do projeto"
    exit 1
fi

# Criar estrutura de diretórios
echo "Criando estrutura de diretórios..."
mkdir -p "$TARGET_DIR/public"

# Copiar arquivos da pasta public
echo "Copiando arquivos..."
cp -r public/* "$TARGET_DIR/public/"

# Copiar install e config para referência
mkdir -p "$TARGET_DIR/install"
cp -r install/* "$TARGET_DIR/install/"

# Configurar permissões
echo "Configurando permissões..."
chown -R www-data:www-data "$TARGET_DIR"
chmod -R 755 "$TARGET_DIR"
chmod -R 775 "$TARGET_DIR/public/storage"
chmod -R 775 "$TARGET_DIR/public/bundles"
chmod -R 775 "$TARGET_DIR/public/scripts/custom"

# Criar arquivo .env se não existir
if [ ! -f "$TARGET_DIR/public/.env" ]; then
    echo "Criando arquivo .env padrão..."
    cat > "$TARGET_DIR/public/.env" << 'EOF'
DB_HOST=localhost
DB_PORT=5432
DB_NAME=seederlinux
DB_USER=seeder
DB_PASS=seeder123

APP_NAME=SeederLinux Lite
APP_VERSION=1.0.0
DEBUG=false
EOF
fi

echo ""
echo "=== Deploy concluído! ==="
echo ""
echo "Próximos passos:"
echo ""
echo "1. Configure o Apache com DocumentRoot: $TARGET_DIR/public"
echo ""
echo "2. Exemplo de VirtualHost:"
echo ""
cat << 'VHOST'
<VirtualHost *:80>
    ServerName seederlinux.comara.intraer
    DocumentRoot /var/www/seederlinux/public

    <Directory /var/www/seederlinux/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
VHOST

echo ""
echo "3. Habilite os módulos do Apache:"
echo "   sudo a2enmod rewrite headers"
echo "   sudo systemctl restart apache2"
echo ""
echo "4. Configure o banco de dados PostgreSQL:"
echo "   sudo -u postgres psql -f $TARGET_DIR/install/schema.sql"
echo ""
echo "5. Acesse: https://seederlinux.comara.intraer/login.html"
echo "   Usuário: admin"
echo "   Senha: admin123"
echo "   (ALTERE A SENHA APÓS O PRIMEIRO LOGIN!)"
