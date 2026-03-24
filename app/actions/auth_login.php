<?php
/**
 * app/actions/auth_login.php
 * Processa o formulário de login com CSRF + anti-brute-force
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

// Se já está logado, vai ao dashboard
if (is_authenticated()) {
    header('Location: /index.php');
    exit;
}

// ── 1. Valida CSRF ────────────────────────────
if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token de segurança inválido. Recarregue a página e tente novamente.');
    header('Location: /login.php');
    exit;
}

// ── 2. Captura e valida inputs ────────────────
$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
$senha = $_POST['password'] ?? '';
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (empty($email) || empty($senha)) {
    set_flash('error', 'Preencha e-mail e senha.');
    header('Location: /login.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Formato de e-mail inválido.');
    header('Location: /login.php');
    exit;
}

// ── 3. Anti-brute-force ───────────────────────
if (is_brute_force($pdo, $ip)) {
    set_flash('error', '🔒 Acesso bloqueado temporariamente. Aguarde 15 minutos e tente novamente.');
    header('Location: /login.php');
    exit;
}

// ── 4. Busca usuário e verifica senha ─────────
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        // ✅ Login bem-sucedido
        register_login_attempt($pdo, $ip, true);

        session_regenerate_id(true);
        $_SESSION['user_id']    = $usuario['id'];
        $_SESSION['user_nome']  = $usuario['nome'];
        $_SESSION['user_email'] = $usuario['email'];
        $_SESSION['user_role']  = $usuario['role'] ?? 'demo';

        // Atualiza data de último acesso
        $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?")
            ->execute([$usuario['id']]);

        set_flash('success', 'Bem-vindo(a), ' . $usuario['nome'] . '!');
        header('Location: /index.php');
        exit;

    } else {
        // ❌ Credenciais erradas
        register_login_attempt($pdo, $ip, false);
        $restantes = remaining_attempts($pdo, $ip);

        if ($restantes <= 0) {
            set_flash('error', '🔒 Muitas tentativas incorretas. Acesso bloqueado por 15 minutos.');
        } else {
            set_flash('error', "E-mail ou senha incorretos. Você tem {$restantes} tentativa(s) restante(s).");
        }

        header('Location: /login.php');
        exit;
    }

} catch (PDOException $e) {
    set_flash('error', 'Erro interno ao autenticar. Tente novamente.');
    header('Location: /login.php');
    exit;
}
