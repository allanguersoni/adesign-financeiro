# PROJECT_CONTEXT.md — ADesign Financeiro

## 1. O QUE É O PROJETO
**ADesign Financeiro** é um SaaS / Sistema de Gestão Financeira focado em pequenas agências e designers freelancers. Ele gerencia assinantes (clientes), faturas e inadimplência.
- **Objetivo**: Controlar pagamentos recorrentes (mensalidades/anualidades), identificar atrasos, calcular saúde financeira e realizar envio de cobranças.
- **Para quem serve**: Gestores de estúdios/agências de design, ou desenvolvedores independentes para controle da carteira de clientes.

## 2. STACK REAL DO PROJETO
- **Backend / Linguagem**: PHP Vanilla 8.x (uso intensivo de validação tipada e estruturas modernas como `match(){}`).
- **Estilo (CSS)**: Tailwind CSS via CDN integrado ao HTML com tema customizado (Glassmorphism e componentes UI limpos).
- **Ícones/Fontes**: Google Fonts (Inter) e Material Symbols Outlined.
- **Banco de Dados**: MySQL 8.0 integrado nativamente via extensão PDO no PHP.
- **Infra / Servidor**: Docker e Docker Compose (`apache/php` via volume `app/`, `mysql` e `phpmyadmin`).

## 3. ESTRUTURA DE PASTAS COMENTADA
- **`/` (Raiz)**: Arquivos de infraestrutura geral.
  - `.env` e `.env.example`: Variavéis de banco de dados e mailer SMTP.
  - `docker-compose.yml`: Define containers essenciais (web, db, phpmyadmin).
  - `init.sql`: Script responsável pela criação do schema das tabelas e mockups de dados no BD MySQL.
- **`/app/`**: Root da aplicação Apache (`DocumentRoot`).
  - `index.php`: Dashboard com métricas globais e gráficos informativos.
  - `login.php`: Tela de entrada para acessar o sistema.
  - `clientes.php`: CRUD completo da base de clientes com filtros lógicos e interfaces modais para edição.
- **`/app/config/`**: Controladores de Configuração.
  - `auth.php`: Core de segurança/autenticação principal (permissões, CSRF, flash e block system).
  - `conexao.php`: Inicialização de string PDO (banco de dados) a ser invocada.
  - `env.php`: Parser do ambiente e variables parser.
- **`/app/actions/`**: "Controllers" ou hooks onde as requisições POST/GET são tratadas (inserir, alterar e deletar informações).
- **`/app/includes/`**: Componentes de UI padronizados reutilizáveis (como `header.php`, `footer.php`).
- **`/app/assets/`**: Assets pesados, imagens e ilustrações da plataforma.

## 4. ARQUIVOS SENSÍVEIS (NUNCA EXPOR AO GIT)
1. `.env`: Contém dados em texto plano - as credenciais de banco e roteiros SMTP reais não devem subir.
2. `init.sql`: Se ele portar uma carga inicial real, poderá ter dados e contratos expostos de clientes.
3. `cookies.txt` (local): Restos de requisições de teste que poderiam manter acessos liberados.

## 5. BANCO DE DADOS
Tabelas simples sem FKs explícitas ou triggers complexos:
- **`clientes`**: 
  - `id` (PK), `nome`, `email`, `dominio`, `valor_anual` (DECIMAL), `tipo_pagamento` (ENUM 'a vista', '2x', '3x'), `status` (ENUM 'em dia', 'pendente', 'vence em 15 dias'), `data_vencimento_base` e timestamp cronológico.
- **`usuarios`**: 
  - `id` (PK), `nome`, `email` (UNIQUE), `senha_hash`, `ativo`, `ultimo_acesso`.
- **`login_tentativas`**:
  - `id` (PK), `ip_address`, `sucesso`, `criado_em`. (Proteção anti-bot).

## 6. FLUXO DE AUTENTICAÇÃO E SEGURANÇA
- **Estado de Sessão**: Usa nativamente o engine de sessions do PHP trancado via `session.cookie_httponly = 1` e `samesite = Strict`.
- **CSRF Token**: Exigência de input hidden via `<?= csrf_field() ?>` (`get_csrf_token()`) e validado por `validate_csrf($_POST['csrf_token'])`.
- **Anti Brute Force**: Sistema checa baseando-se por IP no MySQL. O usuário sofre block por 15 minutos caso acumule até 5 transações mal sucedidas.
- **Flash Alerts**: Validação em backend manda respostas à View por `$_SESSION['flash']` lido por `get_flash()`.

## 7. SISTEMA DE PERMISSÕES (RBAC)
Mecanismo centralizado no `can(string $permission)` do `auth.php`:
- **Admin**: Poder supremo no software (Edita e manuseia cadastros, permissões globais e envios).
- **Editor**: Manuseia livremente área de faturamento (`edit_clients`, `send_charges`), mas sem credencial para registrar mais usuários ou alterar senhas alheias.
- **Demo**: Papel vitrine. Tem a permissão travada apenas na view-only rule (`view_all`).

## 8. COMO ADICIONAR NOVA FUNCIONALIDADE
O padrão em vigor é simples em estrutura Paged-Action, sem frameworks MVC como Laravel:
1. **Página Visual**: Construa `app/novorecurso.php`. Na primeira linha invoque `$page_title` seguido das travas globais: `require_once 'config/auth.php'` e `require_auth()` + Inclusão de Layout (Header). Se tiver formas (formulários), adicione lá dentro `<?= csrf_field() ?>`.
2. **Action Endpoint**: Formulário enviará dados POST rumo à pasta `actions/rota_destino.php`.
3. **Controlador Back End**: 
   - Dependências: `require_once '../config/auth.php'` e `../config/conexao.php`.
   - Limite CSRF: Bloqueie usando `validate_csrf()`.
   - Check RBAC: Restringir a edição a usuários aptos por `require_can('alguma_permissao')`.
   - Lógica Segura: Higienizar strings captadas `sanitize_string()` na construção dos arrays para inputs num `$pdo->prepare()->execute()`.
   - Feedback e fechamento: Utilize `set_flash()` seguido pelo handler de saída `redirect_back('/novorecurso.php')`.

## 9. ARQUIVOS DE REFERÊNCIA EXISTENTES
O diretório local `/references/` aloja documentação voltada e interpretável apenas pela Inteligência Artificial.
- **`PARA_IA.md`**: Único arquivo existente da pasta! Aponta logs/dicas contextuais da stack na máquina WSL2 e alerta sobre configurações futuras (Push de Git em SSH e bloqueio na subida do `.env`). Os documentos "ARCHITECTURE.md" e "WORKFLOW.md" não existem e não foram criados.

## 10. PRÓXIMAS FUNCIONALIDADES SUGERIDAS 
Evoluindo a base construída até aqui, sugere-se a implementação pontual dos incrementos:
- **Rotinas Cron (Workers de Alertas Automáticos)**: O path do arquivo `app/cron/notificador.php` já existe. Deve-se ativá-lo atrelando cronjobs linux (ou um crond no Docker) para disparo diário automático de e-mail de cobranças a clientes perto dos "vence em 15 dias" ou "pendente".
- **Logs de Auditoria Interna (Track Changes)**: Acompanhar os lastros (`id` do Editor/Admin responsável) sempre que ocorrer exclusão ou upgrade de contrato por segurança/prestação de contas.
- **Gateway de Pagamento Integrado API**: Possibilitar links automáticos e captar callbacks de webhook (Stripe, Asaas, Pagar.me) de modo que a conta atualize `status` independentemente da ativação manual do Admin.
