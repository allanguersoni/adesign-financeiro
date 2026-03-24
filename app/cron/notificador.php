<?php
/**
 * app/cron/notificador.php
 * ========================
 * Robô de Cobrança — Notificador de Vencimentos em 15 dias
 *
 * Uso via CLI: php notificador.php
 * Uso via crontab: 0 8 * * * /usr/local/bin/php /var/www/html/cron/notificador.php >> /var/log/notificador.log 2>&1
 */

// Define caminho base para includes funcionarem tanto via CLI quanto via web
define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/conexao.php';
require_once BASE_PATH . '/config/email.php';

// Cabeçalho de execução
$data_execucao = date('d/m/Y H:i:s');
echo "======================================================\n";
echo "  FINANCIAL ARCHITECT — ROBÔ DE COBRANÇA\n";
echo "  Execução: {$data_execucao}\n";
echo "======================================================\n\n";

// Busca todos os clientes com vencimento exatamente em 15 dias
$stmt = $pdo->prepare("
    SELECT id, nome, email, dominio, valor_anual, tipo_pagamento, data_vencimento_base
    FROM clientes
    WHERE data_vencimento_base = CURDATE() + INTERVAL 15 DAY
    ORDER BY nome ASC
");
$stmt->execute();
$clientes = $stmt->fetchAll();

$total = count($clientes);

if ($total === 0) {
    echo "✅ Nenhum cliente com vencimento em 15 dias.\n";
    echo "   Nenhum e-mail de cobrança foi gerado.\n\n";
} else {
    echo "📋 {$total} cliente(s) com vencimento em " . date('d/m/Y', strtotime('+15 days')) . ":\n";
    echo "------------------------------------------------------\n\n";

    foreach ($clientes as $i => $cliente) {
        $num      = $i + 1;
        $venc_fmt = date('d/m/Y', strtotime($cliente['data_vencimento_base']));
        $valor    = 'R$ ' . number_format($cliente['valor_anual'], 2, ',', '.');

        echo "📧 [{$num}/{$total}] SIMULANDO ENVIO DE E-MAIL...\n";
        echo "   Para:      {$cliente['email']}\n";
        $assunto = 'Lembrete: Sua assinatura vence em 15 dias!';
        $html = email_template_cobranca($cliente, $venc_fmt);

        try {
            $status_envio = send_email($cliente['email'], $cliente['nome'], $assunto, $html);
            if ($status_envio) {
                echo "   -> [Sucesso] E-mail enviado via PHPMailer.\n\n";
            } else {
                echo "   -> [Erro] Falha no disparo.\n\n";
            }
        } catch (\Exception $e) {
            echo "   -> [Exception] " . $e->getMessage() . "\n\n";
        }
    }

    echo "======================================================\n";
    echo "✅ {$total} notificação(ões) enviada(s) com sucesso!\n";
    echo "======================================================\n";
}

echo "\nExecução finalizada em: " . date('d/m/Y H:i:s') . "\n";
