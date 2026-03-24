<?php
/**
 * app/config/email.php
 * Configuração SMTP — ADesign Financeiro
 * Servidor: Dreamhost (mail.allandesign.com.br)
 *
 * Para usar PHPMailer:
 *   composer require phpmailer/phpmailer
 *   ou: adicione no Dockerfile: RUN curl -sS https://getcomposer.org/installer | php && php composer.phar require phpmailer/phpmailer
 */

require_once __DIR__ . '/env.php';

// ── Saída (SMTP) ─────────────────────────────────────
define('SMTP_HOST',     env('SMTP_HOST', 'smtp.dreamhost.com'));
define('SMTP_PORT',     (int) env('SMTP_PORT', 587));
define('SMTP_SECURE',   'tls');
define('SMTP_USER',     env('SMTP_USER', ''));
define('SMTP_PASS',     env('SMTP_PASS', ''));
define('SMTP_FROM',     env('SMTP_FROM_EMAIL', 'financeiro@allandesign.com.br'));
define('SMTP_FROM_NAME',env('SMTP_FROM_NAME', 'ADesign Financeiro'));

// ── Entrada (IMAP) — referência para leitura de e-mails ──
define('IMAP_HOST',    env('IMAP_HOST', 'imap.dreamhost.com'));
define('IMAP_PORT',    993);
define('IMAP_SECURE',  'ssl');
define('IMAP_USER',    env('SMTP_USER', 'financeiro@allandesign.com.br'));

// ── Aplicação ─────────────────────────────────────────
define('APP_NAME',      'ADesign Financeiro');
define('APP_URL',       'https://clientes.allandesign.com.br');

/**
 * Envia e-mail via SMTP Dreamhost usando PHPMailer
 * Retorna true em sucesso, lança Exception em falha
 */
function send_email(string $to, string $to_name, string $subject, string $html_body): bool
{
    // O envio utilizará o e-mail real do cliente no banco de dados ($to)

    // Verificar se PHPMailer foi baixado
    $mailer_dir = __DIR__ . '/../../vendor/PHPMailer/src';
    if (!file_exists($mailer_dir . '/PHPMailer.php')) {
        // Fallback: mail() nativo (sem SSL, pode não funcionar com Dreamhost)
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
        return mail($to, $subject, $html_body, $headers);
    }

    require_once $mailer_dir . '/Exception.php';
    require_once $mailer_dir . '/PHPMailer.php';
    require_once $mailer_dir . '/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE === 'tls'
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress($to, $to_name);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html_body;

    return $mail->send();
}

/**
 * Template HTML de e-mail de cobrança
 */
function email_template_cobranca(array $cliente, string $vencimento): string
{
    $nome  = htmlspecialchars($cliente['nome']);
    $valor = 'R$ ' . number_format($cliente['valor_anual'], 2, ',', '.');
    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Cobrança - ADesign Financeiro</title></head>
<body style="font-family:Inter,Arial,sans-serif;background:#f8f9fa;padding:40px 0;margin:0">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)">
    <!-- Header -->
    <div style="background:#2e3132;padding:32px 40px;text-align:center">
      <p style="color:#99e000;font-size:22px;font-weight:900;margin:0;letter-spacing:-0.5px">ADesign Financeiro</p>
      <p style="color:#6b7280;font-size:12px;margin:4px 0 0">clientes.allandesign.com.br</p>
    </div>
    <!-- Body -->
    <div style="padding:40px">
      <p style="color:#1e293b;font-size:16px;font-weight:600;margin:0 0 16px">Olá, {$nome}!</p>
      <p style="color:#64748b;font-size:14px;line-height:1.7;margin:0 0 24px">
        Identificamos que a sua assinatura está com vencimento próximo ou pendente.
        Para evitar interrupções no serviço, realize o pagamento até <strong>{$vencimento}</strong>.
      </p>
      <div style="background:#f8f9fa;border-radius:12px;padding:20px;margin-bottom:24px">
        <p style="color:#64748b;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:0 0 4px">Valor em aberto</p>
        <p style="color:#1e293b;font-size:28px;font-weight:900;margin:0">{$valor}</p>
      </div>
      <p style="color:#64748b;font-size:13px;margin:0">
        Em caso de dúvidas, entre em contato pelo e-mail
        <a href="mailto:financeiro@allandesign.com.br" style="color:#456800">financeiro@allandesign.com.br</a>.
      </p>
    </div>
    <!-- Footer -->
    <div style="background:#f8f9fa;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0">
      <p style="color:#94a3b8;font-size:11px;margin:0">
        ADesign Financeiro · <a href="https://clientes.allandesign.com.br" style="color:#456800">clientes.allandesign.com.br</a>
      </p>
    </div>
  </div>
</body>
</html>
HTML;
}
