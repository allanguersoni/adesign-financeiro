<?php
/**
 * app/configuracoes.php — Configurações do Sistema
 * ADesign Financeiro
 */
$page_title = 'Configurações';
$page_atual = 'configuracoes';

require_once 'includes/header.php';
// $user e setting() definidos pelo header.php (via settings.php)

// Preferências salvas na sessão
$cfg_vencimento = $_SESSION['config_notif_vencimento'] ?? 1;
$cfg_semanal    = $_SESSION['config_notif_semanal']    ?? 1;
$cfg_fraude     = $_SESSION['config_notif_fraude']     ?? 1;

// Dados do usuário logado
try {
    $stmt = $pdo->prepare("SELECT nome, email, role, criado_em, ultimo_acesso FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario_db = $stmt->fetch() ?: [];
} catch (PDOException $e) { $usuario_db = []; }

$nome_atual    = $usuario_db['nome']  ?? $user['nome'];
$email_atual   = $usuario_db['email'] ?? $user['email'];
$role_atual    = $usuario_db['role']  ?? $user['role'];
$ultimo_acesso = $usuario_db['ultimo_acesso']
    ? date('d/m/Y H:i', strtotime($usuario_db['ultimo_acesso']))
    : 'primeiro acesso';

// Lista de usuários (só admin carrega)
$todos_usuarios = [];
if (can('manage_users')) {
    try {
        $todos_usuarios = $pdo->query(
            "SELECT id, nome, email, role, ativo, ultimo_acesso, criado_em FROM usuarios ORDER BY id ASC"
        )->fetchAll();
    } catch (PDOException $e) { $todos_usuarios = []; }
}

// Detecta âncora de seção inicial via GET
$section_inicial = match($_GET['secao'] ?? '') {
    'usuarios'  => 'usuarios',
    'seguranca' => 'seguranca',
    'sistema'   => 'sistema',
    'alertas'   => 'alertas',
    default     => 'perfil',
};

// Detecta ambiente (Local vs Produção)
$smtp_host   = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?? '';
$is_producao = !in_array($smtp_host, ['localhost', 'mailhog', 'smtp.mailtrap.io', '']);
$ambiente    = $is_producao ? '🟢 Produção' : '🟡 Local / Sandbox';

// Versão MySQL
try {
    $mysql_version = $pdo->query('SELECT VERSION()')->fetchColumn();
} catch (PDOException) {
    $mysql_version = 'N/A';
}
?>

<!-- Banner de modo demo -->
<?php if ($role_atual === 'demo'): ?>
<div class="mb-6 flex items-center gap-3 px-5 py-3.5 bg-amber-50 border border-amber-200 rounded-2xl">
    <span class="material-symbols-outlined text-amber-500 icon-fill">visibility</span>
    <div>
        <p class="text-sm font-bold text-amber-800">Modo Demonstração</p>
        <p class="text-xs text-amber-600">Sua conta é somente leitura. Alterações não são permitidas.</p>
    </div>
</div>
<?php endif; ?>

<div class="mb-8">
    <h2 class="text-2xl font-extrabold text-slate-900">Configurações</h2>
    <p class="text-slate-500 text-sm mt-1">Gerencie preferências e dados da conta.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-12 gap-8">

    <!-- ── Sidebar de Seções ──────────────────────────────── -->
    <div class="md:col-span-3 space-y-0.5">
        <?php
        $sections = [
            ['id' => 'perfil',    'icon' => 'person',               'label' => 'Perfil do Usuário'],
            ['id' => 'seguranca', 'icon' => 'shield',                'label' => 'Segurança'],
            ['id' => 'alertas',   'icon' => 'notifications_active',  'label' => 'Alertas'],
            ['id' => 'sistema',   'icon' => 'info',                  'label' => 'Sistema'],
        ];
        if (can('manage_users')) {
            $sections[] = ['id' => 'usuarios', 'icon' => 'manage_accounts', 'label' => 'Usuários', 'badge' => count($todos_usuarios)];
        }
        foreach ($sections as $s):
        ?>
        <button onclick="showSection('<?= $s['id'] ?>')" id="btn-<?= $s['id'] ?>"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all text-sm text-left config-btn">
            <span class="material-symbols-outlined text-[20px]"><?= $s['icon'] ?></span>
            <span class="font-medium flex-1"><?= $s['label'] ?></span>
            <?php if (!empty($s['badge'])): ?>
            <span class="text-[10px] font-black bg-primary/15 text-primary px-1.5 py-0.5 rounded-full"><?= $s['badge'] ?></span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ── Conteúdo ──────────────────────────────────────── -->
    <div class="md:col-span-9 space-y-6">

        <!-- ══════════════ PERFIL ══════════════ -->
        <section id="section-perfil" class="config-section">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-7 py-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900">Perfil do Usuário</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Último acesso: <?= $ultimo_acesso ?></p>
                    </div>
                    <?= role_badge($role_atual) ?>
                </div>

                <form method="POST" action="actions/salvar_config.php" class="px-7 py-6 space-y-5">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="perfil">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-16 h-16 rounded-2xl flex items-center justify-center font-black text-2xl shrink-0"
                             style="background:linear-gradient(135deg,rgba(153,224,0,.2),rgba(69,104,0,.1));color:#456800">
                            <?= strtoupper(substr($nome_atual, 0, 2)) ?>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900"><?= htmlspecialchars($nome_atual) ?></p>
                            <p class="text-sm text-slate-400"><?= htmlspecialchars($email_atual) ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Nome Completo</label>
                            <input name="nome" type="text" value="<?= htmlspecialchars($nome_atual) ?>"
                                   <?= !can('edit_profile') ? 'disabled' : '' ?>
                                   class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all
                                          <?= !can('edit_profile') ? 'cursor-not-allowed opacity-60' : '' ?>"/>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">E-mail (somente leitura)</label>
                            <input type="email" value="<?= htmlspecialchars($email_atual) ?>" disabled
                                   class="w-full h-11 px-4 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-400 cursor-not-allowed"/>
                        </div>
                    </div>

                    <!-- Notificações -->
                    <div class="pt-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Notificações por E-mail</p>
                        <div class="space-y-2">
                            <?php
                            $notifs = [
                                ['name' => 'notif_vencimento', 'label' => 'Avisos de vencimento (15 dias)',  'val' => $cfg_vencimento],
                                ['name' => 'notif_semanal',    'label' => 'Resumo semanal (segundas, 08h)', 'val' => $cfg_semanal],
                                ['name' => 'notif_fraude',     'label' => 'Alertas de transações suspeitas','val' => $cfg_fraude],
                            ];
                            foreach ($notifs as $n):
                            ?>
                            <label class="toggle-switch flex items-center justify-between p-3.5 bg-slate-50 rounded-xl hover:bg-slate-100/80 transition-colors w-full
                                          <?= !can('edit_profile') ? 'opacity-60 pointer-events-none' : '' ?>">
                                <span class="text-sm font-medium text-slate-700"><?= $n['label'] ?></span>
                                <div>
                                    <input type="checkbox" name="<?= $n['name'] ?>" <?= $n['val'] ? 'checked' : '' ?> <?= !can('edit_profile') ? 'disabled' : '' ?>>
                                    <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (can('edit_profile')): ?>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="location.reload()"
                                class="px-5 py-2.5 text-slate-600 hover:bg-slate-100 rounded-xl text-sm font-semibold transition-colors">
                            Descartar
                        </button>
                        <button type="submit" class="btn-primary">
                            <span class="material-symbols-outlined text-[17px]">save</span>
                            Salvar Configurações
                        </button>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-amber-600 font-medium flex items-center gap-1.5 bg-amber-50 px-4 py-2.5 rounded-xl">
                        <span class="material-symbols-outlined text-[15px]">lock</span>
                        Conta de demonstração — alterações desabilitadas.
                    </p>
                    <?php endif; ?>
                </form>
            </div>
        </section>

        <!-- ══════════════ SEGURANÇA ══════════════ -->
        <section id="section-seguranca" class="config-section hidden">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-7 py-5 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">Segurança da Conta</h3>
                </div>
                <div class="px-7 py-6 space-y-3">
                    <?php
                    $sec_items = [
                        ['label' => 'Sessão Ativa',             'desc' => 'IP: ' . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '—'), 'status' => 'ativo',  'color' => 'green'],
                        ['label' => 'Proteção Brute-Force',      'desc' => 'Bloqueio após 5 tentativas em 15 min',                    'status' => 'ativo',  'color' => 'green'],
                        ['label' => 'CSRF Protection',           'desc' => 'Todos os formulários protegidos',                          'status' => 'ativo',  'color' => 'green'],
                        ['label' => 'Controle de Acesso (RBAC)', 'desc' => 'Admin / Editor / Demo',                                   'status' => 'ativo',  'color' => 'green'],
                    ];
                    foreach ($sec_items as $si):
                    ?>
                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl">
                        <div>
                            <p class="font-semibold text-sm text-slate-900"><?= $si['label'] ?></p>
                            <p class="text-xs text-slate-400"><?= $si['desc'] ?></p>
                        </div>
                        <span class="px-2 py-1 bg-green-100 text-green-700 text-[10px] font-bold rounded-full uppercase">ATIVO</span>
                    </div>
                    <?php endforeach; ?>
                    <div class="pt-1">
                        <a href="actions/auth_logout.php"
                           onclick="return confirm('Encerrar sessão agora?')"
                           class="flex items-center gap-2 px-5 py-2.5 bg-red-50 text-red-600 font-bold rounded-xl text-sm hover:bg-red-100 transition-colors w-full justify-center">
                            <span class="material-symbols-outlined text-[18px]">logout</span>
                            Encerrar Sessão
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════ SISTEMA ══════════════ -->
        <section id="section-sistema" class="config-section hidden">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-7 py-5 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">Informações do Sistema</h3>
                </div>
                <div class="px-7 py-6 space-y-0 divide-y divide-slate-50">
                    <?php
                    $infos = [
                        ['label' => 'Versão PHP',           'val' => phpversion()],
                        ['label' => 'Servidor',             'val' => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache'],
                        ['label' => 'Banco de Dados',       'val' => 'MySQL'],
                        ['label' => 'Versão da Aplicação',  'val' => 'ADesign Financeiro v1.0'],
                        ['label' => 'Domínio de Produção',  'val' => 'clientes.allandesign.com.br'],
                        ['label' => 'E-mail de Envio',      'val' => 'financeiro@allandesign.com.br'],
                        ['label' => 'Cron Notificador',     'val' => '/cron/notificador.php'],
                    ];
                    foreach ($infos as $i):
                    ?>
                    <div class="flex justify-between items-center py-3">
                        <span class="text-sm text-slate-500 font-medium"><?= $i['label'] ?></span>
                        <span class="text-sm font-bold text-slate-900"><?= htmlspecialchars($i['val']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (can('manage_users')): ?>
                <div class="px-7 pb-5">
                    <a href="cron/notificador.php"
                       class="flex items-center gap-2 px-5 py-2.5 bg-primary/10 text-primary font-bold rounded-xl text-sm hover:bg-primary/20 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">play_circle</span>
                        Executar Notificador de Cobranças
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ══════════════ ALERTAS ══════════════ -->
        <section id="section-alertas" class="config-section hidden">

            <?php if (!can('manage_users')): ?>
            <div class="flex items-center gap-3 px-5 py-3.5 mb-4 bg-amber-50 border border-amber-200 rounded-2xl">
                <span class="material-symbols-outlined text-amber-500 icon-fill">lock</span>
                <p class="text-sm text-amber-800">Somente administradores podem editar estas configurações.</p>
            </div>
            <?php endif; ?>

            <form method="POST" action="actions/salvar_config.php" id="form-alertas">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="alertas">

            <div class="space-y-5">

            <!-- ── CARD 1: Identidade ─────────────────────────── -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-7 py-5 border-b border-slate-100 flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary icon-fill text-[22px]">domain</span>
                    <div>
                        <h3 class="font-bold text-slate-900">Identidade do Sistema</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Usado nos e-mails enviados aos clientes</p>
                    </div>
                </div>
                <div class="px-7 py-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Nome da Empresa</label>
                            <input type="text" name="nome_empresa"
                                   value="<?= htmlspecialchars(setting('nome_empresa', 'ADesign Financeiro')) ?>"
                                   <?= !can('manage_users') ? 'disabled' : '' ?>
                                   class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all <?= !can('manage_users') ? 'opacity-60 cursor-not-allowed' : '' ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">URL do Sistema</label>
                            <input type="url" name="url_sistema"
                                   value="<?= htmlspecialchars(setting('url_sistema', 'https://clientes.allandesign.com.br')) ?>"
                                   <?= !can('manage_users') ? 'disabled' : '' ?>
                                   class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all <?= !can('manage_users') ? 'opacity-60 cursor-not-allowed' : '' ?>">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Rodapé dos E-mails de Cobrança</label>
                        <textarea name="email_rodape_cobranca" rows="3"
                                  placeholder="Ex: Dúvidas? Responda este e-mail ou ligue (xx) 9xxxx-xxxx"
                                  <?= !can('manage_users') ? 'disabled' : '' ?>
                                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all <?= !can('manage_users') ? 'opacity-60 cursor-not-allowed' : '' ?>"><?= htmlspecialchars(setting('email_rodape_cobranca', '')) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- ── CARD 2: Alertas de E-mail ──────────────────── -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-7 py-5 border-b border-slate-100 flex items-center gap-3">
                    <span class="material-symbols-outlined text-blue-500 icon-fill text-[22px]">mail</span>
                    <div>
                        <h3 class="font-bold text-slate-900">Alertas de E-mail</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Valores padrão globais — cada cliente pode ter configuração individual</p>
                    </div>
                </div>
                <div class="px-7 py-6 space-y-5">

                    <!-- Admin -->
                    <div class="p-5 bg-slate-50 rounded-xl border border-slate-200 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-sm text-slate-900">Enviar alertas ao Admin</p>
                                <p class="text-xs text-slate-400">Notifica o administrador antes do vencimento</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer <?= !can('manage_users') ? 'pointer-events-none opacity-60' : '' ?>">
                                <input type="checkbox" class="sr-only peer" name="alertas_email_admin" value="1"
                                       id="toggle-admin"
                                       onchange="toggleDias('admin-dias', this.checked)"
                                       <?= setting('alertas_email_admin', '1') === '1' ? 'checked' : '' ?>
                                       <?= !can('manage_users') ? 'disabled' : '' ?>>
                                <div class="w-11 h-6 bg-slate-300 peer-checked:bg-green-500 rounded-full peer transition-all duration-300
                                            after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
                                            after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                            </label>
                        </div>
                        <div id="admin-dias" class="<?= setting('alertas_email_admin', '1') === '1' ? '' : 'hidden' ?>">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Quantos dias antes do vencimento</label>
                            <div class="flex items-center gap-3">
                                <input type="number" name="alerta_admin_dias_padrao"
                                       value="<?= (int) setting('alerta_admin_dias_padrao', '15') ?>"
                                       min="1" max="60"
                                       <?= !can('manage_users') ? 'disabled' : '' ?>
                                       class="w-24 h-10 px-3 bg-white border border-slate-200 rounded-xl text-sm font-bold text-center focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all">
                                <span class="text-sm text-slate-500">dias antes do vencimento</span>
                            </div>
                        </div>
                    </div>

                    <!-- Cliente -->
                    <div class="p-5 bg-slate-50 rounded-xl border border-slate-200 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-sm text-slate-900">Enviar alertas ao Cliente</p>
                                <p class="text-xs text-slate-400">Envia e-mail de cobrança para o cliente antes do vencimento</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer <?= !can('manage_users') ? 'pointer-events-none opacity-60' : '' ?>">
                                <input type="checkbox" class="sr-only peer" name="alertas_email_cliente" value="1"
                                       id="toggle-cliente"
                                       onchange="toggleDias('cliente-dias', this.checked)"
                                       <?= setting('alertas_email_cliente', '1') === '1' ? 'checked' : '' ?>
                                       <?= !can('manage_users') ? 'disabled' : '' ?>>
                                <div class="w-11 h-6 bg-slate-300 peer-checked:bg-green-500 rounded-full peer transition-all duration-300
                                            after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
                                            after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                            </label>
                        </div>
                        <div id="cliente-dias" class="<?= setting('alertas_email_cliente', '1') === '1' ? '' : 'hidden' ?>">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Quantos dias antes do vencimento</label>
                            <div class="flex items-center gap-3">
                                <input type="number" name="alerta_cliente_dias_padrao"
                                       value="<?= (int) setting('alerta_cliente_dias_padrao', '7') ?>"
                                       min="1" max="60"
                                       <?= !can('manage_users') ? 'disabled' : '' ?>
                                       class="w-24 h-10 px-3 bg-white border border-slate-200 rounded-xl text-sm font-bold text-center focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all">
                                <span class="text-sm text-slate-500">dias antes do vencimento</span>
                            </div>
                        </div>
                    </div>

                    <!-- Nota explicativa -->
                    <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-xl border border-blue-100">
                        <span class="material-symbols-outlined text-blue-400 text-[18px] shrink-0 mt-0.5">info</span>
                        <p class="text-xs text-blue-700 leading-relaxed">
                            <strong>Atenção:</strong> estes são os valores <strong>padrão globais</strong>.
                            Cada cliente pode ter uma configuração individual de alertas definida no seu cadastro.
                            O cron respeita o campo <code class="bg-blue-100 px-1 rounded">alerta_admin_dias</code> de cada cliente.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── CARD 3: WhatsApp (Preview) ─────────────────── -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden opacity-70">
                <div class="px-7 py-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-green-500 icon-fill text-[22px]">chat</span>
                        <div>
                            <h3 class="font-bold text-slate-900">WhatsApp</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Notificações via WhatsApp Business API</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 bg-amber-100 text-amber-700 text-[10px] font-black uppercase tracking-wider rounded-full">Em breve</span>
                </div>
                <div class="px-7 py-6">
                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-200 pointer-events-none">
                        <div>
                            <p class="font-semibold text-sm text-slate-700">Ativar alertas por WhatsApp</p>
                            <p class="text-xs text-slate-400">Requer integração com WhatsApp Business API</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-not-allowed opacity-50">
                            <input type="checkbox" class="sr-only peer" disabled>
                            <div class="w-11 h-6 bg-slate-300 rounded-full after:content-[''] after:absolute after:top-0.5
                                        after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5"></div>
                        </label>
                    </div>
                    <p class="text-xs text-slate-400 mt-4 leading-relaxed">
                        🚧 Integração com WhatsApp Business API está prevista para uma versão futura.
                        Você será notificado quando esta funcionalidade estiver disponível.
                    </p>
                </div>
            </div>

            <!-- ── CARD 4: Notificador Manual ─────────────────── -->
            <?php if (can('manage_users')): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-7 py-5 border-b border-slate-100 flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary icon-fill text-[22px]">play_circle</span>
                    <div>
                        <h3 class="font-bold text-slate-900">Notificador Manual</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Execute o robô de cobranças agora, sem aguardar o cron automático</p>
                    </div>
                </div>
                <div class="px-7 py-6 space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div>
                            <?php $ultimo = setting('notificador_ultimo_disparo'); ?>
                            <p class="text-sm text-slate-600">
                                Último disparo:
                                <strong class="text-slate-900">
                                    <?= $ultimo ? date('d/m/Y H:i', strtotime($ultimo)) : 'Nunca executado' ?>
                                </strong>
                            </p>
                        </div>
                        <button type="button" id="btn-notificador"
                                onclick="executarNotificador()"
                                class="btn-primary shrink-0">
                            <span class="material-symbols-outlined text-[17px]" id="notif-icon">send</span>
                            <span id="notif-label">Executar Notificador</span>
                        </button>
                    </div>

                    <!-- Output ao vivo -->
                    <div id="notif-output-wrap" class="hidden">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Output do Notificador</p>
                            <button type="button" onclick="document.getElementById('notif-output-wrap').classList.add('hidden')"
                                    class="text-xs text-slate-400 hover:text-slate-600">fechar ×</button>
                        </div>
                        <pre id="notif-output"
                             class="bg-slate-900 text-green-400 text-xs font-mono p-5 rounded-xl overflow-auto max-h-72 leading-relaxed whitespace-pre-wrap"></pre>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── CARD 5: Informações do Sistema ─────────────── -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-7 py-5 border-b border-slate-100 flex items-center gap-3">
                    <span class="material-symbols-outlined text-slate-500 icon-fill text-[22px]">info</span>
                    <h3 class="font-bold text-slate-900">Informações do Sistema</h3>
                </div>
                <div class="px-7 py-6 divide-y divide-slate-50 space-y-0">
                    <?php
                    $sys_infos = [
                        ['label' => 'Versão da Aplicação', 'val' => 'ADesign Financeiro v1.0'],
                        ['label' => 'PHP',                 'val' => phpversion()],
                        ['label' => 'MySQL',               'val' => $mysql_version],
                        ['label' => 'Servidor',            'val' => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache/Nginx'],
                        ['label' => 'Ambiente',            'val' => $ambiente],
                        ['label' => 'SMTP Host',           'val' => $smtp_host ?: '(não configurado)'],
                        ['label' => 'Cron Notificador',    'val' => '/cron/notificador.php'],
                    ];
                    foreach ($sys_infos as $si): ?>
                    <div class="flex justify-between items-center py-3">
                        <span class="text-sm text-slate-500 font-medium"><?= $si['label'] ?></span>
                        <span class="text-sm font-bold text-slate-900"><?= htmlspecialchars($si['val']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Botão Salvar -->
            <?php if (can('manage_users')): ?>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="location.reload()"
                        class="px-5 py-2.5 text-slate-600 hover:bg-slate-100 rounded-xl text-sm font-semibold transition-colors">
                    Descartar
                </button>
                <button type="submit" form="form-alertas" class="btn-primary">
                    <span class="material-symbols-outlined text-[17px]">save</span>
                    Salvar Configurações
                </button>
            </div>
            <?php else: ?>
            <p class="text-xs text-amber-600 font-medium flex items-center gap-1.5 bg-amber-50 px-4 py-2.5 rounded-xl">
                <span class="material-symbols-outlined text-[15px]">lock</span>
                Somente administradores podem alterar estas configurações.
            </p>
            <?php endif; ?>

            </div><!-- /space-y-5 -->
            </form><!-- /form-alertas -->

        </section><!-- /section-alertas -->

        <!-- ══════════════ USUÁRIOS (só admin) ══════════════ -->
        <?php if (can('manage_users')): ?>
        <section id="section-usuarios" class="config-section hidden" id="usuarios">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-7 py-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900">Gerenciar Usuários</h3>
                        <p class="text-xs text-slate-400 mt-0.5"><?= count($todos_usuarios) ?> usuário(s) no sistema</p>
                    </div>
                    <button onclick="document.getElementById('modal-novo-usuario').classList.remove('hidden')"
                            class="btn-primary text-[13px] h-9 px-4">
                        <span class="material-symbols-outlined text-[16px]">person_add</span> Novo Usuário
                    </button>
                </div>

                <!-- Tabela de usuários -->
                <div class="divide-y divide-slate-50">
                    <?php foreach ($todos_usuarios as $u): ?>
                    <div class="flex items-center gap-4 px-7 py-4 hover:bg-slate-50/80 transition-colors">
                        <!-- Avatar -->
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-sm shrink-0
                                    <?= $u['ativo'] ? 'bg-primary/10 text-primary' : 'bg-slate-100 text-slate-400' ?>"
                             style="<?= $u['ativo'] ? 'background:linear-gradient(135deg,rgba(153,224,0,.18),rgba(69,104,0,.1))' : '' ?>">
                            <?= strtoupper(substr($u['nome'], 0, 2)) ?>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-semibold text-sm text-slate-900"><?= htmlspecialchars($u['nome']) ?></p>
                                <?= role_badge($u['role']) ?>
                                <?php if (!$u['ativo']): ?>
                                <span class="px-1.5 py-0.5 bg-slate-100 text-slate-400 text-[10px] font-bold rounded-full uppercase">Inativo</span>
                                <?php endif; ?>
                                <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                <span class="text-[10px] text-slate-400">(você)</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-slate-400"><?= htmlspecialchars($u['email']) ?></p>
                            <?php if ($u['ultimo_acesso']): ?>
                            <p class="text-[10px] text-slate-300 mt-0.5">
                                Último acesso: <?= date('d/m/Y H:i', strtotime($u['ultimo_acesso'])) ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- Ações -->
                        <?php if ($u['id'] !== 1 && $u['id'] != $_SESSION['user_id']): ?>
                        <div class="flex items-center gap-1 shrink-0">
                            <!-- Toggle ativo -->
                            <form method="POST" action="actions/toggle_usuario.php" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn-icon <?= $u['ativo'] ? 'text-amber-500 hover:bg-amber-50' : 'text-green-600 hover:bg-green-50' ?>"
                                        title="<?= $u['ativo'] ? 'Desativar usuário' : 'Ativar usuário' ?>"
                                        onclick="return confirm('<?= $u['ativo'] ? 'Desativar' : 'Ativar' ?> este usuário?')">
                                    <span class="material-symbols-outlined text-[17px]"><?= $u['ativo'] ? 'person_off' : 'person_check' ?></span>
                                </button>
                            </form>
                            <!-- Editar -->
                            <button onclick="abrirEdicaoUsuario(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nome'])) ?>', '<?= htmlspecialchars(addslashes($u['email'])) ?>', '<?= $u['role'] ?>')"
                                    class="btn-icon btn-icon-edit" title="Editar usuário">
                                <span class="material-symbols-outlined text-[17px]">edit</span>
                            </button>
                            <!-- Excluir -->
                            <form method="POST" action="actions/excluir_usuario.php" class="inline"
                                  onsubmit="return confirm('Excluir \'<?= htmlspecialchars(addslashes($u['nome'])) ?>\'? Esta ação não pode ser desfeita.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn-icon btn-icon-del" title="Excluir usuário">
                                    <span class="material-symbols-outlined text-[17px]">delete</span>
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <div class="shrink-0 text-xs text-slate-300 italic pr-1">protegido</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Legenda de roles -->
                <div class="px-7 py-4 bg-slate-50 border-t border-slate-100">
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Níveis de Acesso</p>
                    <div class="grid grid-cols-3 gap-3 text-[11px] text-slate-500">
                        <div class="flex flex-col gap-1">
                            <?= role_badge('admin') ?>
                            <span>Acesso total ao sistema</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <?= role_badge('editor') ?>
                            <span>Edita clientes e cobranças</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <?= role_badge('demo') ?>
                            <span>Somente visualização</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ MODAL: NOVO USUÁRIO ══ -->
        <div id="modal-novo-usuario"
             class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-4"
             onclick="if(event.target===this)this.classList.add('hidden')">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
                <div class="flex items-center justify-between px-7 py-5 border-b border-slate-100">
                    <div>
                        <h3 class="font-bold text-slate-900" id="modal-usuario-titulo">Novo Usuário</h3>
                        <p class="text-xs text-slate-400">Preencha os dados e defina o nível de acesso</p>
                    </div>
                    <button onclick="fecharModalUsuario()" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form method="POST" action="actions/salvar_usuario.php" class="px-7 py-6 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="modal-usuario-id" value="0"/>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Nome Completo *</label>
                        <input name="nome" id="modal-usuario-nome" type="text" required placeholder="Ex: João Silva"
                               class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">E-mail *</label>
                        <input name="email" id="modal-usuario-email" type="email" required placeholder="usuario@empresa.com"
                               class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Senha <span id="senha-hint" class="normal-case text-slate-400 font-normal">(mínimo 8 caracteres)</span></label>
                        <div class="relative">
                            <input name="senha" id="modal-usuario-senha" type="password" placeholder="••••••••"
                                   class="w-full h-11 px-4 pr-11 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                            <button type="button" onclick="toggleSenhaModal()"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                <span class="material-symbols-outlined text-[19px]" id="senha-eye-modal">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Nível de Acesso *</label>
                        <select name="role" id="modal-usuario-role"
                                class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all appearance-none cursor-pointer">
                            <option value="editor">✏️ Editor — Edita clientes e cobranças</option>
                            <option value="demo">👁 Demo — Somente visualização</option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1.5">
                            <strong>Editor:</strong> pode criar/editar/excluir clientes e enviar cobranças.<br>
                            <strong>Demo:</strong> somente leitura — ideal para apresentações.
                        </p>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick="fecharModalUsuario()"
                                class="px-5 py-2.5 text-slate-600 font-semibold hover:bg-slate-100 rounded-xl text-sm transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-primary">
                            <span class="material-symbols-outlined text-[16px]">person_add</span>
                            <span id="modal-usuario-btn-label">Criar Usuário</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function showSection(id) {
    document.querySelectorAll('.config-section').forEach(s => s.classList.add('hidden'));
    document.querySelectorAll('.config-btn').forEach(b => {
        b.classList.remove('text-primary', 'bg-primary/10', 'font-bold');
        b.classList.add('text-slate-600');
    });
    document.getElementById('section-' + id)?.classList.remove('hidden');
    const btn = document.getElementById('btn-' + id);
    if (btn) {
        btn.classList.add('text-primary', 'bg-primary/10', 'font-bold');
        btn.classList.remove('text-slate-600');
    }
}

// Detecta âncora na URL para abrir a seção correta
const anchor = window.location.hash.replace('#', '');
const validSections = ['perfil', 'seguranca', 'alertas', 'sistema', 'usuarios'];
showSection(validSections.includes(anchor) ? anchor : '<?= $section_inicial ?>');

// ── Toggle de dias (mostrar/ocultar campo de dias) ─────────
function toggleDias(divId, checked) {
    const div = document.getElementById(divId);
    if (!div) return;
    if (checked) {
        div.classList.remove('hidden');
    } else {
        div.classList.add('hidden');
    }
}

// ── Notificador AJAX ───────────────────────────────────────
async function executarNotificador() {
    const btn    = document.getElementById('btn-notificador');
    const icon   = document.getElementById('notif-icon');
    const label  = document.getElementById('notif-label');
    const wrap   = document.getElementById('notif-output-wrap');
    const output = document.getElementById('notif-output');

    if (!btn) return;

    // Estado: carregando
    btn.disabled  = true;
    icon.textContent = 'hourglass_empty';
    label.textContent = 'Executando...';
    wrap.classList.remove('hidden');
    output.textContent = '⏳ Aguardando resposta do servidor...\n';

    try {
        const resp = await fetch('/cron/notificador.php', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const text = await resp.text();
        output.textContent = text || '(sem output)';

        // Salva timestamp da última execução via action auxiliar
        await fetch('/actions/salvar_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                csrf_token:  document.querySelector('meta[name="csrf-token"]').content,
                action_type: 'alertas',
                nome_empresa: document.querySelector('[name="nome_empresa"]')?.value || '',
                url_sistema:  document.querySelector('[name="url_sistema"]')?.value || '',
                email_rodape_cobranca: document.querySelector('[name="email_rodape_cobranca"]')?.value || '',
                alerta_admin_dias_padrao:   document.querySelector('[name="alerta_admin_dias_padrao"]')?.value || '15',
                alerta_cliente_dias_padrao: document.querySelector('[name="alerta_cliente_dias_padrao"]')?.value || '7',
                alertas_email_admin:   document.querySelector('[name="alertas_email_admin"]')?.checked   ? '1' : '',
                alertas_email_cliente: document.querySelector('[name="alertas_email_cliente"]')?.checked ? '1' : '',
                _notificador_timestamp: new Date().toISOString()
            })
        });

    } catch (err) {
        output.textContent = '❌ Erro ao conectar: ' + err.message;
    } finally {
        btn.disabled = false;
        icon.textContent = 'check_circle';
        label.textContent = 'Executar Notificador';
    }
}

function fecharModalUsuario() {
    const m = document.getElementById('modal-novo-usuario');
    m.classList.add('hidden');
    m.querySelector('form').reset();
    document.getElementById('modal-usuario-id').value = '0';
    document.getElementById('modal-usuario-titulo').textContent = 'Novo Usuário';
    document.getElementById('modal-usuario-btn-label').textContent = 'Criar Usuário';
    document.getElementById('senha-hint').textContent = '(mínimo 8 caracteres)';
}

function abrirEdicaoUsuario(id, nome, email, role) {
    document.getElementById('modal-usuario-id').value    = id;
    document.getElementById('modal-usuario-nome').value  = nome;
    document.getElementById('modal-usuario-email').value = email;
    document.getElementById('modal-usuario-role').value  = role;
    document.getElementById('modal-usuario-titulo').textContent    = 'Editar Usuário';
    document.getElementById('modal-usuario-btn-label').textContent = 'Salvar Alterações';
    document.getElementById('senha-hint').textContent    = '(deixe em branco para não alterar)';
    document.getElementById('modal-novo-usuario').classList.remove('hidden');
}

function toggleSenhaModal() {
    const inp = document.getElementById('modal-usuario-senha');
    const eye = document.getElementById('senha-eye-modal');
    if (inp.type === 'password') { inp.type = 'text'; eye.textContent = 'visibility_off'; }
    else { inp.type = 'password'; eye.textContent = 'visibility'; }
}
</script>

<?php require_once 'includes/footer.php'; ?>
