<?php
/**
 * app/config/whatsapp.php
 * =======================
 * Client isolado para envio de mensagens via Z-API.
 * Nunca loga tokens, instance IDs ou números completos.
 *
 * Funções exportadas:
 *   send_whatsapp(string $numero, string $mensagem): bool
 *   format_whatsapp_number(string $numero): string
 *   whatsapp_template(string $template, array $vars): string
 *   proximo_vencimento_mensal(int $dia): DateTimeImmutable
 */

/**
 * Remove formatação e garante DDI 55 (Brasil).
 */
function format_whatsapp_number(string $numero): string
{
    // Remove tudo que não é dígito
    $apenas_digitos = preg_replace('/\D/', '', $numero);

    // Se vazio ou muito curto → retorna como está
    if (strlen($apenas_digitos) < 8) {
        return $apenas_digitos;
    }

    // Adiciona DDI 55 se não começar com 55
    if (!str_starts_with($apenas_digitos, '55')) {
        $apenas_digitos = '55' . $apenas_digitos;
    }

    return $apenas_digitos;
}

/**
 * Substitui variáveis no template de mensagem.
 *
 * Suporta: {nome}, {valor}, {vencimento}, {dominio}, {dias}
 */
function whatsapp_template(string $template, array $vars): string
{
    $search  = [];
    $replace = [];
    foreach ($vars as $chave => $valor) {
        $search[]  = '{' . $chave . '}';
        $replace[] = (string) $valor;
    }
    return str_replace($search, $replace, $template);
}

/**
 * Calcula a data do próximo vencimento para clientes mensais.
 * Trata edge cases: dia 31 em fevereiro, dia de hoje já passou, etc.
 */
function proximo_vencimento_mensal(int $dia): DateTimeImmutable
{
    $hoje = new DateTimeImmutable('today');
    $ano  = (int) $hoje->format('Y');
    $mes  = (int) $hoje->format('m');

    // Ajusta para não ultrapassar o último dia do mês atual
    $ultimo_do_mes = (int) $hoje->format('t'); // 't' = total de dias no mês
    $dia_real      = min($dia, $ultimo_do_mes);

    $venc = DateTimeImmutable::createFromFormat('Y-n-j', "{$ano}-{$mes}-{$dia_real}");

    // Se vencimento já passou ou é hoje → avança para o próximo mês
    if ($venc <= $hoje) {
        $venc_prox   = $venc->modify('+1 month');
        $ultimo_prox = (int) $venc_prox->format('t');
        $dia_prox    = min($dia, $ultimo_prox);
        $venc        = DateTimeImmutable::createFromFormat(
            'Y-n-j',
            $venc_prox->format('Y-n-') . $dia_prox
        );
    }

    return $venc;
}

/**
 * Envia mensagem via Z-API.
 *
 * Fail-fast silencioso se não configurado.
 * Logs com número mascarado (apenas últimos 4 dígitos).
 * Timeout: 10s geral, 5s para conexão.
 */
function send_whatsapp(string $numero, string $mensagem): bool
{
    // 1. Verifica configuração global
    if (setting('whatsapp_ativo', '0') !== '1') {
        return false; // Skip silencioso — WA desativado
    }

    $instance_id = setting('whatsapp_instance_id', '');
    $token       = setting('whatsapp_token', '');

    if (empty($instance_id) || empty($token)) {
        return false; // Skip silencioso — sem credenciais
    }

    // 2. Formata e valida o número
    $numero_fmt = format_whatsapp_number($numero);
    if (strlen($numero_fmt) < 12) {  // mínimo: 55 + DDD + 8 dígitos
        error_log('[WhatsApp] Número inválido (muito curto): ***' . substr($numero_fmt, -4));
        return false;
    }

    // 3. Prepara a requisição
    $url  = "https://api.z-api.io/instances/{$instance_id}/token/{$token}/send-text";
    $body = json_encode([
        'phone'   => $numero_fmt,
        'message' => $mensagem,
    ], JSON_UNESCAPED_UNICODE);

    // 4. cURL com timeout seguro
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,   // máximo 10s total
        CURLOPT_CONNECTTIMEOUT => 5,    // máximo 5s para conectar
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    // 5. Tratamento de erros — número mascarado nos logs
    $numero_log = '***' . substr($numero_fmt, -4);

    if ($curl_err) {
        error_log("[WhatsApp] cURL erro para {$numero_log}: {$curl_err}");
        return false;
    }

    if ($http_code < 200 || $http_code >= 300) {
        error_log("[WhatsApp] HTTP {$http_code} para {$numero_log}");
        return false;
    }

    // 6. Valida resposta Z-API (sucesso = tem zaapId ou messageId)
    $data = json_decode($response, true);
    $ok   = isset($data['zaapId']) || isset($data['messageId']);

    if (!$ok) {
        error_log("[WhatsApp] Resposta inesperada para {$numero_log}: " . substr($response, 0, 200));
    }

    return $ok;
}
