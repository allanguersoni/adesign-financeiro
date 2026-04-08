<?php
/**
 * app/index.php — Dashboard Principal
 */
$page_title = 'Início';
$page_atual = 'dashboard';

require_once 'config/auth.php';

$header_btn = can('edit_clients') ? '<a href="clientes.php?novo=1"
    class="bg-gradient-to-br from-primary to-[#3a5600] text-white px-5 py-2 rounded-xl font-semibold flex items-center gap-2 shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all text-sm">
    <span class="material-symbols-outlined text-lg">add</span> Novo Cliente
</a>' : '';


require_once 'includes/header.php';

// ── Métricas ──────────────────────────────────
$total_clientes   = (int)   $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$receita_prevista = (float) $pdo->query("SELECT COALESCE(SUM(valor_anual),0) FROM clientes")->fetchColumn();
$inadimplentes    = (float) $pdo->query("SELECT COALESCE(SUM(valor_anual),0) FROM clientes WHERE status = 'pendente'")->fetchColumn();
$clientes_em_dia  = (int)   $pdo->query("SELECT COUNT(*) FROM clientes WHERE status = 'em dia'")->fetchColumn();
$vencendo         = (int)   $pdo->query("SELECT COUNT(*) FROM clientes WHERE status = 'vence em 15 dias'")->fetchColumn();

$taxa_adimplencia = $total_clientes > 0 ? round(($clientes_em_dia / $total_clientes) * 100) : 0;
$is_empty = $total_clientes === 0;

// ── Últimos clientes ──────────────────────────
$clientes = $pdo->query("SELECT * FROM clientes ORDER BY criado_em DESC LIMIT 8")->fetchAll();
?>

<?php if ($is_empty): ?>
<!-- ── ESTADO VAZIO — ONBOARDING ───────────────── -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 md:p-12 mb-8 flex flex-col items-center text-center">
    <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mb-5">
        <span class="material-symbols-outlined text-primary text-4xl" style="font-variation-settings:'FILL' 1">rocket_launch</span>
    </div>
    <h2 class="text-2xl font-extrabold text-slate-900 mb-2">Bem-vindo ao ADesign Financeiro!</h2>
    <p class="text-slate-500 text-sm mb-8 max-w-md leading-relaxed">
        Aqui você controla quem te deve, quem já pagou e quando cada cliente vence.
        Comece cadastrando seu primeiro cliente.
    </p>
    <div class="flex flex-col sm:flex-row gap-3 items-center">
        <?php if (can('edit_clients')): ?>
        <a href="clientes.php?novo=1" class="btn-primary text-sm px-8" style="height:48px">
            <span class="material-symbols-outlined text-[20px]">person_add</span>
            Cadastrar meu primeiro cliente
        </a>
        <?php endif; ?>
        <a href="configuracoes.php"
           class="flex items-center gap-2 px-6 py-3 text-slate-500 font-semibold text-sm hover:bg-slate-100 rounded-xl transition-colors">
            <span class="material-symbols-outlined text-[18px]">settings</span>
            Configurar o sistema
        </a>
    </div>
    <!-- Passos -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-10 w-full max-w-lg text-left">
        <?php foreach ([
            ['num' => '1', 'title' => 'Cadastre seus clientes',   'desc' => 'Nome, e-mail e quanto cada um te paga'],
            ['num' => '2', 'title' => 'Envie cobranças',          'desc' => 'O sistema avisa quando alguém está para vencer'],
            ['num' => '3', 'title' => 'Marque os pagamentos',     'desc' => 'Confirme quando receber e tudo fica no controle'],
        ] as $step): ?>
        <div class="flex gap-3 p-4 bg-slate-50 rounded-xl">
            <div class="w-7 h-7 rounded-full bg-primary text-white text-xs font-black flex items-center justify-center shrink-0"><?= $step['num'] ?></div>
            <div>
                <p class="font-bold text-sm text-slate-900"><?= $step['title'] ?></p>
                <p class="text-xs text-slate-500 mt-0.5"><?= $step['desc'] ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<!-- CARDS MÉTRICAS -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-8">
    <!-- Receita Anual -->
    <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow border border-slate-100">
        <div class="flex justify-between items-start mb-4">
            <div class="p-2 bg-primary/10 rounded-xl">
                <span class="material-symbols-outlined text-primary text-2xl" style="font-variation-settings:'FILL' 1">payments</span>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Anual</span>
        </div>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">O que você vai receber</p>
        <h3 class="text-2xl font-black text-slate-900">R$ <?= number_format($receita_prevista, 0, ',', '.') ?></h3>
        <p class="text-xs text-tertiary font-semibold mt-2">total previsto no ano</p>
    </div>

    <!-- Clientes em Dia -->
    <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow border border-slate-100">
        <div class="flex justify-between items-start mb-4">
            <div class="p-2 bg-tertiary/10 rounded-xl">
                <span class="material-symbols-outlined text-tertiary text-2xl" style="font-variation-settings:'FILL' 1">verified</span>
            </div>
            <span class="text-xs font-bold text-tertiary">+<?= $taxa_adimplencia ?>%</span>
        </div>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Pagando em dia</p>
        <h3 class="text-2xl font-black text-slate-900"><?= $clientes_em_dia ?></h3>
        <p class="text-xs text-slate-400 font-medium mt-2">de <?= $total_clientes ?> clientes</p>
    </div>

    <!-- Inadimplência -->
    <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow border border-slate-100">
        <div class="flex justify-between items-start mb-4">
            <div class="p-2 bg-error/10 rounded-xl">
                <span class="material-symbols-outlined text-error text-2xl" style="font-variation-settings:'FILL' 1">warning</span>
            </div>
        </div>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Em atraso</p>
        <h3 class="text-2xl font-black text-error">R$ <?= number_format($inadimplentes, 0, ',', '.') ?></h3>
        <p class="text-xs text-error/70 font-semibold mt-2">clientes com pagamento atrasado</p>
    </div>

    <!-- Vencendo em 15 dias -->
    <div class="bg-[#2e3132] rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex justify-between items-start mb-4">
            <div class="p-2 bg-[#99E000]/20 rounded-xl">
                <span class="material-symbols-outlined text-[#99E000] text-2xl" style="font-variation-settings:'FILL' 1">event_upcoming</span>
            </div>
        </div>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Vencem em breve</p>
        <h3 class="text-2xl font-black text-white"><?= $vencendo ?></h3>
        <p class="text-xs text-[#99E000] font-semibold mt-2">
            <?= $vencendo > 0 ? 'Enviar notificações' : 'Tudo em ordem ✓' ?>
        </p>
    </div>
</section>

<!-- PROGRESSO DE ADIMPLÊNCIA -->
<section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 mb-8">
    <div class="flex justify-between items-center mb-4">
        <h3 class="font-bold text-slate-900">Como está sua carteira</h3>
        <span class="text-2xl font-black text-primary"><?= $taxa_adimplencia ?>%</span>
    </div>
    <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden">
        <div class="bg-gradient-to-r from-primary to-primary-container h-full rounded-full transition-all duration-700"
             style="width: <?= $taxa_adimplencia ?>%"></div>
    </div>
    <div class="flex justify-between mt-2 text-[11px] font-bold text-slate-500">
        <span>Adimplentes: <?= $clientes_em_dia ?></span>
        <span>Total: <?= $total_clientes ?></span>
    </div>
</section>

<!-- TABELA — ÚLTIMOS CLIENTES -->
<section class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-7 py-5 flex justify-between items-center border-b border-slate-100">
        <h3 class="font-bold text-slate-900">Clientes Recentes</h3>
        <a href="clientes.php" class="text-primary text-sm font-bold hover:underline flex items-center gap-1">
            Ver todos <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50">
                    <th class="px-7 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">Cliente</th>
                    <th class="px-7 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">Domínio</th>
                    <th class="px-7 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-500 text-right">Valor</th>
                    <th class="px-7 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-7 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">Vencimento</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($clientes)): ?>
                <tr><td colspan="5" class="px-7 py-10 text-center text-slate-400 text-sm">Nenhum cliente cadastrado. <a href="clientes.php" class="text-primary font-bold">Adicionar agora →</a></td></tr>
                <?php else: ?>
                <?php foreach ($clientes as $c):
                    $ini = strtoupper(substr($c['nome'], 0, 1));
                    $badge = match($c['status']) {
                        'em dia'           => 'bg-green-100 text-green-800',
                        'pendente'         => 'bg-red-100 text-red-700',
                        'vence em 15 dias' => 'bg-amber-100 text-amber-700',
                        default            => 'bg-slate-100 text-slate-500',
                    };
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="px-7 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-black text-sm"><?= $ini ?></div>
                            <div>
                                <p class="font-semibold text-sm text-slate-900"><?= htmlspecialchars($c['nome']) ?></p>
                                <p class="text-xs text-slate-400"><?= htmlspecialchars($c['email'] ?? '') ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-7 py-4 text-sm text-slate-500"><?= htmlspecialchars($c['dominio'] ?? '—') ?></td>
                    <td class="px-7 py-4 text-sm font-bold text-slate-900 text-right">
                        R$ <?= $c['valor_anual'] ? number_format($c['valor_anual'], 2, ',', '.') : '—' ?>
                    </td>
                    <td class="px-7 py-4">
                        <span class="<?= $badge ?> px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider whitespace-nowrap">
                            <?= htmlspecialchars($c['status']) ?>
                        </span>
                    </td>
                    <td class="px-7 py-4 text-sm text-slate-500">
                        <?= $c['data_vencimento_base'] ? date('d/m/Y', strtotime($c['data_vencimento_base'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
