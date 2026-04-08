<?php
/**
 * app/actions/salvar_config.php
 * ==============================
 * Processa o formulário de configurações.
 *
 * Blocos independentes, controlados por `action_type`:
 *   - 'perfil'   → atualiza nome do usuário (edit_profile)
 *   - 'alertas'  → salva tabela configuracoes (manage_users/admin)
 *                   inclui: Identidade, Alertas E-mail, PIX, WhatsApp
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/settings.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /configuracoes.php');
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token de segurança inválido. Tente novamente.');
    redirect_back('/configuracoes.php');
}

$action_type = $_POST['action_type'] ?? 'perfil';

// ══════════════════════════════════════════════════
// BLOCO A — Perfil do Usuário (admin + editor)
// ══════════════════════════════════════════════════
if ($action_type === 'perfil') {
    require_can('edit_profile', '/configuracoes.php');

    // Salva notificações pessoais na sessão
    $_SESSION['config_notif_vencimento'] = isset($_POST['notif_vencimento']) ? 1 : 0;
    $_SESSION['config_notif_semanal']    = isset($_POST['notif_semanal'])    ? 1 : 0;
    $_SESSION['config_notif_fraude']     = isset($_POST['notif_fraude'])     ? 1 : 0;

    // Atualiza nome do usuário
    $novo_nome = sanitize_string($_POST['nome'] ?? '');
    if (!empty($novo_nome) && strlen($novo_nome) >= 2) {
        try {
            $pdo->prepare("UPDATE usuarios SET nome = ? WHERE id = ?")
                ->execute([$novo_nome, $_SESSION['user_id']]);
            $_SESSION['user_nome'] = $novo_nome;
        } catch (PDOException) {
            // Falha silenciosa — nome não atualizado
        }
    }

    set_flash('success', '✅ Perfil atualizado com sucesso!');
    redirect_back('/configuracoes.php');
}

// ══════════════════════════════════════════════════
// BLOCO B — Configurações do Sistema (só admin)
// ══════════════════════════════════════════════════
if ($action_type === 'alertas') {
    require_can('manage_users', '/configuracoes.php');

    // ── Identidade / Empresa ────────────────────────
    $nome_empresa = sanitize_string($_POST['nome_empresa'] ?? '');
    $url_sistema  = sanitize_string($_POST['url_sistema']  ?? '');
    $email_rodape = sanitize_string($_POST['email_rodape_cobranca'] ?? '');

    if (!empty($nome_empresa)) {
        save_setting('nome_empresa', $nome_empresa);
    }
    if (!empty($url_sistema)) {
        save_setting('url_sistema', $url_sistema);
    }
    save_setting('email_rodape_cobranca', $email_rodape);

    // ── Alertas Admin ──────────────────────────────
    $alertas_admin = isset($_POST['alertas_email_admin']) ? '1' : '0';
    $admin_dias    = max(1, min(60, (int) ($_POST['alerta_admin_dias_padrao'] ?? 15)));
    save_setting('alertas_email_admin',      $alertas_admin);
    save_setting('alerta_admin_dias_padrao', (string) $admin_dias);

    // ── Alertas Cliente ────────────────────────────
    $alertas_cliente = isset($_POST['alertas_email_cliente']) ? '1' : '0';
    $cliente_dias    = max(1, min(60, (int) ($_POST['alerta_cliente_dias_padrao'] ?? 7)));
    save_setting('alertas_email_cliente',      $alertas_cliente);
    save_setting('alerta_cliente_dias_padrao', (string) $cliente_dias);

    // ── PIX ────────────────────────────────────────
    $pix_modo = in_array($_POST['pix_modo'] ?? '', ['simples', 'avancado'])
                ? $_POST['pix_modo'] : 'simples';
    save_setting('pix_modo',         $pix_modo);
    save_setting('pix_chave',        sanitize_string($_POST['pix_chave'] ?? ''));
    save_setting('pix_beneficiario', sanitize_string($_POST['pix_beneficiario'] ?? ''));
    save_setting('pix_cidade',       sanitize_string($_POST['pix_cidade'] ?? ''));
    save_setting('efi_client_id',    sanitize_string($_POST['efi_client_id'] ?? ''));
    save_setting('efi_client_secret', sanitize_string($_POST['efi_client_secret'] ?? ''));
    save_setting('efi_sandbox',      isset($_POST['efi_sandbox']) ? '1' : '0');

    // ── WhatsApp ───────────────────────────────────
    save_setting('whatsapp_ativo',          isset($_POST['whatsapp_ativo']) ? '1' : '0');
    save_setting('whatsapp_instance_id',    sanitize_string($_POST['whatsapp_instance_id'] ?? ''));
    save_setting('whatsapp_token',          sanitize_string($_POST['whatsapp_token'] ?? ''));
    save_setting('whatsapp_numero_proprio', sanitize_string($_POST['whatsapp_numero_proprio'] ?? ''));

    $wpp_dias = max(1, min(30, (int) ($_POST['whatsapp_dias_antes'] ?? 7)));
    save_setting('whatsapp_dias_antes', (string) $wpp_dias);

    // Templates: não sanitizar para preservar emojis e quebras de linha
    $tpl_venc  = $_POST['whatsapp_template_vencimento'] ?? '';
    $tpl_atr   = $_POST['whatsapp_template_atraso']     ?? '';
    save_setting('whatsapp_template_vencimento', htmlspecialchars_decode(strip_tags($tpl_venc)));
    save_setting('whatsapp_template_atraso',     htmlspecialchars_decode(strip_tags($tpl_atr)));

    // ── Timestamp do notificador (enviado pelo AJAX do Card Sistema) ──
    if (!empty($_POST['_notificador_timestamp'])) {
        save_setting('notificador_ultimo_disparo', date('Y-m-d H:i:s'));
    }

    // Invalida cache para refletir novos valores imediatamente
    flush_settings_cache();

    $section = sanitize_string($_POST['_section'] ?? 'alertas');
    set_flash('success', '✅ Configurações salvas com sucesso!');
    header('Location: /configuracoes.php#' . $section);
    exit;
}

// Fallback
redirect_back('/configuracoes.php');
