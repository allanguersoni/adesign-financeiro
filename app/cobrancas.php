<?php
/**
 * app/cobrancas.php — Fluxo de Cobranças
 */
$page_title = 'Cobranças';
$page_atual = 'cobrancas';

require_once 'config/auth.php';

$header_btn = can('send_charges') ? '<a href="cron/notificador.php"
    class="bg-gradient-to-br from-primary to-[#3a5600] text-white px-5 py-2 rounded-xl font-semibold flex items-center gap-2 shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all text-sm">
    <span class="material-symbols-outlined text-lg">send</span> Executar Notificador
</a>' : '';

require_once 'includes/header.php';

// ── Métricas ──────────────────────────────────────────
$total_previsto = (float) $pdo->query("SELECT COALESCE(SUM(valor_anual),0) FROM clientes")->fetchColumn();
$total_em_dia   = (float) $pdo->query("SELECT COALESCE(SUM(valor_anual),0) FROM clientes WHERE status='em dia'")->fetchColumn();
$total_pendente = (float) $pdo->query("SELECT COALESCE(SUM(valor_anual),0) FROM clientes WHERE status='pendente'")->fetchColumn();
$vencendo_15    = (float) $pdo->query("SELECT COALESCE(SUM(valor_anual),0) FROM clientes WHERE status='vence em 15 dias'")->fetchColumn();
$pct            = $total_previsto > 0 ? round(($total_em_dia / $total_previsto) * 100) : 0;

// ── Filtro de status ──────────────────────────────────
$filter = $_GET['status'] ?? '';
$where  = '';
$params = [];
if (in_array($filter, ['em dia', 'pendente', 'vence em 15 dias'])) {
    $where  = 'WHERE c.status = ?';
    $params = [$filter];
}
$stmt = $pdo->prepare("
    SELECT c.*, 
        (SELECT id FROM pagamentos WHERE cliente_id = c.id AND status IN ('pendente', 'cancelado') ORDER BY id DESC LIMIT 1) AS pagamento_id,
        (SELECT pix_token FROM pagamentos WHERE cliente_id = c.id AND status IN ('pendente', 'cancelado') ORDER BY id DESC LIMIT 1) AS pix_token
    FROM clientes c 
    {$where} 
    ORDER BY c.data_vencimento_base ASC
");
$stmt->execute($params);
$faturas = $stmt->fetchAll();
?>

<!-- CARDS FINANCEIROS -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-8">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Total a Receber</p>
        <h3 class="text-xl font-black text-slate-900">R$ <?= number_format($total_previsto, 2, ',', '.') ?></h3>
        <div class="mt-3 w-full bg-slate-100 h-1.5 rounded-full">
            <div class="bg-primary h-1.5 rounded-full" style="width: <?= $pct ?>%"></div>
        </div>
        <p class="text-[10px] text-slate-400 mt-1"><?= $pct ?>% recebido</p>
    </div>

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Clientes em Dia</p>
        <h3 class="text-xl font-black text-tertiary">R$ <?= number_format($total_em_dia, 2, ',', '.') ?></h3>
        <p class="text-xs text-tertiary/70 font-semibold mt-2 flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">check_circle</span> Adimplentes
        </p>
    </div>

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Inadimplência</p>
        <h3 class="text-xl font-black text-error">R$ <?= number_format($total_pendente, 2, ',', '.') ?></h3>
        <p class="text-xs text-error/70 font-semibold mt-2 flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">warning</span> Ação necessária
        </p>
    </div>

    <div class="bg-[#2e3132] rounded-2xl p-5 shadow-sm">
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Vence em 15 dias</p>
        <h3 class="text-xl font-black text-white">R$ <?= number_format($vencendo_15, 2, ',', '.') ?></h3>
        <p class="text-xs text-[#99E000] font-semibold mt-2 flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">schedule</span> Enviar avisos
        </p>
    </div>
</section>

<!-- FILTROS RÁPIDOS -->
<div class="flex flex-wrap gap-2 mb-5">
    <?php
    $filtros = [
        ''                 => ['label' => 'Todos', 'class' => 'bg-slate-100 text-slate-700'],
        'em dia'           => ['label' => 'Em Dia', 'class' => 'bg-green-100 text-green-800'],
        'pendente'         => ['label' => 'Pendentes', 'class' => 'bg-red-100 text-red-700'],
        'vence em 15 dias' => ['label' => 'Vencendo', 'class' => 'bg-amber-100 text-amber-700'],
    ];
    foreach ($filtros as $key => $f):
        $active = $filter === $key ? 'ring-2 ring-primary font-black' : 'hover:ring-1 ring-slate-200';
    ?>
    <a href="?status=<?= urlencode($key) ?>"
       class="px-4 py-1.5 rounded-full text-xs font-bold transition-all <?= $f['class'] ?> <?= $active ?>">
        <?= $f['label'] ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- TABELA DE FATURAS -->
<section class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-8">
    <div class="px-7 py-5 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-900">Faturas / Cobranças</h3>
        <span class="text-xs text-slate-400"><?= count($faturas) ?> registro(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50">
                    <th class="px-7 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">Cliente</th>
                    <th class="px-7 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-500 text-right">Valor</th>
                    <th class="px-7 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">Vencimento</th>
                    <th class="px-7 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-7 py-3.5 text-[11px] font-bold uppercase tracking-wider text-slate-500 text-center">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($faturas)): ?>
                <tr><td colspan="5" class="py-12 text-center text-slate-400">Nenhuma cobrança encontrada.</td></tr>
                <?php else: ?>
                <?php foreach ($faturas as $f):
                    $ini   = strtoupper(substr($f['nome'], 0, 1));
                    $badge = match($f['status']) {
                        'em dia'           => 'bg-green-100 text-green-800',
                        'pendente'         => 'bg-red-100 text-red-700',
                        'vence em 15 dias' => 'bg-amber-100 text-amber-700',
                        default            => 'bg-slate-100 text-slate-500',
                    };
                    $venc_ts  = $f['data_vencimento_base'] ? strtotime($f['data_vencimento_base']) : null;
                    $vencido  = $venc_ts && $venc_ts < time();
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="px-7 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm shrink-0"><?= $ini ?></div>
                            <div>
                                <p class="font-semibold text-sm text-slate-900"><?= htmlspecialchars($f['nome']) ?></p>
                                <p class="text-[10px] text-slate-400"><?= htmlspecialchars($f['dominio'] ?? '') ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-7 py-4 text-right font-bold text-slate-900 text-sm">
                        R$ <?= $f['valor_anual'] ? number_format($f['valor_anual'], 2, ',', '.') : '—' ?>
                    </td>
                    <td class="px-7 py-4 text-sm <?= $vencido ? 'text-error font-bold' : 'text-slate-600' ?>">
                        <?= $venc_ts ? date('d/m/Y', $venc_ts) : '—' ?>
                        <?= $vencido ? '<span class="text-[10px] font-black ml-1">VENCIDO</span>' : '' ?>
                    </td>
                    <td class="px-7 py-4">
                        <span class="<?= $badge ?> px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider whitespace-nowrap">
                            <?= htmlspecialchars($f['status']) ?>
                        </span>
                    </td>
                    <td class="px-7 py-4">
                        <div class="flex items-center justify-center gap-1.5">
                        <?php if ($f['status'] === 'pendente' || $f['status'] === 'vence em 15 dias'): ?>
                            <?php if (can('send_charges')): ?>
                                <!-- Botão "Enviar Cobrança" -->
                                <form method="POST" action="actions/enviar_cobranca.php" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                    <button type="submit"
                                            class="flex items-center gap-1 px-3 py-1.5 bg-error/10 text-error text-[11px] font-bold rounded-lg hover:bg-error/20 transition-colors whitespace-nowrap"
                                            title="Enviar cobrança">
                                        <span class="material-symbols-outlined text-[15px]">mail</span>
                                        Cobrar
                                    </button>
                                </form>
                                
                                <!-- Botão Gerar PIX -->
                                <?php if (!empty($f['pagamento_id'])): ?>
                                    <?php if (!empty($f['pix_token'])): ?>
                                        <a href="pix.php?token=<?= $f['pix_token'] ?>" target="_blank"
                                           class="flex items-center gap-1 px-3 py-1.5 bg-[#0ea5e9]/10 text-[#0ea5e9] text-[11px] font-bold rounded-lg hover:bg-[#0ea5e9]/20 transition-colors whitespace-nowrap"
                                            title="Ver PIX gerado">
                                            <span class="material-symbols-outlined text-[15px]">qr_code</span>
                                            <span>Ver PIX</span>
                                        </a>
                                    <?php else: ?>
                                        <form method="POST" action="actions/gerar_link_pix.php" target="_blank" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="pagamento_id" value="<?= $f['pagamento_id'] ?>">
                                            <button type="submit"
                                                    class="flex items-center gap-1 px-3 py-1.5 bg-[#0ea5e9]/10 text-[#0ea5e9] text-[11px] font-bold rounded-lg hover:bg-[#0ea5e9]/20 transition-colors whitespace-nowrap"
                                                    title="Gerar código PIX">
                                                <span class="material-symbols-outlined text-[15px]">pix</span>
                                                <span>Gerar PIX</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="POST" action="actions/gerar_link_pix.php" target="_blank" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="cliente_id" value="<?= $f['id'] ?>">
                                        <button type="submit"
                                                class="flex items-center gap-1 px-3 py-1.5 bg-[#0ea5e9]/10 text-[#0ea5e9] text-[11px] font-bold rounded-lg hover:bg-[#0ea5e9]/20 transition-colors whitespace-nowrap"
                                                title="Gerar código PIX (Criará cobrança automática)">
                                            <span class="material-symbols-outlined text-[15px]">pix</span>
                                            <span>Gerar PIX</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <span class="material-symbols-outlined text-[17px] text-slate-300 pointer-events-none" title="Apenas visualização">lock</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
