<?php
/**
 * app/actions/toggle_usuario.php
 * Ativa ou desativa um usuário (apenas admin)
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';

require_auth();
require_can('manage_users', '/configuracoes.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /configuracoes.php');
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token de segurança inválido.');
    header('Location: /configuracoes.php#usuarios');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id === 1 || $id === (int) auth_user()['id']) {
    set_flash('error', 'Não é possível desativar esta conta.');
    header('Location: /configuracoes.php#usuarios');
    exit;
}

try {
    $pdo->prepare('UPDATE usuarios SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
    set_flash('success', 'Status do usuário atualizado.');
} catch (PDOException $e) {
    set_flash('error', 'Erro ao atualizar usuário.');
}

header('Location: /configuracoes.php#usuarios');
exit;
