<?php
/**
 * app/cron/notificador.php
 * ========================
 * Robô de Cobrança — Notificador de Vencimentos Personalizado
 *
 * Usa alerta_admin_dias de cada cliente (Issue 001) em vez de 15 fixo.
 * Lógica: busca clientes com vencimento nos próximos 60 dias
 * e verifica no PHP se hoje == data_vencimento - alerta_admin_dias.
 *
 * Uso via CLI:     php notificador.php
 * Uso via crontab: 0 8 * * * /usr/local/bin/php /var/www/html/cron/notificador.php >> /var/log/notificador.log 2>&1
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/conexao.php';
require_once BASE_PATH . '/config/email.php';

// ── Cabeçalho de execução ─────────────────────────────
$data_execucao = date('d/m/Y H:i:s');
echo "======================================================\n";
echo "  ADESIGN FINANCEIRO — ROBÔ DE NOTIFICAÇÕES\n";
echo "  Execução: {$data_execucao}\n";
echo "======================================================\n\n";

// ── Busca clientes com vencimento nos próximos 60 dias ─
// A verificação de "alerta_admin_dias antes" é feita no PHP
// pois SQL não permite comparar colunas com expressões dinâmicas
// por cliente sem subquery (ineficiente). A janela de 60 dias
// garante que nenhum alerta seja perdido.
$stmt = $pdo->prepare("
    SELECT
        id, nome, email, dominio,
        valor_anual, tipo_pagamento,
        data_vencimento_base,
        alerta_admin_dias,
        tipo_recorrencia
    FROM clientes
    WHERE data_vencimento_base BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY
      AND email IS NOT NULL
      AND email != ''
    ORDER BY data_vencimento_base ASC, nome ASC
");
$stmt->execute();
$todos = $stmt->fetchAll();

// ── Filtra: só quem deve ser notificado HOJE ──────────
$hoje = new DateTimeImmutable('today');
$para_notificar = [];

foreach ($todos as $c) {
    $dias_alerta = max(1, (int) ($c['alerta_admin_dias'] ?? 15));
    $venc        = new DateTimeImmutable($c['data_vencimento_base']);
    $diff        = (int) $hoje->diff($venc)->days;   // dias até o vencimento

    // Notifica quando: hoje == vencimento - alerta_admin_dias
    if ($diff === $dias_alerta) {
        $para_notificar[] = $c;
    }
}

$total = count($para_notificar);

// ── Processa envios ───────────────────────────────────
if ($total === 0) {
    echo "✅ Nenhum cliente a ser notificado hoje.\n";
    echo "   (Critério: data_vencimento - alerta_admin_dias = hoje)\n\n";
} else {
    echo "📋 {$total} cliente(s) a notificar hoje:\n";
    echo "------------------------------------------------------\n\n";

    $enviados = 0;
    $falhas   = 0;

    foreach ($para_notificar as $i => $cliente) {
        $num      = $i + 1;
        $venc_fmt = date('d/m/Y', strtotime($cliente['data_vencimento_base']));
        $valor    = 'R$ ' . number_format((float)($cliente['valor_anual'] ?? 0), 2, ',', '.');
        $dias     = $cliente['alerta_admin_dias'];
        $recorr   = ucfirst($cliente['tipo_recorrencia'] ?? 'anual');

        echo "📧 [{$num}/{$total}] {$cliente['nome']}\n";
        echo "   Para:      {$cliente['email']}\n";
        echo "   Venc:      {$venc_fmt} (em {$dias} dias) | {$recorr} | {$valor}\n";

        $assunto = "Lembrete: Sua assinatura vence em {$dias} dias!";
        $html    = email_template_cobranca($cliente, $venc_fmt);

        try {
            $ok = send_email($cliente['email'], $cliente['nome'], $assunto, $html);
            if ($ok) {
                echo "   -> [✅ Sucesso] E-mail enviado via SMTP.\n\n";
                $enviados++;
            } else {
                echo "   -> [⚠️  Erro] send_email() retornou false.\n\n";
                $falhas++;
            }
        } catch (\Exception $e) {
            echo "   -> [❌ Exception] " . $e->getMessage() . "\n\n";
            $falhas++;
        }
    }

    echo "======================================================\n";
    echo "  ✅ Enviados: {$enviados}   ❌ Falhas: {$falhas}\n";
    echo "======================================================\n";
}

echo "\nExecução finalizada em: " . date('d/m/Y H:i:s') . "\n";
