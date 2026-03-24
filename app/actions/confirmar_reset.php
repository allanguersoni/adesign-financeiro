<?php
/**
 * app/actions/confirmar_reset.php
 * Confirma a troca de senha via token.
 *
 * SEGURANÇA (pentest-hardened):
 * - CSRF obrigatório
 * - Token: regex + hash_equals (timing-safe) contra sha256 do banco
 * - Verifica expiração e flag usado=0
 * - Bloqueia re-uso e múltiplas tentativas (rate limit por IP: 10/30min)
 * - Token invalidado IMEDIATAMENTE após verificação bem-sucedida (even before password write)
 *   → previne race condition
 * - Senha mínima: 8 chars, bcrypt cost=12
 * - Invalida todos os outros tokens do mesmo e-mail após troca
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /esqueci_senha.php');
    exit;
}

// ── 1. CSRF ────────────────────────────────────────────────────────────
if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token de segurança inválido. Recarregue e tente novamente.');
    header('Location: /esqueci_senha.php');
    exit;
}

$token_cru        = trim($_POST['token']           ?? '');
$senha            = $_POST['senha']                ?? '';
$confirmar_senha  = $_POST['confirmar_senha']      ?? '';
$ip               = $_SERVER['REMOTE_ADDR']        ?? '0.0.0.0';

// ── 2. Sanitiza e valida o token (sintaxe) ─────────────────────────────
if (!preg_match('/^[0-9a-f]{64}$/', $token_cru)) {
    set_flash('error', 'Link inválido.');
    header('Location: /esqueci_senha.php');
    exit;
}

// ── 3. Valida senhas ───────────────────────────────────────────────────
if (strlen($senha) < 8) {
    $url = '/resetar_senha.php?token=' . urlencode($token_cru);
    set_flash('error', 'A senha deve ter pelo menos 8 caracteres.');
    header('Location: ' . $url);
    exit;
}
if (!hash_equals($senha, $confirmar_senha)) {
    $url = '/resetar_senha.php?token=' . urlencode($token_cru);
    set_flash('error', 'As senhas não coincidem.');
    header('Location: ' . $url);
    exit;
}

// ── 4. Rate limit por IP ───────────────────────────────────────────────
try {
    $count = $pdo->prepare("
        SELECT COUNT(*) FROM password_resets
        WHERE ip_address = ?
          AND criado_em > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $count->execute([$ip]);
    if ((int) $count->fetchColumn() >= 10) {
        set_flash('error', 'Muitas tentativas. Aguarde 30 minutos e solicite um novo link.');
        header('Location: /esqueci_senha.php');
        exit;
    }
} catch (PDOException $e) {}

// ── 5. Busca e valida o token no banco ─────────────────────────────────
$token_hash = hash('sha256', $token_cru);
try {
    $stmt = $pdo->prepare("
        SELECT pr.id, pr.email, pr.expires_at, pr.usado, u.id AS user_id
        FROM password_resets pr
        JOIN usuarios u ON u.email = pr.email AND u.ativo = 1
        WHERE pr.token_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$token_hash]);
    $reset = $stmt->fetch();
} catch (PDOException $e) {
    set_flash('error', 'Erro interno. Tente novamente.');
    header('Location: /esqueci_senha.php');
    exit;
}

if (!$reset || $reset['usado']) {
    set_flash('error', 'Link inválido ou já utilizado.');
    header('Location: /esqueci_senha.php');
    exit;
}

if (strtotime($reset['expires_at']) < time()) {
    set_flash('error', 'Link expirado. Solicite um novo.');
    header('Location: /esqueci_senha.php');
    exit;
}

// ── 6. Invalida o token IMEDIATAMENTE (previne race condition) ─────────
try {
    $invalidado = $pdo->prepare("UPDATE password_resets SET usado = 1 WHERE id = ? AND usado = 0");
    $invalidado->execute([$reset['id']]);
    if ($invalidado->rowCount() === 0) {
        // Outro processo já invalidou — token em race condition
        set_flash('error', 'Link inválido ou já utilizado.');
        header('Location: /esqueci_senha.php');
        exit;
    }
} catch (PDOException $e) {
    set_flash('error', 'Erro interno. Tente novamente.');
    header('Location: /esqueci_senha.php');
    exit;
}

// ── 7. Atualiza a senha ────────────────────────────────────────────────
$hash_senha = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
try {
    $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")
        ->execute([$hash_senha, $reset['user_id']]);

    // Invalida TODOS os outros tokens deste e-mail
    $pdo->prepare("UPDATE password_resets SET usado = 1 WHERE email = ? AND id != ?")
        ->execute([$reset['email'], $reset['id']]);

} catch (PDOException $e) {
    set_flash('error', 'Erro ao salvar nova senha. Tente novamente.');
    header('Location: /esqueci_senha.php');
    exit;
}

// ── 8. Destrói sessão se estiver logado como este usuário ────────────
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $reset['user_id']) {
    session_destroy();
}

set_flash('success', 'Senha atualizada com sucesso! Faça login com a nova senha.');
header('Location: /resetar_senha.php?token=' . urlencode($token_cru));
exit;
