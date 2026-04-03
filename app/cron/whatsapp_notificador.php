<?php
/**
 * app/cron/whatsapp_notificador.php
 * ==================================
 * Worker diário de notificações WhatsApp.
 *
 * Lógica espelhada ao notificador.php de e-mail, mas para WhatsApp.
 *
 * Uso via CLI:     php whatsapp_notificador.php
 * Uso via crontab: 0 9 * * * /usr/local/bin/php /var/www/html/cron/whatsapp_notificador.php >> /var/log/wpp_notificador.log 2>&1
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/conexao.php';
require_once BASE_PATH . '/config/auth.php';
require_once BASE_PATH . '/config/settings.php';
require_once BASE_PATH . '/config/whatsapp.php';

// ── Cabeçalho ─────────────────────────────────────────────
$data_exec = date('d/m/Y H:i:s');
echo "======================================================\n";
echo "  ADESIGN FINANCEIRO — ROBÔ WHATSAPP\n";
echo "  Execução: {$data_exec}\n";
echo "======================================================\n\n";

// ── 1. Verifica se WhatsApp está ativo ────────────────────
if (setting('whatsapp_ativo', '0') !== '1') {
    echo "⏭️  WhatsApp desabilitado nas configurações. Encerrando.\n";
    exit(0);
}

$instance_id = setting('whatsapp_instance_id', '');
$token       = setting('whatsapp_token', '');

if (empty($instance_id) || empty($token)) {
    echo "❌ Credenciais Z-API não configuradas (Instance ID / Token).\n";
    echo "   Acesse Configurações → Alertas → WhatsApp para configurar.\n";
    exit(1);
}

$dias_antes        = max(1, (int) setting('whatsapp_dias_antes', '7'));
$tpl_vencimento    = setting('whatsapp_template_vencimento', '');
$tpl_atraso        = setting('whatsapp_template_atraso', '');
$hoje              = new DateTimeImmutable('today');

echo "📋 Configurações:\n";
echo "   Dias antes do vencimento: {$dias_antes}\n";
echo "   Hoje: " . $hoje->format('d/m/Y') . "\n\n";

// ── 2. Busca clientes com WhatsApp ────────────────────────
$todos_stmt = $pdo->prepare("
    SELECT id, nome, dominio, valor_anual,
           whatsapp, whatsapp_ativo,
           tipo_recorrencia, dia_vencimento,
           data_vencimento_base
    FROM clientes
    WHERE whatsapp IS NOT NULL
      AND whatsapp != ''
      AND whatsapp_ativo = 1
    ORDER BY nome ASC
");
$todos_stmt->execute();
$todos = $todos_stmt->fetchAll();

if (empty($todos)) {
    echo "✅ Nenhum cliente com WhatsApp cadastrado e ativo.\n";
    exit(0);
}

echo "👥 " . count($todos) . " cliente(s) com WhatsApp ativo.\n\n";

// ── 3. Filtra quem deve receber mensagem hoje ─────────────
$para_notificar = [];

foreach ($todos as $c) {
    if ($c['tipo_recorrencia'] === 'anual') {
        // Anuais: DATEDIFF exato
        if (empty($c['data_vencimento_base'])) continue;

        $venc = new DateTimeImmutable($c['data_vencimento_base']);
        $diff = (int) $hoje->diff($venc)->days;

        // Só notifica se vencimento é no futuro e diff = dias_antes
        if ($venc > $hoje && $diff === $dias_antes) {
            $c['_data_venc_fmt'] = $venc->format('d/m/Y');
            $c['_diff']          = $diff;
            $c['_template']      = $tpl_vencimento;
            $para_notificar[]    = $c;
        }

        // Atraso: já passou e ainda sem pagamento confirmado (diff negativo)
        if ($venc < $hoje) {
            $diff_atraso = (int) $venc->diff($hoje)->days;
            // Notifica no 1º dia de atraso e a cada 7 dias
            if ($diff_atraso === 1 || $diff_atraso % 7 === 0) {
                $c['_data_venc_fmt'] = $venc->format('d/m/Y');
                $c['_diff']          = $diff_atraso;
                $c['_template']      = $tpl_atraso;
                $para_notificar[]    = $c;
            }
        }
    } else {
        // Mensais: calcula próximo vencimento
        $dia_venc = max(1, min(28, (int) $c['dia_vencimento']));
        $venc     = proximo_vencimento_mensal($dia_venc);
        $diff     = (int) $hoje->diff($venc)->days;

        if ($diff === $dias_antes) {
            $c['_data_venc_fmt'] = $venc->format('d/m/Y');
            $c['_diff']          = $diff;
            $c['_template']      = $tpl_vencimento;
            $para_notificar[]    = $c;
        }
    }
}

$total = count($para_notificar);

if ($total === 0) {
    echo "✅ Nenhum cliente a notificar por WhatsApp hoje.\n";
    echo "   (Critério: vencimento em exatamente {$dias_antes} dias)\n";
    exit(0);
}

echo "📲 {$total} cliente(s) a notificar hoje:\n";
echo "------------------------------------------------------\n\n";

// ── 4. Envia mensagens ─────────────────────────────────────
$enviados = 0;
$falhas   = 0;

foreach ($para_notificar as $i => $cliente) {
    $num    = $i + 1;
    $valor  = 'R$ ' . number_format((float) ($cliente['valor_anual'] ?? 0), 2, ',', '.');

    $mensagem = whatsapp_template($cliente['_template'], [
        'nome'       => $cliente['nome'],
        'dominio'    => $cliente['dominio'] ?? '—',
        'valor'      => $valor,
        'vencimento' => $cliente['_data_venc_fmt'],
        'dias'       => $cliente['_diff'],
    ]);

    $numero_log = '***' . substr(preg_replace('/\D/', '', $cliente['whatsapp']), -4);
    echo "💬 [{$num}/{$total}] {$cliente['nome']}\n";
    echo "   WhatsApp: {$numero_log}\n";
    echo "   Venc: {$cliente['_data_venc_fmt']} (em {$cliente['_diff']} dias)\n";

    $ok = send_whatsapp($cliente['whatsapp'], $mensagem);

    if ($ok) {
        echo "   → [✅ Enviado]\n\n";
        $enviados++;
    } else {
        echo "   → [❌ Falha] Verifique logs do PHP.\n\n";
        $falhas++;
    }
}

echo "======================================================\n";
echo "  ✅ Enviados: {$enviados}   ❌ Falhas: {$falhas}\n";
echo "======================================================\n";
echo "\nFinalizado em: " . date('d/m/Y H:i:s') . "\n";
