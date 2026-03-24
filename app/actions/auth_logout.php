<?php
/**
 * app/actions/auth_logout.php
 * Destrói a sessão de forma segura e redireciona ao login
 */

require_once __DIR__ . '/../config/auth.php';

// Limpa todos os dados da sessão
$_SESSION = [];

// Remove o cookie de sessão do browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: /login.php?bye=1');
exit;
