# SeederLinux Lite - Gerenciamento de Provisionamento Linux

Sistema de gerenciamento centralizado de scripts para provisionamento de estações Linux.

## Instalação Rápida

```bash
sudo ./install/install.sh
```

## Acesso

- Página Pública: http://localhost/
- Painel Admin: http://localhost/admin
- Login: http://localhost/login

## Credenciais Padrão

- Usuário: admin
- Senha: admin123

**IMPORTANTE:** Altere as credenciais em produção!

## Estrutura do Projeto

```
├── api/                 # API REST PHP
│   └── index.php       # Router principal
├── includes/           # Bibliotecas de autenticação
├── lib/               # Conexão e funções PHP
├── public/            # Frontend (HTML/CSS/JS)
├── scripts/           # Scripts shell de provisionamento
├── install/           # Instalador e schema SQL
└── downloads/         # Agente Python e documentação
```

## Uso do Agente

```bash
# Na estação Linux
sudo python3 agent.py --org COMARA --server http://192.168.1.100
```

## Documentação

Veja `install/DOCUMENTACAO.md` para documentação completa.
