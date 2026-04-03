<?php
/**
 * app/migrate_v5.php
 * ==================
 * Issue 005 — WhatsApp: adiciona colunas em clientes
 * e novas chaves em configuracoes.
 *
 * IDEMPOTENTE: verifica INFORMATION_SCHEMA antes de ALTER.
 * INSERT IGNORE protege chaves já existentes em configuracoes.
 *
 * Acesse: http://seudominio/migrate_v5.php
 * ⚠️  Remova após confirmar execução.
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/conexao.php';

require_auth();
require_can('manage_users', '/index.php');

$results = [];

// ── Helper: verifica se coluna já existe ──────────────────
function col_exists_v5(PDO $pdo, string $table, string $col): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = ?
          AND COLUMN_NAME  = ?
    ");
    $stmt->execute([$table, $col]);
    return (int) $stmt->fetchColumn() > 0;
}

// ── Helper: executa SQL e registra resultado ──────────────
function run5(PDO $pdo, string $label, string $sql): void
{
    global $results;
    try {
        $pdo->exec($sql);
        $results[] = ['ok', $label];
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate') || str_contains($msg, 'already exists')) {
            $results[] = ['skip', $label . ' (já existe)'];
        } else {
            $results[] = ['err', $label . ': ' . $msg];
        }
    }
}

// ══════════════════════════════════════════════════════════
// 1. Colunas em clientes
// ══════════════════════════════════════════════════════════

if (!col_exists_v5($pdo, 'clientes', 'whatsapp')) {
    run5($pdo, 'clientes.whatsapp (VARCHAR 20)', "
        ALTER TABLE clientes
        ADD COLUMN whatsapp VARCHAR(20) NULL
            COMMENT 'Número WhatsApp (somente dígitos com DDI)'
        AFTER email
    ");
} else {
    $results[] = ['skip', 'clientes.whatsapp (já existe)'];
}

if (!col_exists_v5($pdo, 'clientes', 'whatsapp_ativo')) {
    run5($pdo, 'clientes.whatsapp_ativo (TINYINT)', "
        ALTER TABLE clientes
        ADD COLUMN whatsapp_ativo TINYINT(1) NOT NULL DEFAULT 1
            COMMENT '1=recebe notificações WA, 0=opt-out'
        AFTER whatsapp
    ");
} else {
    $results[] = ['skip', 'clientes.whatsapp_ativo (já existe)'];
}

// ══════════════════════════════════════════════════════════
// 2. Chaves em configuracoes (INSERT IGNORE)
// ══════════════════════════════════════════════════════════

$template_vencimento = "Olá {nome}! 👋\n\nSua assinatura *{dominio}* vence em *{dias} dias* ({vencimento}).\n\n💰 Valor: *{valor}*\n\nPara efetuar o pagamento ou tirar dúvidas, entre em contato conosco.\n\n_ADesign Financeiro_ ✅";

$template_atraso = "Olá {nome}! ⚠️\n\nIdentificamos que sua assinatura *{dominio}* está em atraso desde {vencimento}.\n\n💰 Valor em aberto: *{valor}*\n\nPor favor, regularize para evitar a suspensão do serviço.\n\n_ADesign Financeiro_";

$defaults = [
    ['whatsapp_ativo',               '0',                   'WhatsApp habilitado globalmente (1=sim, 0=não)'],
    ['whatsapp_instance_id',         '',                    'Instance ID da instância Z-API'],
    ['whatsapp_token',               '',                    'Token da instância Z-API'],
    ['whatsapp_numero_proprio',      '',                    'Seu número WhatsApp para receber mensagens de teste'],
    ['whatsapp_template_vencimento', $template_vencimento,  'Template de mensagem de vencimento próximo'],
    ['whatsapp_template_atraso',     $template_atraso,      'Template de mensagem para pagamentos em atraso'],
    ['whatsapp_dias_antes',          '7',                   'Dias antes do vencimento para enviar WA'],
];

$stmt_ins = $pdo->prepare("
    INSERT IGNORE INTO configuracoes (chave, valor, descricao)
    VALUES (?, ?, ?)
");

$inseridos = 0;
$ignorados = 0;
foreach ($defaults as [$chave, $valor, $desc]) {
    $stmt_ins->execute([$chave, $valor, $desc]);
    if ($stmt_ins->rowCount() > 0) {
        $results[] = ['ok',   "Config inserida: {$chave}"];
        $inseridos++;
    } else {
        $results[] = ['skip', "Config já existe: {$chave}"];
        $ignorados++;
    }
}

$has_error = !empty(array_filter($results, fn($r) => $r[0] === 'err'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration v5 — ADesign Financeiro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; } .material-symbols-outlined { vertical-align: middle; }</style>
</head>
<body class="min-h-screen bg-[#f3f4f5] flex items-center justify-center p-6">
<div class="w-full max-w-xl">

    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-lg text-white"
             style="background:linear-gradient(135deg,#25d366,#128c7e)">💬</div>
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Migration v5</h1>
            <p class="text-xs text-slate-400">Issue 005 — WhatsApp: colunas + configurações</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
            <h2 class="font-bold text-slate-800 text-sm uppercase tracking-wider">Resultado</h2>
        </div>
        <ul class="divide-y divide-slate-50 max-h-96 overflow-y-auto">
            <?php foreach ($results as [$status, $msg]):
                $icon  = match($status) { 'ok' => 'check_circle', 'skip' => 'info', default => 'cancel' };
                $color = match($status) { 'ok' => 'text-green-600', 'skip' => 'text-slate-400', default => 'text-red-600' };
                $badge = match($status) { 'ok' => 'bg-green-100 text-green-700', 'skip' => 'bg-slate-100 text-slate-500', default => 'bg-red-100 text-red-700' };
                $label = match($status) { 'ok' => 'OK', 'skip' => 'Skip', default => 'Erro' };
            ?>
            <li class="flex items-center gap-4 px-6 py-3">
                <span class="material-symbols-outlined <?= $color ?> text-xl" style="font-variation-settings:'FILL' 1"><?= $icon ?></span>
                <p class="text-xs text-slate-700 flex-1 font-mono"><?= htmlspecialchars($msg) ?></p>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full <?= $badge ?>"><?= $label ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-400">
            2 colunas em clientes · 7 chaves em configuracoes · <?= $inseridos ?> inserida(s) · <?= $ignorados ?> já existia(m)
        </div>
    </div>

    <?php if (!$has_error): ?>
    <div class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 rounded-2xl px-5 py-4 mb-4">
        <span class="material-symbols-outlined text-xl shrink-0" style="font-variation-settings:'FILL' 1">check_circle</span>
        <div>
            <p class="font-bold text-sm">Migration v5 executada com sucesso!</p>
            <p class="text-xs mt-0.5">Configure as credenciais Z-API em Configurações → Alertas → WhatsApp.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-2xl px-5 py-4 mb-4">
        <span class="material-symbols-outlined text-xl shrink-0" style="font-variation-settings:'FILL' 1">cancel</span>
        <p class="font-bold text-sm">Concluído com erros. Verifique os itens acima.</p>
    </div>
    <?php endif; ?>

    <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-5 py-4 mb-6">
        <span class="material-symbols-outlined text-xl shrink-0" style="font-variation-settings:'FILL' 1">warning</span>
        <p class="text-xs font-medium">Remova <code class="bg-amber-100 px-1 rounded">migrate_v5.php</code> após confirmar a execução.</p>
    </div>

    <div class="flex gap-3">
        <a href="/configuracoes.php?secao=alertas"
           class="flex-1 flex items-center justify-center gap-2 h-11 rounded-xl font-bold text-white text-sm"
           style="background:linear-gradient(135deg,#25d366,#128c7e)">
            <span class="material-symbols-outlined text-lg">chat</span> Configurar WhatsApp
        </a>
        <a href="/index.php"
           class="flex items-center justify-center gap-2 px-5 h-11 rounded-xl font-bold text-slate-600 text-sm bg-white border border-slate-200 hover:bg-slate-50 transition-all">
            Dashboard
        </a>
    </div>
</div>
</body>
</html>
