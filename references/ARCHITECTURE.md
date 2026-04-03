# ARCHITECTURE.md — ADesign Financeiro
> Leia antes de qualquer implementação.

## Padrão Arquitetural: Paged-Action (PHP Vanilla)

Sem MVC formal. O padrão é:

  [Página Visual]         [Action Endpoint]
  app/recurso.php   →    app/actions/recurso_action.php
       ↑                          ↓
  Exibe HTML/UI          Processa POST, valida, persiste
       ↑                          ↓
  set_flash()    ←     redirect_back('/recurso.php')

## Estrutura de Pastas

app/
├── config/
│   ├── auth.php        ← CORE: sessão, CSRF, RBAC, flash, redirect
│   ├── conexao.php     ← Instância PDO (chamar via require_once)
│   └── env.php         ← Parser do .env
├── actions/            ← Endpoints POST (nunca acessar direto via GET)
├── includes/           ← Componentes UI: header.php, footer.php
├── assets/             ← Imagens, ilustrações
├── cron/               ← Workers de tarefas agendadas
│   └── notificador.php ← Disparos automáticos de cobrança (a ativar)
├── index.php           ← Dashboard principal
├── login.php           ← Autenticação
├── clientes.php        ← CRUD de clientes
└── vendor/             ← PHPMailer (Composer)

references/             ← IA lê antes de codar (não vai para produção)
issues/                 ← Tarefas de desenvolvimento

## Regras Inegociáveis

### Segurança (nunca violar)
- TODA página protegida começa com:
  require_once 'config/auth.php';
  require_auth();
- TODA action começa com:
  require_once '../config/auth.php';
  require_once '../config/conexao.php';
  validate_csrf($_POST['csrf_token']);
  require_can('permissao_necessaria');
- NUNCA concatenar variáveis em SQL — sempre prepare()->execute()
- NUNCA colocar lógica de permissão no front-end
- NUNCA expor .env, cookies.txt ou init.sql

### Funções globais disponíveis (auth.php)
- require_auth()              → bloqueia não logados
- require_can('permissao')    → bloqueia por role
- can('permissao')            → retorna bool para condicionais
- csrf_field()                → gera input hidden com token
- validate_csrf($token)       → valida ou mata a requisição
- set_flash($tipo, $msg)      → define mensagem de feedback
- get_flash()                 → lê e limpa o flash
- sanitize_string($input)     → higieniza input do usuário
- redirect_back('/pagina.php')→ redireciona após action

### RBAC — Permissões por Role
| Permissão       | admin | editor | demo |
|-----------------|-------|--------|------|
| edit_clients    | ✅    | ✅     | ❌   |
| send_charges    | ✅    | ✅     | ❌   |
| manage_users    | ✅    | ❌     | ❌   |
| view_all        | ✅    | ✅     | ✅   |

## Template: Nova Página

<?php
$page_title = 'Nome da Página';
require_once 'config/auth.php';
require_auth();
require_once 'includes/header.php';
?>
<!-- HTML + Tailwind aqui -->
<form method="POST" action="actions/recurso_action.php">
  <?= csrf_field() ?>
  <!-- campos -->
</form>
<?php require_once 'includes/footer.php'; ?>

## Template: Nova Action

<?php
require_once '../config/auth.php';
require_once '../config/conexao.php';

validate_csrf($_POST['csrf_token'] ?? '');
require_can('edit_clients'); // ajustar permissão

$campo = sanitize_string($_POST['campo'] ?? '');

try {
    $stmt = $pdo->prepare("INSERT INTO tabela (campo) VALUES (:campo)");
    $stmt->execute([':campo' => $campo]);
    set_flash('success', 'Operação realizada com sucesso!');
} catch (Exception $e) {
    set_flash('error', 'Erro ao processar. Tente novamente.');
}

redirect_back('/recurso.php');
