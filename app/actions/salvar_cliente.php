<?php
/**
 * app/actions/salvar_cliente.php
 * Cria um novo cliente com validação completa + CSRF
 * Issue 001: suporte a tipo_recorrencia, dia_vencimento e alertas
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';

require_auth();
require_can('edit_clients', '/clientes.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /clientes.php');
    exit;
}

// ── CSRF ──────────────────────────────────────
if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token de segurança inválido. Tente novamente.');
    header('Location: /clientes.php');
    exit;
}

// ── Sanitiza e valida ─────────────────────────
$nome         = sanitize_string($_POST['nome'] ?? '');
$email        = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
$dominio      = sanitize_string($_POST['dominio'] ?? '');
$valor        = filter_var(str_replace(',', '.', $_POST['valor_anual'] ?? '0'), FILTER_VALIDATE_FLOAT);
$tipo         = $_POST['tipo_pagamento'] ?? 'a vista';
$status       = $_POST['status'] ?? 'em dia';
$venc         = $_POST['data_vencimento_base'] ?? '';

// ── Issue 001: Novos campos de recorrência e alertas ──
$recorrencia      = $_POST['tipo_recorrencia'] ?? 'anual';
$dia_vencimento   = (int) ($_POST['dia_vencimento'] ?? 1);
$alerta_admin     = (int) ($_POST['alerta_admin_dias'] ?? 15);
$alerta_cliente   = (int) ($_POST['alerta_cliente_dias'] ?? 7);

$tipos_validos      = ['a vista', '2x', '3x'];
$status_validos     = ['em dia', 'pendente', 'vence em 15 dias'];
$recorrencia_valida = ['mensal', 'anual'];

$errors = [];
if (empty($nome))                              $errors[] = 'Nome do cliente é obrigatório.';
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Formato de e-mail inválido.';
if (!in_array($tipo, $tipos_validos))          $tipo = 'a vista';
if (!in_array($status, $status_validos))       $status = 'em dia';
if (!in_array($recorrencia, $recorrencia_valida)) $recorrencia = 'anual';
if ($valor === false || $valor < 0)            $valor = 0;
// Dia de vencimento obrigatório apenas para mensais; limite 1–28
$dia_vencimento = max(1, min(28, $dia_vencimento));
// Alertas: entre 1 e 60 dias
$alerta_admin   = max(1, min(60, $alerta_admin));
$alerta_cliente = max(1, min(60, $alerta_cliente));

$venc = !empty($venc) ? $venc : null;

if (!empty($errors)) {
    set_flash('error', implode(' | ', $errors));
    redirect_back('/clientes.php');
}

// ── INSERT ────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        INSERT INTO clientes (
            nome, email, dominio, valor_anual, tipo_pagamento, status, data_vencimento_base,
            tipo_recorrencia, dia_vencimento, alerta_admin_dias, alerta_cliente_dias
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $nome, $email ?: null, $dominio ?: null, $valor ?: null,
        $tipo, $status, $venc,
        $recorrencia, $dia_vencimento, $alerta_admin, $alerta_cliente,
    ]);

    set_flash('success', "✅ Cliente \"{$nome}\" cadastrado com sucesso!");
} catch (PDOException $e) {
    set_flash('error', 'Erro ao cadastrar cliente. Tente novamente.');
}

redirect_back('/clientes.php');
