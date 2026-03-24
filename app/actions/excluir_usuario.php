<?php
/**
 * app/actions/excluir_usuario.php
 * Remove usuário (apenas admin, não pode excluir a si mesmo ou o admin principal)
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
$me = auth_user()['id'];

if ($id === 0 || $id === (int)$me) {
    set_flash('error', 'Não é possível excluir sua própria conta.');
    header('Location: /configuracoes.php#usuarios');
    exit;
}

try {
    $usuario = $pdo->prepare('SELECT nome, role FROM usuarios WHERE id = ?');
    $usuario->execute([$id]);
    $usuario = $usuario->fetch();

    if (!$usuario) {
        set_flash('error', 'Usuário não encontrado.');
        header('Location: /configuracoes.php#usuarios');
        exit;
    }

    // Protege o admin principal (id=1)
    if ($id === 1) {
        set_flash('error', 'O usuário administrador principal não pode ser excluído.');
        header('Location: /configuracoes.php#usuarios');
        exit;
    }

    $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
    set_flash('success', "Usuário \"{$usuario['nome']}\" excluído com sucesso.");
} catch (PDOException $e) {
    set_flash('error', 'Erro ao excluir usuário.');
}

header('Location: /configuracoes.php#usuarios');
exit;
