<?php
/**
 * app/actions/solicitar_reset.php
 * Processa a solicitação de reset de senha.
 *
 * SEGURANÇA (pentest-hardened):
 * - Sem user enumeration: resposta idêntica para e-mail existente ou não
 * - Rate limiting: máx. 3 solicitações por IP em 30 min
 * - Token: criptograficamente aleatório (32 bytes = 256 bits)
 * - DB guarda somente o hash do token (sha256), nunca o token cru
 * - Token expira em 30 min e é single-use
 * - Solicitar novo reset invalida tokens anteriores para o mesmo e-mail
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/env.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /esqueci_senha.php');
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token de segurança inválido. Recarregue e tente novamente.');
    header('Location: /esqueci_senha.php');
    exit;
}

$email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? ''));
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ── Mensagem genérica para TODA resposta (evita user enumeration) ──────
$msg_generica = 'Se este e-mail estiver cadastrado, você receberá as instruções em instantes. Verifique sua caixa de entrada e o spam.';

// ── 1. Rate limiting por IP: máx. 3 requests em 30 min ────────────────
try {
    $count_ip = $pdo->prepare("
        SELECT COUNT(*) FROM password_resets
        WHERE ip_address = ?
          AND criado_em > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $count_ip->execute([$ip]);
    if ((int) $count_ip->fetchColumn() >= 3) {
        // Resposta idêntica — não revela que foi bloqueado
        set_flash('success', $msg_generica);
        header('Location: /esqueci_senha.php');
        exit;
    }
} catch (PDOException $e) {
    // Falha silenciosa — não impede o fluxo
}

// ── 2. Verifica se o e-mail existe (mas não revela ao usuário) ─────────
$usuario = null;
try {
    $stmt = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    // Silencioso
}

// ── 3. Invalida tokens anteriores do mesmo e-mail ─────────────────────
if ($usuario) {
    try {
        $pdo->prepare("UPDATE password_resets SET usado = 1 WHERE email = ? AND usado = 0")
            ->execute([$email]);
    } catch (PDOException $e) {}
}

// ── 4. Gera token, persiste hash no banco e envia e-mail ──────────────
if ($usuario) {
    $token_cru  = bin2hex(random_bytes(32));   // 64 chars hex, 256 bits
    $token_hash = hash('sha256', $token_cru);  // guardado no banco
    $expires_at = date('Y-m-d H:i:s', time() + 1800); // 30 min

    try {
        $pdo->prepare("
            INSERT INTO password_resets (email, token_hash, expires_at, ip_address)
            VALUES (?, ?, ?, ?)
        ")->execute([$email, $token_hash, $expires_at, $ip]);

        // ── Envia e-mail ─────────────────────────────────────────────
        // Detecta host dinâmico (local vs produção)
        $proto  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'clientes.allandesign.com.br';
        $link   = "{$proto}://{$host}/resetar_senha.php?token=" . urlencode($token_cru);

        _enviar_email_reset($usuario['nome'], $usuario['email'], $link, $expires_at);
    } catch (PDOException $e) {
        // Token não salvo — fluxo silencioso, usuário recebe msg genérica
    }
}

// ── 5. Sempre retorna a mesma mensagem ────────────────────────────────
set_flash('success', $msg_generica);
header('Location: /esqueci_senha.php');
exit;


// ══════════════════════════════════════════════════════════════════════
// Função de envio (usa PHPMailer se disponível, fallback mail() nativo)
// ══════════════════════════════════════════════════════════════════════
function _enviar_email_reset(string $nome, string $email, string $link, string $expires_at): void
{
    $expiry_br = date('d/m/Y \à\s H:i', strtotime($expires_at));

    $assunto  = 'Recuperação de senha — ADesign Financeiro';
    $corpo_html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Inter',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 16px">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0">
      <!-- Header -->
      <tr>
        <td style="background:#0f172a;padding:28px 40px;text-align:center">
          <span style="font-size:22px;font-weight:900;color:#ffffff;letter-spacing:-0.5px">
            A<span style="color:#99E000">Design</span> Financeiro
          </span>
        </td>
      </tr>
      <!-- Body -->
      <tr>
        <td style="padding:40px">
          <p style="font-size:18px;font-weight:700;color:#0f172a;margin:0 0 8px">
            Olá, {$nome}!
          </p>
          <p style="font-size:14px;color:#64748b;margin:0 0 24px;line-height:1.6">
            Recebemos uma solicitação para redefinir a senha da sua conta.
            Clique no botão abaixo para criar uma nova senha:
          </p>
          <table cellpadding="0" cellspacing="0" style="margin:0 0 24px">
            <tr>
              <td style="background:#99E000;border-radius:12px">
                <a href="{$link}" style="display:block;padding:14px 32px;font-size:14px;font-weight:700;color:#1a1a1a;text-decoration:none">
                  Redefinir minha senha →
                </a>
              </td>
            </tr>
          </table>
          <p style="font-size:12px;color:#94a3b8;margin:0 0 8px">
            ⏰ <strong>Este link expira em {$expiry_br}.</strong>
          </p>
          <p style="font-size:12px;color:#94a3b8;margin:0 0 8px">
            🔒 Se você não solicitou a recuperação de senha, ignore este e-mail com segurança. Sua senha não será alterada.
          </p>
          <p style="font-size:11px;color:#cbd5e1;margin:0;word-break:break-all">
            Link direto: {$link}
          </p>
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #f1f5f9">
          <p style="font-size:11px;color:#94a3b8;margin:0">
            ADesign Financeiro · clientes.allandesign.com.br
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

    // Tenta PHPMailer (instalado via composer) se disponível
    $phpmailer_path = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($phpmailer_path)) {
        require_once $phpmailer_path;
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = env('SMTP_HOST', 'smtp.dreamhost.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('SMTP_USER', '');
            $mail->Password   = env('SMTP_PASS', '');
            $mail->SMTPSecure = env('SMTP_PORT') == 465 ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) env('SMTP_PORT', 587);
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom(env('SMTP_FROM_EMAIL', 'financeiro@allandesign.com.br'), env('SMTP_FROM_NAME', 'ADesign Financeiro'));
            $mail->addAddress($email, $nome);
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body    = $corpo_html;
            $mail->AltBody = "Redefinir senha: {$link}\n\nExpira em: {$expiry_br}";
            $mail->send();
            return;
        } catch (\Exception $e) {
            // Fallback para mail() nativo
        }
    }

    // Fallback: mail() nativo (apenas dev/localhost)
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ADesign Financeiro <financeiro@allandesign.com.br>\r\n";
    $headers .= "Reply-To: financeiro@allandesign.com.br\r\n";
    @mail($email, $assunto, $corpo_html, $headers);
}
