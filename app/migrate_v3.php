<?php
/**
 * app/migrate_v3.php
 * ==================
 * Adiciona UNIQUE KEY (cliente_id, competencia) na tabela pagamentos.
 * Necessária para INSERT IGNORE funcionar corretamente.
 * Idempotente: pode ser executada múltiplas vezes sem erro.
 *
 * Acesse: http://localhost/migrate_v3.php
 * Remova após execução confirmada!
 */

define('BASE_PATH', __DIR__);
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/auth.php';
require_auth();

$results = [];

function run(PDO $pdo, string $label, string $sql): void {
    global $results;
    try {
        $pdo->exec($sql);
        $results[] = ['ok', $label];
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Ignora erros de "já existe" (duplicate key/index)
        if (str_contains($msg, 'Duplicate') || str_contains($msg, 'already exists')) {
            $results[] = ['skip', $label . ' (já existe)'];
        } else {
            $results[] = ['err', $label . ': ' . $msg];
        }
    }
}

// ── Adiciona UNIQUE KEY se não existir ─────────────────────
run($pdo, 'UNIQUE KEY (cliente_id, competencia) em pagamentos', "
    ALTER TABLE pagamentos
    ADD CONSTRAINT uk_pagamento_cliente_competencia
    UNIQUE KEY (cliente_id, competencia)
");

// ── Adiciona coluna observacao se não existir ───────────────
// ADD COLUMN IF NOT EXISTS não é suportado em todas as versões do MySQL.
// Verificamos via INFORMATION_SCHEMA antes de executar o ALTER.
$col_exists = $pdo->prepare("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'pagamentos'
      AND COLUMN_NAME  = 'observacao'
");
$col_exists->execute();
if ((int) $col_exists->fetchColumn() === 0) {
    run($pdo, 'Coluna observacao em pagamentos', "
        ALTER TABLE pagamentos
        ADD COLUMN observacao TEXT NULL AFTER metodo
    ");
} else {
    $results[] = ['skip', 'Coluna observacao em pagamentos (já existe)'];
}

// ── Saída ───────────────────────────────────────────────────
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Migration v3 — ADesign Financeiro</title>
    <style>
        body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 40px; }
        h1   { color: #99e000; margin-bottom: 24px; }
        .ok   { color: #4ade80; } .skip { color: #facc15; } .err { color: #f87171; }
        a    { color: #99e000; }
    </style>
</head>
<body>
<h1>Migration v3 — Controle de Pagamentos</h1>
<?php foreach ($results as [$status, $msg]): ?>
<p class="<?= $status ?>">
    <?= $status === 'ok' ? '✅' : ($status === 'skip' ? '⚠️' : '❌') ?>
    <?= htmlspecialchars($msg) ?>
</p>
<?php endforeach; ?>
<hr style="border-color:#334155;margin:20px 0">
<p>✔ Migration concluída. <a href="pagamentos.php">→ Ir para Pagamentos</a></p>
<p style="color:#64748b;font-size:12px">⚠️ Remova este arquivo após confirmar a execução.</p>
</body>
</html>
