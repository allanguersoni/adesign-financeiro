<?php
/**
 * app/actions/salvar_config.php
 * Processa o formulário de configurações
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';

require_auth();
require_can('edit_profile', '/configuracoes.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /configuracoes.php');
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token de segurança inválido.');
    header('Location: /configuracoes.php');
    exit;
}

// Salva preferências na sessão do usuário (extensível para DB)
$_SESSION['config_notif_vencimento'] = isset($_POST['notif_vencimento']) ? 1 : 0;
$_SESSION['config_notif_semanal']    = isset($_POST['notif_semanal'])    ? 1 : 0;
$_SESSION['config_notif_fraude']     = isset($_POST['notif_fraude'])     ? 1 : 0;

// Atualiza nome do usuário se alterado
$novo_nome = sanitize_string($_POST['nome'] ?? '');
if (!empty($novo_nome) && $novo_nome !== ($_SESSION['user_nome'] ?? '')) {
    try {
        $pdo->prepare("UPDATE usuarios SET nome = ? WHERE id = ?")
            ->execute([$novo_nome, $_SESSION['user_id']]);
        $_SESSION['user_nome'] = $novo_nome;
    } catch (PDOException $e) {
        // Silently fail
    }
}

set_flash('success', '✅ Configurações salvas com sucesso!');
header('Location: /configuracoes.php');
exit;
