<?php
/**
 * app/migrate.php
 * ===============
 * Script de migração — roda UMA VEZ no servidor de produção para instalar o banco.
 * Acesse: http://clientes.allandesign.com.br/migrate.php
 */

require_once __DIR__ . '/config/conexao.php';

header('Content-Type: text/html; charset=utf-8');
$log = [];
$ok  = true;

function run($pdo, string $label, string $sql): bool
{
    global $log;
    try {
        $pdo->exec($sql);
        $log[] = "✅ $label";
        return true;
    } catch (PDOException $e) {
        $log[] = "❌ $label — " . $e->getMessage();
        return false;
    }
}

// 1. Tabela usuarios
run($pdo, 'Tabela usuarios', "
    CREATE TABLE IF NOT EXISTS usuarios (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        nome           VARCHAR(255) NOT NULL,
        email          VARCHAR(255) UNIQUE NOT NULL,
        senha_hash     VARCHAR(255) NOT NULL,
        ativo          TINYINT(1) DEFAULT 1,
        role           ENUM('admin','editor','demo') NOT NULL DEFAULT 'editor',
        ultimo_acesso  DATETIME,
        criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 2. Tabela login_tentativas
run($pdo, 'Tabela login_tentativas', "
    CREATE TABLE IF NOT EXISTS login_tentativas (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        ip_address  VARCHAR(45) NOT NULL,
        sucesso     TINYINT(1) DEFAULT 0,
        criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_criado (ip_address, criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 3. Tabela password_resets
run($pdo, 'Tabela password_resets', "
    CREATE TABLE IF NOT EXISTS password_resets (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        email      VARCHAR(255) NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME    NOT NULL,
        usado      TINYINT(1)  NOT NULL DEFAULT 0,
        ip_address VARCHAR(45)          DEFAULT NULL,
        criado_em  TIMESTAMP            DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token_hash),
        INDEX idx_email (email),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 4. Tabela clientes
run($pdo, 'Tabela clientes', "
    CREATE TABLE IF NOT EXISTS clientes (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        nome                  VARCHAR(255) NOT NULL,
        email                 VARCHAR(255) DEFAULT NULL,
        dominio               VARCHAR(255) DEFAULT NULL,
        valor_anual           DECIMAL(10,2) DEFAULT NULL,
        tipo_pagamento        VARCHAR(50) DEFAULT NULL,
        data_vencimento_base  DATE DEFAULT NULL,
        status                ENUM('em dia', 'pendente', 'vence em 15 dias') DEFAULT 'em dia',
        anotacoes             TEXT DEFAULT NULL,
        criado_em             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_vencimento (data_vencimento_base)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 4.1 Inserir clientes iniciais se a tabela estiver vazia
$check_cli = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
if ($check_cli == 0) {
    try {
        $pdo->exec("
            INSERT INTO clientes (nome, email, dominio, valor_anual, tipo_pagamento, status, data_vencimento_base) VALUES
            ('Tech Nova Solutions', 'contato@technova.com', 'technova.com', 3200.00, 'a vista', 'em dia', DATE_ADD(CURDATE(), INTERVAL 60 DAY)),
            ('Boutique Elegance', 'vendas@elegance.com.br', 'elegance.com.br', 1500.00, '2x', 'pendente', DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
            ('Digital Strategy Co', 'admin@digitalstrategy.io', 'digitalstrategy.io', 4800.00, '3x', 'vence em 15 dias', DATE_ADD(CURDATE(), INTERVAL 15 DAY));
        ");
        $log[] = "✅ Clientes de demonstração importados para o banco de dados.";
    } catch (PDOException $e) {
        $log[] = "❌ Falha ao importar clientes — " . $e->getMessage();
    }
} else {
    $log[] = "ℹ️  Base de clientes já possui dados — pulando importação automática.";
}

// 5. Usuário padrão admin
$email_admin = 'contato@allandesign.com.br';
$check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
$check->execute([$email_admin]);

if (!$check->fetch()) {
    $hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
    $ins  = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, role) VALUES (?, ?, ?, 'admin')");
    if ($ins->execute(['Administrador', $email_admin, $hash])) {
        $log[] = '✅ Usuário admin criado (contato@allandesign.com.br / admin123)';
    } else {
        $log[] = '❌ Falha ao criar usuário admin';
        $ok = false;
    }
} else {
    $log[] = "ℹ️  Usuário admin ($email_admin) já existe — pulando";
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Instalação — ADesign Financeiro</title>
<style>
  body { font-family: 'Courier New', monospace; background: #1a1a2e; color: #e2e8f0; padding: 40px; }
  h1 { color: #99E000; margin-bottom: 24px; }
  .log { background: #0f0f1a; border-radius: 12px; padding: 24px; line-height: 2; }
  .btn { display: inline-block; margin-top: 32px; padding: 14px 32px; background: #99E000; color: #1a1a2e; border-radius: 8px; font-weight: bold; text-decoration: none; }
  .warn { color: #fbbf24; margin-top: 20px; font-size: 0.85em; }
</style>
</head>
<body>
<h1>🏗️ ADesign Financeiro — Instalação do Banco</h1>
<div class="log">
<?php foreach ($log as $line): ?>
    <div><?= htmlspecialchars($line) ?></div>
<?php endforeach; ?>
</div>

<?php if ($ok): ?>
<br>
<strong style="color:#99E000">✅ Banco de Dados instalado com sucesso!</strong><br>
<a class="btn" href="/login.php">Ir para o Login →</a>
<p class="warn">
    ⚠️ Credenciais de acesso:<br>
    <strong>E-mail:</strong> contato@allandesign.com.br<br>
    <strong>Senha:</strong> admin123<br><br>
    Por favor, apague ou bloqueie este arquivo (migrate.php) por segurança.
</p>
<?php else: ?>
<br>
<strong style="color:#ef4444">❌ Alguns passos falharam. Verifique as credenciais no .env.</strong>
<?php endif; ?>
</body>
</html>
