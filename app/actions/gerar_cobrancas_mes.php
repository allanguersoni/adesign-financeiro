<?php
/**
 * app/actions/gerar_cobrancas_mes.php
 * Gera registros pendentes em pagamentos para todos os clientes
 * elegíveis no mês informado.
 * Usa INSERT IGNORE — requer UNIQUE KEY (cliente_id, competencia).
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';

require_auth();
require_can('edit_clients', '/pagamentos.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pagamentos.php');
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token de segurança inválido. Tente novamente.');
    redirect_back('/pagamentos.php');
}

// ── Valida mês ─────────────────────────────────────────────
$mes_raw = trim($_POST['mes'] ?? '');  // formato YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $mes_raw)) {
    set_flash('error', 'Mês inválido. Use o formato YYYY-MM.');
    redirect_back('/pagamentos.php');
}

[$ano, $mes] = explode('-', $mes_raw);
$ano = (int) $ano;
$mes = (int) $mes;

if ($mes < 1 || $mes > 12 || $ano < 2020 || $ano > 2100) {
    set_flash('error', 'Mês ou ano fora do intervalo permitido.');
    redirect_back('/pagamentos.php');
}

$primeiro_dia = sprintf('%04d-%02d-01', $ano, $mes);

// ── Busca clientes mensais ─────────────────────────────────
$stmt_mensais = $pdo->prepare("
    SELECT id, valor_anual,
           LEAST(dia_vencimento, DAY(LAST_DAY(:primeiro_dia))) AS dia_venc_real
    FROM clientes
    WHERE tipo_recorrencia = 'mensal'
");
$stmt_mensais->execute([':primeiro_dia' => $primeiro_dia]);
$mensais = $stmt_mensais->fetchAll();

// ── Busca clientes anuais que vencem neste mês/ano ─────────
$stmt_anuais = $pdo->prepare("
    SELECT id, valor_anual, data_vencimento_base
    FROM clientes
    WHERE tipo_recorrencia = 'anual'
      AND MONTH(data_vencimento_base) = :mes
      AND YEAR(data_vencimento_base)  = :ano
");
$stmt_anuais->execute([':mes' => $mes, ':ano' => $ano]);
$anuais = $stmt_anuais->fetchAll();

// ── INSERT IGNORE ──────────────────────────────────────────
$stmt_insert = $pdo->prepare("
    INSERT IGNORE INTO pagamentos (cliente_id, competencia, valor, status)
    VALUES (:cliente_id, :competencia, :valor, 'pendente')
");

$criados = 0;

foreach ($mensais as $c) {
    $stmt_insert->execute([
        ':cliente_id'  => $c['id'],
        ':competencia' => $primeiro_dia,
        ':valor'       => $c['valor_anual'] ?? 0,
    ]);
    $criados += $stmt_insert->rowCount();
}

foreach ($anuais as $c) {
    $stmt_insert->execute([
        ':cliente_id'  => $c['id'],
        ':competencia' => $primeiro_dia,
        ':valor'       => $c['valor_anual'] ?? 0,
    ]);
    $criados += $stmt_insert->rowCount();
}

$total_clientes = count($mensais) + count($anuais);
$ignorados      = $total_clientes - $criados;

if ($criados > 0) {
    set_flash('success', "✅ {$criados} cobrança(s) gerada(s) para " . date('m/Y', strtotime($primeiro_dia)) . ". ({$ignorados} já existiam.)");
} else {
    set_flash('success', "ℹ️ Todas as cobranças de " . date('m/Y', strtotime($primeiro_dia)) . " já existiam. Nenhuma duplicata criada.");
}

redirect_back('/pagamentos.php');
