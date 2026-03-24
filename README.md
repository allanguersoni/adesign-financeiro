# ADesign Financeiro - SaaS

Sistema SaaS de Gestão Financeira focado em Controle de Receitas, Cobranças (Recorrência) e Inadimplência, construído em PHP Moderno e TailwindCSS.

## 🚀 Funcionalidades Principais

* **Gestão de Clientes:** Cadastro e edição com Status automático (Em dia, Pendente, Vence em 15 dias).
* **Gestão de Cobranças:** Acompanhamento de Inadimplência e envio de disparo de e-mails customizados de cobrança e avisos.
* **Sistema de Permissões (RBAC):** Múltiplos níveis de acesso (`admin`, `editor`, `demo`).
* **Proteção contra Força Bruta e Ataques:** CSRF Tokens, Secure Sessions, Rate Limiting de IPs.
* **Recuperação de Senha Segura:** Algoritmo à prova de User-Enumeration e Time-Attacks, suporte a PHPMailer.
* **Dashboard e UI UX Premium:** Efeitos Glassmorphism, UI responsiva com efeitos Tailwind.

## 💻 Tecnologias e Arquitetura

O sistema foi construído visando robustez, fácil manutenção e o máximo de velocidade sem a sobrecarga de frameworks monolíticos:

* **Backend:** `PHP 8+` com injeção de dependências e PDO (PHP Data Objects).
* **Banco de Dados:** `MySQL` (100% blindado contra SQL Injection por Prepared Statements).
* **Frontend Design:** `Tailwind CSS` (Glassmorphism, Mobile-First Responsivo, Utilização de CDN para cache veloz) e `HTML5`.
* **Iconografia & Tipografia:** `Material Symbols` (Google MD3) e Fonte `Inter`.
* **Motor de Email:** `PHPMailer` (via SMTP seguro TLS/SSL na porta 587).
* **Infraestrutura/Containers:** `Docker` e `Docker-Compose` para ambiente de desenvolvimento local idêntico à nuvem.
* **Segurança Base (CyberSec):** 
  * Algoritmo `Bcrypt` (Cost-12) para senhas irreversíveis.
  * Proteção `Anti-CSRF` com tokens de sessão assinados.
  * Rate-Limiting `Anti-Brute Force` contra tentativa de invasão de IPs.
  * Sistema de variáveis de ambiente híbrido `.env` para suprimir vazamento de API Keys.
* **Deploy e Nuvem:** Servidor Web `Apache 2.4` em nuvem, FTP Syncing, Mod-Rewrites isolados via `.htaccess` e Tarefas Agendadas no Servidor (`Cron Jobs` Linux).

## 📝 Regras de Negócio e Permissões (Roles)

| Nível / Role | Pode Criar/Editar Clientes? | Pode Enviar Cobranças? | Pode Gerenciar Usuários? | Pode Alterar Senhas? |
|--------------|------------------------------|------------------------|---------------------------|-----------------------|
| `admin`      | Sim                          | Sim                    | Sim                       | Sim                   |
| `editor`     | Sim                          | Sim                    | Não                       | Sim                   |
| `demo`       | Não (Leitura)                | Não (Leitura)          | Não                       | Sim (apenas da própria)|

O usuário `admin` e `editor` tem acesso livre para operar o sistema financeiro. O usuário do tipo `demo` visualiza os dados (com bloqueios visuais em Dashboard, Clientes, Configurações), o que é ideal para portfólio. Mudar o próprio painel e configurações exige permissões ativadas no backend utilizando a função global `can('permission')`.

## 🛠️ Instalação em Hospedagem (cPanel / Servidor Web)

O sistema foi preparado para rodar em produção de forma segura sem expor credenciais em código aberto. Siga os passos:

### 1) Preparando o Banco de Dados
No painel da sua hospedagem (ex: Dreamhost, HostGator), crie um novo **Banco de Dados MySQL** e um **Usuário** atrelando todos os privilégios. Anote o Nome do Banco, o Nome do Usuário e a Senha.

### 2) Subindo os Arquivos
Envie todo o conteúdo da pasta `app/` para o diretório raiz web do seu subdomínio (ex: `public_html/clientes`).
O arquivo `.env.example` e a pasta `vendor/` (se usar PHPMailer via Composer) podem subir junto ou um diretório acima.

### 3) Configuração (.env)
1. Renomeie o arquivo `.env.example` para `.env` (oculto no Linux).
2. Edite o `.env` com suas informações:
```env
DB_HOST=localhost
DB_NAME=seu_banco
DB_USER=seu_usuario
DB_PASS=sua_senha_forte

SMTP_HOST=mail.allandesign.com.br
SMTP_PORT=587
SMTP_USER=financeiro@allandesign.com.br
SMTP_PASS=SenhaDoEmailAqui
SMTP_FROM_EMAIL=financeiro@allandesign.com.br
SMTP_FROM_NAME="ADesign Financeiro"
```

### 4) Executar a Migração (Instalação Automática)
Acesse a URL de instalação no navegador:
**`http://clientes.seu-dominio.com.br/migrate.php`**

O script lerá o arquivo `.env`, criará todas as 4 tabelas (`usuarios`, `clientes`, `password_resets`, `login_tentativas`) e injetará o primeiro usuário **Administrador Mestre** cuja credencial será:
* **E-mail:** `contato@allandesign.com.br`
* **Senha:** `admin123`

> ⚠️ Depois de instalar, o sistema pedirá para remover o arquivo `migrate.php` ou bloqueá-lo. Recomendamos apagar o arquivo do servidor por segurança.

## 🔐 Segurança Implementada

- **Senhas:** Nenhuma senha transita "nua". Utiliza-se a Engine BCRYPT do PHP (cost=12).
- **Recuperação de Senha (Pentest Ready):** O sistema não revela se um e-mail existe na base (mitiga Enumeração), e a tabela de tokens armazena apenas `SHA-256`, enquanto o link enviado por e-mail contém um Bin-Token de 32 bytes imprevisível. Bloqueia IPs após 3 tentativas a cada meia hora.
- **CSRF:** Todas as 13 `actions` no back-end rejeitam `POST` ou alteração de dados se não vierem acompanhadas de um `csrf_token` gerado pela e para aquela exata sessão.

## 📦 Uso Nativo e Docker (Desenvolvimento)
Se quiser rodar localmente com Docker:
```bash
docker-compose up -d --build
```
E acesse `http://localhost`. O Docker do MySQL já cria a tabela (`init.sql` ou você pode rodar o `migrate.php` localmente).
