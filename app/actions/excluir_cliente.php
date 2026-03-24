<?php
/**
 * app/actions/excluir_cliente.php
 * Remove um cliente com confirmação CSRF
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

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'ID de cliente inválido.');
    redirect_back('/clientes.php');
}

// ── DELETE ────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT nome FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        set_flash('error', 'Cliente não encontrado.');
        redirect_back('/clientes.php');
    }

    $pdo->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);

    set_flash('success', "🗑️ Cliente \"{$cliente['nome']}\" excluído com sucesso.");
} catch (PDOException $e) {
    set_flash('error', 'Erro ao excluir cliente. Tente novamente.');
}

redirect_back('/clientes.php');
