<?php
/**
 * app/actions/get_cliente.php
 * Retorna JSON de um cliente para pré-preencher o modal de edição
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';

require_auth();

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, email, dominio, valor_anual, tipo_pagamento, status, data_vencimento_base
        FROM clientes WHERE id = ?
    ");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente não encontrado']);
        exit;
    }

    echo json_encode($cliente);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar cliente']);
}
