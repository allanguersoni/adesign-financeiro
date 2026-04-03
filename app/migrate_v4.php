<?php
/**
 * app/migrate_v4.php
 * ==================
 * Issue 003 — Cria tabela `configuracoes` e insere valores padrão.
 *
 * IDEMPOTENTE: CREATE TABLE IF NOT EXISTS + INSERT IGNORE
 * Pode ser executado múltiplas vezes sem efeito colateral.
 *
 * SEGURANÇA: Exige admin autenticado.
 * Acesse: http://seudominio/migrate_v4.php
 * ⚠️  Remova ou mova para fora da webroot após confirmar a execução.
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/conexao.php';

require_auth();
require_can('manage_users', '/index.php');

$results = [];

function run4(PDO $pdo, string $label, string $sql): void
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

// ── 1. Criar tabela configuracoes ─────────────────────────
run4($pdo, 'CREATE TABLE configuracoes', "
    CREATE TABLE IF NOT EXISTS configuracoes (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        chave      VARCHAR(100)  NOT NULL,
        valor      TEXT          NULL,
        descricao  VARCHAR(255)  NULL,
        updated_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_chave (chave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 2. Inserir valores padrão (INSERT IGNORE = seguro ──────
//    Chaves já customizadas pelo admin NÃO serão sobrescritas
$defaults = [
    ['nome_empresa',               'ADesign Financeiro',                      'Nome exibido nos e-mails e no sistema'],
    ['url_sistema',                'https://clientes.allandesign.com.br',     'URL base usada nos links dos e-mails'],
    ['email_rodape_cobranca',      '',                                        'Texto do rodapé nos e-mails de cobrança'],
    ['alertas_email_admin',        '1',                                       'Enviar alertas de vencimento ao admin (1=sim, 0=não)'],
    ['alerta_admin_dias_padrao',   '15',                                      'Dias antes do vencimento para alertar admin (padrão global)'],
    ['alertas_email_cliente',      '1',                                       'Enviar alertas de vencimento ao cliente (1=sim, 0=não)'],
    ['alerta_cliente_dias_padrao', '7',                                       'Dias antes do vencimento para alertar cliente (padrão global)'],
    ['alertas_whatsapp',           '0',                                       'Alertas via WhatsApp (em desenvolvimento)'],
    ['notificador_ultimo_disparo', null,                                      'Timestamp da última execução manual do notificador'],
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
        $results[] = ['ok',  "Padrão inserido: {$chave} = " . ($valor ?? 'NULL')];
        $inseridos++;
    } else {
        $results[] = ['skip', "Padrão já existe: {$chave}"];
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
    <title>Migration v4 — ADesign Financeiro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: '#456800', lime: '#99e000' } } } }</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { vertical-align: middle; }
    </style>
</head>
<body class="min-h-screen bg-[#f3f4f5] flex items-center justify-center p-6">
<div class="w-full max-w-xl">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-lg"
             style="background:linear-gradient(135deg,#99e000,#456800);color:#fff">⚙</div>
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Migration v4</h1>
            <p class="text-xs text-slate-400">Issue 003 — Tabela de Configurações</p>
        </div>
    </div>

    <!-- Resultados -->
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
                <span class="material-symbols-outlined <?= $color ?> text-xl"
                      style="font-variation-settings:'FILL' 1"><?= $icon ?></span>
                <p class="text-sm text-slate-700 flex-1 font-mono text-xs"><?= htmlspecialchars($msg) ?></p>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full <?= $badge ?>"><?= $label ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-400">
            <?= $inseridos ?> inserido(s) · <?= $ignorados ?> já existia(m)
        </div>
    </div>

    <!-- Status global -->
    <?php if ($has_error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-2xl px-5 py-4 mb-4">
        <span class="material-symbols-outlined text-xl shrink-0" style="font-variation-settings:'FILL' 1">cancel</span>
        <div>
            <p class="font-bold text-sm">Migration concluída com erros.</p>
            <p class="text-xs mt-0.5">Verifique os itens acima.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 rounded-2xl px-5 py-4 mb-4">
        <span class="material-symbols-outlined text-xl shrink-0" style="font-variation-settings:'FILL' 1">check_circle</span>
        <div>
            <p class="font-bold text-sm">Migration v4 executada com sucesso!</p>
            <p class="text-xs mt-0.5">Tabela <code class="bg-green-100 px-1 rounded">configuracoes</code> pronta. Acesse as configurações para personalizar.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Aviso de segurança -->
    <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-5 py-4 mb-6">
        <span class="material-symbols-outlined text-xl shrink-0" style="font-variation-settings:'FILL' 1">warning</span>
        <p class="text-xs font-medium leading-relaxed">
            <strong>Segurança:</strong> Remova ou mova <code class="bg-amber-100 px-1 rounded">migrate_v4.php</code>
            para fora da pasta pública após confirmar a execução.
        </p>
    </div>

    <!-- Ações -->
    <div class="flex gap-3">
        <a href="/configuracoes.php?secao=alertas"
           class="flex-1 flex items-center justify-center gap-2 h-11 rounded-xl font-bold text-white text-sm transition-all"
           style="background:linear-gradient(135deg,#5a8000,#3a5600);box-shadow:0 4px 16px rgba(69,104,0,.25)">
            <span class="material-symbols-outlined text-lg">settings</span>
            Ir para Configurações
        </a>
        <a href="/index.php"
           class="flex items-center justify-center gap-2 px-5 h-11 rounded-xl font-bold text-slate-600 text-sm bg-white border border-slate-200 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-lg">dashboard</span>
            Dashboard
        </a>
    </div>

</div>
</body>
</html>
