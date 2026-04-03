<?php
/**
 * app/migrate_v2.php — Migration Issue 001: Recorrência Mensal/Anual
 *
 * Adiciona suporte a clientes mensais e cria tabela de pagamentos.
 * ─────────────────────────────────────────────────────────────────
 * SEGURANÇA: Exige admin autenticado.
 * IDEMPOTÊNCIA: Usa IF NOT EXISTS / verificação via INFORMATION_SCHEMA.
 * Pode ser executado múltiplas vezes sem efeito colateral.
 * ─────────────────────────────────────────────────────────────────
 * COMO USAR: Acesse /migrate_v2.php uma única vez pelo navegador.
 *            Após confirmar sucesso, delete ou mova para fora do DocRoot.
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/conexao.php';

require_auth();
require_can('manage_users', '/index.php');

// ─────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────

/**
 * Verifica se uma coluna já existe na tabela antes de tentar adicionar.
 */
function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = ?
          AND COLUMN_NAME  = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

// ─────────────────────────────────────────────────────────────────
// Execução das migrations
// ─────────────────────────────────────────────────────────────────

$results = [];   // ['label' => '...', 'status' => 'ok|skip|error', 'msg' => '']

/**
 * Roda uma migration e registra o resultado.
 */
function run_migration(PDO $pdo, string $label, callable $fn, array &$results): void
{
    try {
        $status = $fn($pdo);
        $results[] = ['label' => $label, 'status' => $status, 'msg' => ''];
    } catch (Exception $e) {
        $results[] = ['label' => $label, 'status' => 'error', 'msg' => $e->getMessage()];
    }
}

// ── 1. tipo_recorrencia ───────────────────────────────────────────
run_migration($pdo, 'clientes.tipo_recorrencia', function (PDO $pdo): string {
    if (column_exists($pdo, 'clientes', 'tipo_recorrencia')) return 'skip';
    $pdo->exec("ALTER TABLE clientes ADD COLUMN tipo_recorrencia ENUM('mensal','anual') NOT NULL DEFAULT 'anual'");
    return 'ok';
}, $results);

// ── 2. dia_vencimento ─────────────────────────────────────────────
run_migration($pdo, 'clientes.dia_vencimento', function (PDO $pdo): string {
    if (column_exists($pdo, 'clientes', 'dia_vencimento')) return 'skip';
    $pdo->exec("ALTER TABLE clientes ADD COLUMN dia_vencimento TINYINT UNSIGNED NOT NULL DEFAULT 1");
    return 'ok';
}, $results);

// ── 3. alerta_admin_dias ──────────────────────────────────────────
run_migration($pdo, 'clientes.alerta_admin_dias', function (PDO $pdo): string {
    if (column_exists($pdo, 'clientes', 'alerta_admin_dias')) return 'skip';
    $pdo->exec("ALTER TABLE clientes ADD COLUMN alerta_admin_dias TINYINT UNSIGNED NOT NULL DEFAULT 15");
    return 'ok';
}, $results);

// ── 4. alerta_cliente_dias ────────────────────────────────────────
run_migration($pdo, 'clientes.alerta_cliente_dias', function (PDO $pdo): string {
    if (column_exists($pdo, 'clientes', 'alerta_cliente_dias')) return 'skip';
    $pdo->exec("ALTER TABLE clientes ADD COLUMN alerta_cliente_dias TINYINT UNSIGNED NOT NULL DEFAULT 7");
    return 'ok';
}, $results);

// ── 5. Tabela pagamentos ──────────────────────────────────────────
run_migration($pdo, 'CREATE TABLE pagamentos', function (PDO $pdo): string {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pagamentos (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            cliente_id  INT NOT NULL,
            competencia DATE NOT NULL COMMENT 'Mês/ano de referência do pagamento',
            valor       DECIMAL(10,2) NOT NULL,
            status      ENUM('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
            pago_em     DATETIME NULL,
            metodo      VARCHAR(50) NULL COMMENT 'pix, boleto, cartao, transferencia...',
            created_at  DATETIME NOT NULL DEFAULT NOW(),
            CONSTRAINT fk_pag_cliente
                FOREIGN KEY (cliente_id) REFERENCES clientes(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    return 'ok';
}, $results);

// ─────────────────────────────────────────────────────────────────
// Calcula resultado global
// ─────────────────────────────────────────────────────────────────
$has_error = array_filter($results, fn($r) => $r['status'] === 'error');
$all_skip  = count(array_filter($results, fn($r) => $r['status'] === 'skip')) === count($results);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Migration v2 | ADesign Financeiro</title>
    <link rel="icon" type="image/png" href="assets/img/icons/logo.png"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: {
                primary: "#456800",
                "primary-container": "#99e000",
            }}}
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { vertical-align: middle; }
    </style>
</head>
<body class="min-h-screen bg-[#f3f4f5] flex items-center justify-center p-6">

<div class="w-full max-w-xl">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
        <img src="assets/img/icons/logo.png" alt="Logo" class="w-10 h-10 object-contain"/>
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Migration v2</h1>
            <p class="text-xs text-slate-400">Issue 001 — Recorrência Mensal/Anual</p>
        </div>
    </div>

    <!-- Card de resultados -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
            <h2 class="font-bold text-slate-800 text-sm uppercase tracking-wider">
                Resultado das Migrations
            </h2>
        </div>

        <ul class="divide-y divide-slate-50">
            <?php foreach ($results as $r):
                $icon  = match($r['status']) { 'ok' => 'check_circle', 'skip' => 'info', default => 'cancel' };
                $color = match($r['status']) { 'ok' => 'text-green-600', 'skip' => 'text-slate-400', default => 'text-red-600' };
                $label = match($r['status']) { 'ok' => 'Executado', 'skip' => 'Já existe', default => 'Erro' };
            ?>
            <li class="flex items-center gap-4 px-6 py-4">
                <span class="material-symbols-outlined <?= $color ?> text-xl"
                      style="font-variation-settings:'FILL' 1">
                    <?= $icon ?>
                </span>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-700 font-mono"><?= htmlspecialchars($r['label']) ?></p>
                    <?php if ($r['msg']): ?>
                    <p class="text-xs text-red-500 mt-0.5"><?= htmlspecialchars($r['msg']) ?></p>
                    <?php endif; ?>
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full
                    <?= match($r['status']) {
                        'ok'    => 'bg-green-100 text-green-700',
                        'skip'  => 'bg-slate-100 text-slate-500',
                        default => 'bg-red-100 text-red-700'
                    } ?>">
                    <?= $label ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Status global -->
    <?php if ($has_error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-2xl px-5 py-4 mb-4">
        <span class="material-symbols-outlined text-xl shrink-0" style="font-variation-settings:'FILL' 1">cancel</span>
        <div>
            <p class="font-bold text-sm">Migration concluída com erros.</p>
            <p class="text-xs mt-0.5">Verifique os itens acima e tente novamente.</p>
        </div>
    </div>
    <?php elseif ($all_skip): ?>
    <div class="flex items-start gap-3 bg-slate-50 border border-slate-200 text-slate-600 rounded-2xl px-5 py-4 mb-4">
        <span class="material-symbols-outlined text-xl shrink-0">info</span>
        <div>
            <p class="font-bold text-sm">Nenhuma alteração necessária.</p>
            <p class="text-xs mt-0.5">Todas as colunas e tabelas já existem. O banco está atualizado.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 rounded-2xl px-5 py-4 mb-4">
        <span class="material-symbols-outlined text-xl shrink-0" style="font-variation-settings:'FILL' 1">check_circle</span>
        <div>
            <p class="font-bold text-sm">Migration executada com sucesso!</p>
            <p class="text-xs mt-0.5">O banco está atualizado para a v2. Você já pode usar os novos campos.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Aviso de segurança -->
    <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-5 py-4 mb-6">
        <span class="material-symbols-outlined text-xl shrink-0" style="font-variation-settings:'FILL' 1">warning</span>
        <p class="text-xs font-medium leading-relaxed">
            <strong>Recomendação de segurança:</strong> Após confirmar o sucesso da migration,
            delete ou mova <code class="bg-amber-100 px-1 rounded">migrate_v2.php</code>
            para fora da pasta pública do servidor.
        </p>
    </div>

    <!-- Ação -->
    <a href="/index.php"
       class="flex items-center justify-center gap-2 w-full h-11 rounded-xl font-bold text-white text-sm transition-all"
       style="background:linear-gradient(135deg,#5a8000,#3a5600);box-shadow:0 4px 16px rgba(69,104,0,.25)">
        <span class="material-symbols-outlined text-lg">arrow_back</span>
        Voltar ao Dashboard
    </a>

</div>
</body>
</html>
