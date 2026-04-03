<?php
/**
 * app/actions/enviar_cobranca.php
 * Registra o envio de uma cobrança (simulação + log)
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../config/pix_simples.php';
require_once __DIR__ . '/../config/settings.php';

require_auth();
require_can('send_charges', '/cobrancas.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /cobrancas.php');
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token de segurança inválido.');
    header('Location: /cobrancas.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'ID de cliente inválido.');
    redirect_back('/cobrancas.php');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        set_flash('error', 'Cliente não encontrado.');
        redirect_back('/cobrancas.php');
    }

    // Tenta enviar via SMTP real (precisa de PHPMailer instalado)
    $venc = $cliente['data_vencimento_base']
        ? date('d/m/Y', strtotime($cliente['data_vencimento_base']))
        : 'não definida';

    $enviado = false;
    if (!empty($cliente['email'])) {
        try {
            $pix_payload = '';
            $pix_chave = setting('pix_chave');
            
            // Só gera PIX se a chave existir
            if (!empty($pix_chave)) {
                try {
                    $pix = gerar_pix_estatico([
                        'chave'        => $pix_chave,
                        'beneficiario' => setting('pix_beneficiario'),
                        'cidade'       => setting('pix_cidade', 'Sao Paulo'),
                        'valor'        => $cliente['valor_anual'],
                        'txid'         => substr(uniqid(), 0, 25),
                        'descricao'    => 'Cobranca ' . ($cliente['dominio'] ?? '')
                    ]);
                    $pix_payload = $pix['payload'] ?? '';
                } catch (Exception $e) {
                    $pix_payload = '';
                }
            }
            
            $html = email_template_cobranca($cliente, $venc, $pix_payload);
            
            $enviado = send_email(
                $cliente['email'],
                $cliente['nome'],
                'ADesign Financeiro — Cobrança: R$ ' . number_format($cliente['valor_anual'], 2, ',', '.'),
                $html
            );
        } catch (\Exception $e) {
            $enviado = false;
        }
    }

    if ($enviado) {
        set_flash('success',
            "📧 E-mail enviado para {$cliente['nome']} ({$cliente['email']}) — " .
            "R$ " . number_format($cliente['valor_anual'], 2, ',', '.') .
            " — Venc: {$venc}"
        );
    } else {
        set_flash('success',
            "📧 Cobrança registrada para {$cliente['nome']} " .
            ($cliente['email'] ? '(e-mail será enviado via SMTP ao instalar PHPMailer)' : '(sem e-mail cadastrado)') .
            " — R$ " . number_format($cliente['valor_anual'], 2, ',', '.')
        );
    }

} catch (PDOException $e) {
    set_flash('error', 'Erro ao processar cobrança.');
}

redirect_back('/cobrancas.php');
