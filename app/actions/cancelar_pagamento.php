<?php
/**
 * app/actions/cancelar_pagamento.php
 * Cancela um pagamento existente. Exclusivo para admin.
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';

require_auth();
require_can('manage_users', '/pagamentos.php'); // só admin

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pagamentos.php');
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token de segurança inválido. Tente novamente.');
    redirect_back('/pagamentos.php');
}

$pagamento_id = (int) ($_POST['pagamento_id'] ?? 0);

if ($pagamento_id <= 0) {
    set_flash('error', 'ID de pagamento inválido.');
    redirect_back('/pagamentos.php');
}

try {
    // Verifica se existe e busca dados para o flash
    $check = $pdo->prepare("
        SELECT p.id, c.nome, p.competencia
        FROM pagamentos p
        JOIN clientes c ON c.id = p.cliente_id
        WHERE p.id = ?
    ");
    $check->execute([$pagamento_id]);
    $pag = $check->fetch();

    if (!$pag) {
        set_flash('error', 'Pagamento não encontrado.');
        redirect_back('/pagamentos.php');
    }

    $stmt = $pdo->prepare("
        UPDATE pagamentos SET status = 'cancelado' WHERE id = ?
    ");
    $stmt->execute([$pagamento_id]);

    $nome_mes = date('m/Y', strtotime($pag['competencia']));
    set_flash('success', "✅ Pagamento de \"{$pag['nome']}\" ({$nome_mes}) cancelado.");
} catch (PDOException $e) {
    set_flash('error', 'Erro ao cancelar pagamento. Tente novamente.');
}

redirect_back('/pagamentos.php');
