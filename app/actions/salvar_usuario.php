<?php
/**
 * app/actions/salvar_usuario.php
 * Cria ou atualiza usuário (apenas admin)
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

$id    = (int) ($_POST['id'] ?? 0);   // 0 = novo usuário
$nome  = sanitize_string($_POST['nome']  ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$role  = $_POST['role'] ?? 'demo';
$senha = $_POST['senha'] ?? '';

// Validações básicas
if (!$nome || !$email) {
    set_flash('error', 'Nome e e-mail são obrigatórios.');
    header('Location: /configuracoes.php#usuarios');
    exit;
}

if (!in_array($role, ['editor', 'demo'])) {
    // Admin não pode criar outro admin pela UI (apenas via migrate)
    set_flash('error', 'Role inválido.');
    header('Location: /configuracoes.php#usuarios');
    exit;
}

try {
    if ($id === 0) {
        // ── CRIAR ────────────────────────────────────────────
        if (empty($senha) || strlen($senha) < 8) {
            set_flash('error', 'A senha precisa ter pelo menos 8 caracteres.');
            header('Location: /configuracoes.php#usuarios');
            exit;
        }

        // Verifica e-mail duplicado
        $existe = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $existe->execute([$email]);
        if ($existe->fetchColumn()) {
            set_flash('error', 'Este e-mail já está cadastrado.');
            header('Location: /configuracoes.php#usuarios');
            exit;
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, role, ativo) VALUES (?, ?, ?, ?, 1)')
            ->execute([$nome, $email, $hash, $role]);

        set_flash('success', "Usuário \"{$nome}\" criado com sucesso.");
    } else {
        // ── EDITAR ───────────────────────────────────────────
        // Não pode editar o próprio usuário admin (id=1) por aqui
        $alvo = $pdo->prepare('SELECT id, role FROM usuarios WHERE id = ?');
        $alvo->execute([$id]);
        $alvo = $alvo->fetch();

        if (!$alvo) {
            set_flash('error', 'Usuário não encontrado.');
            header('Location: /configuracoes.php#usuarios');
            exit;
        }

        // Admin principal protegido
        if ($alvo['role'] === 'admin' && auth_user()['id'] != $id) {
            set_flash('error', 'Não é possível alterar o usuário admin principal.');
            header('Location: /configuracoes.php#usuarios');
            exit;
        }

        if (!empty($senha)) {
            if (strlen($senha) < 8) {
                set_flash('error', 'A nova senha precisa ter pelo menos 8 caracteres.');
                header('Location: /configuracoes.php#usuarios');
                exit;
            }
            $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE usuarios SET nome=?, email=?, senha_hash=?, role=? WHERE id=?')
                ->execute([$nome, $email, $hash, $role, $id]);
        } else {
            $pdo->prepare('UPDATE usuarios SET nome=?, email=?, role=? WHERE id=?')
                ->execute([$nome, $email, $role, $id]);
        }

        set_flash('success', "Usuário \"{$nome}\" atualizado com sucesso.");
    }
} catch (PDOException $e) {
    set_flash('error', 'Erro ao salvar usuário: ' . $e->getMessage());
}

header('Location: /configuracoes.php#usuarios');
exit;
