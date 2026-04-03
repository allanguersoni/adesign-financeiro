<?php
/**
 * app/actions/confirmar_pagamento.php
 * Marca um pagamento como pago (INSERT ou UPDATE via ON DUPLICATE KEY).
 * Cria o registro se não existir.
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

// ── Inputs ─────────────────────────────────────────────────
$cliente_id  = (int) ($_POST['cliente_id']  ?? 0);
$competencia = trim($_POST['competencia']    ?? '');  // YYYY-MM-01
$valor       = filter_var(str_replace(',', '.', $_POST['valor'] ?? '0'), FILTER_VALIDATE_FLOAT);
$metodo_raw  = $_POST['metodo']              ?? '';
$data_pag    = trim($_POST['data_pagamento'] ?? date('Y-m-d'));
$observacao  = sanitize_string($_POST['observacao'] ?? '');

// ── Validações ─────────────────────────────────────────────
$metodos_validos = ['pix', 'dinheiro', 'transferencia', 'cartao'];

$errors = [];
if ($cliente_id <= 0)                                    $errors[] = 'Cliente inválido.';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $competencia)) $errors[] = 'Competência inválida.';
if ($valor === false || $valor < 0)                      $valor = 0;
if (!in_array($metodo_raw, $metodos_validos))            $errors[] = 'Método de pagamento inválido.';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_pag))    $data_pag = date('Y-m-d');

if (!empty($errors)) {
    set_flash('error', implode(' | ', $errors));
    redirect_back('/pagamentos.php');
}

// ── Verifica se cliente existe ─────────────────────────────
$check = $pdo->prepare("SELECT nome FROM clientes WHERE id = ?");
$check->execute([$cliente_id]);
$cliente = $check->fetch();
if (!$cliente) {
    set_flash('error', 'Cliente não encontrado.');
    redirect_back('/pagamentos.php');
}

// ── Upsert: cria ou atualiza para 'pago' ──────────────────
// ON DUPLICATE KEY UPDATE funciona com a UNIQUE KEY (cliente_id, competencia)
try {
    $stmt = $pdo->prepare("
        INSERT INTO pagamentos (cliente_id, competencia, valor, status, pago_em, metodo, observacao)
        VALUES (:cliente_id, :competencia, :valor, 'pago', :pago_em, :metodo, :observacao)
        ON DUPLICATE KEY UPDATE
            status     = 'pago',
            pago_em    = VALUES(pago_em),
            metodo     = VALUES(metodo),
            valor      = VALUES(valor),
            observacao = VALUES(observacao)
    ");
    $stmt->execute([
        ':cliente_id'  => $cliente_id,
        ':competencia' => $competencia,
        ':valor'       => $valor ?: null,
        ':pago_em'     => $data_pag . ' ' . date('H:i:s'),
        ':metodo'      => $metodo_raw,
        ':observacao'  => $observacao ?: null,
    ]);

    // strftime() foi depreciada no PHP 8.1 — usando DateTimeImmutable
    $dt_comp  = new DateTimeImmutable($competencia);
    $meses_pt = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                 'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    $nome_mes = $meses_pt[(int) $dt_comp->format('n')] . '/' . $dt_comp->format('Y');

    set_flash('success', "✅ Pagamento de \"{$cliente['nome']}\" confirmado como pago ({$nome_mes}).");
} catch (PDOException $e) {
    set_flash('error', 'Erro ao confirmar pagamento. Tente novamente.');
}

redirect_back('/pagamentos.php');
