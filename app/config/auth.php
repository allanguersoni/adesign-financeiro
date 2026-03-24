<?php
/**
 * app/config/auth.php
 * Central de segurança: sessão, CSRF, flash messages, brute-force
 */

// Configurações de segurança da sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 7200);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    // Previne fixação de sessão
    if (empty($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}

// ──────────────────────────────────────────────
// AUTENTICAÇÃO
// ──────────────────────────────────────────────

function is_authenticated(): bool
{
    return !empty($_SESSION['user_id']);
}

function require_auth(): void
{
    if (!is_authenticated()) {
        header('Location: /login.php');
        exit;
    }
}

function auth_user(): array
{
    return [
        'id'    => $_SESSION['user_id']    ?? 0,
        'nome'  => $_SESSION['user_nome']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role']  ?? 'demo',
    ];
}

function auth_role(): string
{
    return $_SESSION['user_role'] ?? 'demo';
}

/**
 * Verifica se o usuário logado tem permissão para uma ação.
 * Hierarquia: admin > editor > demo
 *
 * Permissões disponíveis:
 *  'edit_clients'   — criar/editar/excluir clientes
 *  'send_charges'   — enviar cobranças
 *  'edit_profile'   — editar próprio nome/notificações
 *  'manage_users'   — criar/editar/excluir usuários (só admin)
 *  'view_all'       — visualizar qualquer página (todos os roles)
 */
function can(string $permission): bool
{
    $role = auth_role();
    return match($permission) {
        'view_all'      => true,                                                // todos
        'edit_profile'  => in_array($role, ['admin', 'editor']),                // admin + editor
        'edit_clients'  => in_array($role, ['admin', 'editor']),                // admin + editor
        'send_charges'  => in_array($role, ['admin', 'editor']),                // admin + editor
        'manage_users'  => $role === 'admin',                                   // só admin
        default         => false,
    };
}

/** Exige uma permission específica ou redireciona com flash de erro */
function require_can(string $permission, string $redirect = '/index.php'): void
{
    if (!can($permission)) {
        set_flash('error', 'Acesso negado. Sua conta não tem permissão para esta ação.');
        header('Location: ' . $redirect);
        exit;
    }
}

/** Atalho: bloqueia usuário demo (apenas leitura) */
function require_not_demo(string $redirect = '/index.php'): void
{
    require_can('edit_profile', $redirect);
}

/** Badge HTML do role para exibição */
function role_badge(string $role): string
{
    return match($role) {
        'admin'  => '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary/15 text-primary uppercase tracking-wider">⭐ Admin</span>',
        'editor' => '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 uppercase tracking-wider">✏️ Editor</span>',
        'demo'   => '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 uppercase tracking-wider">👁 Demo</span>',
        default  => '<span class="text-xs text-slate-400">' . htmlspecialchars($role) . '</span>',
    };
}


// ──────────────────────────────────────────────
// CSRF
// ──────────────────────────────────────────────

function get_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf(string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token()) . '">';
}

// ──────────────────────────────────────────────
// MENSAGENS FLASH
// ──────────────────────────────────────────────

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ──────────────────────────────────────────────
// PROTEÇÃO ANTI BRUTE-FORCE
// ──────────────────────────────────────────────

function is_brute_force(PDO $pdo, string $ip): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_tentativas
            WHERE ip_address = ?
              AND sucesso = 0
              AND criado_em > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn() >= 5;
    } catch (PDOException $e) {
        return false;
    }
}

function remaining_attempts(PDO $pdo, string $ip): int
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_tentativas
            WHERE ip_address = ?
              AND sucesso = 0
              AND criado_em > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ip]);
        return max(0, 5 - (int) $stmt->fetchColumn());
    } catch (PDOException $e) {
        return 5;
    }
}

function register_login_attempt(PDO $pdo, string $ip, bool $success): void
{
    try {
        $pdo->prepare("INSERT INTO login_tentativas (ip_address, sucesso) VALUES (?, ?)")
            ->execute([$ip, $success ? 1 : 0]);

        // Limpa tentativas com mais de 24h
        $pdo->exec("DELETE FROM login_tentativas WHERE criado_em < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    } catch (PDOException $e) {
        // Falha silenciosa — não impede o fluxo principal
    }
}

// ──────────────────────────────────────────────
// SANITIZAÇÃO
// ──────────────────────────────────────────────

function sanitize_string(string $value): string
{
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function redirect_back(string $fallback = '/index.php'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? $fallback;
    // Só aceita URLs do mesmo host
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $safe = parse_url($ref, PHP_URL_HOST);
    if ($safe !== null && $safe !== $host) {
        $ref = $fallback;
    }
    header('Location: ' . $ref);
    exit;
}
