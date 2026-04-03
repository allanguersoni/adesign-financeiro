<?php
/**
 * app/pagamentos.php
 * Tela de Controle de Pagamentos — Issue 003
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/conexao.php';

require_auth();
require_can('view_all', '/index.php');

$page_title = 'Controle de Pagamentos';
$page_atual = 'pagamentos';

// ── Mês consultado (via GET ou atual) ──────────────────────
$hoje_obj = new DateTimeImmutable('today');
$mes_raw  = $_GET['mes'] ?? $hoje_obj->format('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mes_raw)) {
    $mes_raw = $hoje_obj->format('Y-m');
}

[$ano_str, $mes_str] = explode('-', $mes_raw);
$ano = (int) $ano_str;
$mes = (int) $mes_str;

if ($mes < 1 || $mes > 12 || $ano < 2020 || $ano > 2100) {
    $ano = (int) $hoje_obj->format('Y');
    $mes = (int) $hoje_obj->format('m');
    $mes_raw = sprintf('%04d-%02d', $ano, $mes);
}

$primeiro_dia = sprintf('%04d-%02d-01', $ano, $mes);
$mes_anterior = (new DateTimeImmutable($primeiro_dia))->modify('-1 month')->format('Y-m');
$mes_proximo  = (new DateTimeImmutable($primeiro_dia))->modify('+1 month')->format('Y-m');

$meses_pt = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
             'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$titulo_mes = $meses_pt[$mes] . ' ' . $ano;

// ── Query UNION: mensais + anuais do mês ──────────────────
$stmt = $pdo->prepare("
    SELECT
        c.id            AS cliente_id,
        c.nome,
        c.dominio,
        COALESCE(p.valor, c.valor_anual)  AS valor,
        c.tipo_recorrencia,
        c.dia_vencimento,
        c.data_vencimento_base,
        'mensal'        AS tipo_exibicao,
        DATE(CONCAT(:ano, '-', LPAD(:mes, 2, '0'), '-',
             LPAD(LEAST(c.dia_vencimento, DAY(LAST_DAY(:primeiro_dia))), 2, '0')
        )) AS data_venc_competencia,
        p.id            AS pagamento_id,
        p.status        AS pagamento_status,
        p.pago_em,
        p.metodo,
        p.observacao,
        p.valor         AS valor_pago
    FROM clientes c
    LEFT JOIN pagamentos p
        ON p.cliente_id = c.id AND p.competencia = :primeiro_dia2
    WHERE c.tipo_recorrencia = 'mensal'

    UNION ALL

    SELECT
        c.id            AS cliente_id,
        c.nome,
        c.dominio,
        COALESCE(p.valor, c.valor_anual)  AS valor,
        c.tipo_recorrencia,
        c.dia_vencimento,
        c.data_vencimento_base,
        'anual'         AS tipo_exibicao,
        c.data_vencimento_base AS data_venc_competencia,
        p.id            AS pagamento_id,
        p.status        AS pagamento_status,
        p.pago_em,
        p.metodo,
        p.observacao,
        p.valor         AS valor_pago
    FROM clientes c
    LEFT JOIN pagamentos p
        ON p.cliente_id = c.id AND p.competencia = :primeiro_dia3
    WHERE c.tipo_recorrencia = 'anual'
      AND MONTH(c.data_vencimento_base) = :mes2
      -- YEAR removido intencionalmente: clientes anuais devem aparecer
      -- todo ano no mês do seu vencimento, independente do ano base.

    ORDER BY nome ASC
");

$stmt->execute([
    ':ano'          => $ano_str,
    ':mes'          => $mes_str,
    ':primeiro_dia' => $primeiro_dia,
    ':primeiro_dia2'=> $primeiro_dia,
    ':primeiro_dia3'=> $primeiro_dia,
    ':mes2'         => $mes,
    // :ano2 removido — filtro por ano eliminado da query de anuais
]);
$rows = $stmt->fetchAll();

// ── Status visual por linha ────────────────────────────────
foreach ($rows as &$row) {
    $venc = $row['data_venc_competencia']
        ? new DateTimeImmutable($row['data_venc_competencia'])
        : null;

    if ($row['pagamento_status'] === 'pago') {
        $row['status_visual'] = 'pago';
    } elseif ($row['pagamento_status'] === 'cancelado') {
        $row['status_visual'] = 'cancelado';
    } elseif (is_null($row['pagamento_id'])) {
        $row['status_visual'] = 'nao_gerado';
    } elseif ($venc && $hoje_obj > $venc) {
        $row['status_visual'] = 'atrasado';
    } else {
        $row['status_visual'] = 'pendente';
    }
}
unset($row);

// ── Totalizadores ──────────────────────────────────────────
$total_receber  = array_sum(array_column($rows, 'valor'));
$total_recebido = 0;
foreach ($rows as $r) {
    if ($r['pagamento_status'] === 'pago') {
        $total_recebido += (float) ($r['valor_pago'] ?? $r['valor']);
    }
}
$total_pendente = $total_receber - $total_recebido;
$adimplencia    = $total_receber > 0
    ? round(($total_recebido / $total_receber) * 100, 1) : 0;

// ── Helpers ───────────────────────────────────────────────
function fmt_money(float $v): string {
    return 'R$ ' . number_format($v, 2, ',', '.');
}
function fmt_date(?string $d): string {
    return $d ? date('d/m/Y', strtotime($d)) : '—';
}
function fmt_datetime(?string $d): string {
    return $d ? date('d/m/Y H:i', strtotime($d)) : '—';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ══════ CABEÇALHO DA PÁGINA ══════ -->
<div class="flex flex-col md:flex-row md:items-center gap-4 mb-6">

    <!-- Navegação de mês -->
    <div class="flex items-center gap-2">
        <a href="?mes=<?= $mes_anterior ?>"
           class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all">
            <span class="material-symbols-outlined text-[20px]">chevron_left</span>
        </a>
        <div class="px-5 py-2 bg-white rounded-xl border border-slate-200 shadow-sm min-w-[180px] text-center">
            <p class="text-[15px] font-extrabold text-slate-900 leading-none"><?= $titulo_mes ?></p>
        </div>
        <a href="?mes=<?= $mes_proximo ?>"
           class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all">
            <span class="material-symbols-outlined text-[20px]">chevron_right</span>
        </a>
        <a href="?mes=<?= $hoje_obj->format('Y-m') ?>"
           class="text-[11px] font-bold text-primary hover:underline px-2">Hoje</a>
    </div>

    <div class="flex-1"></div>

    <!-- Botão Gerar Cobranças -->
    <?php if (can('edit_clients')): ?>
    <form method="POST" action="actions/gerar_cobrancas_mes.php"
          onsubmit="return confirm('Gerar cobranças pendentes para <?= $titulo_mes ?>?\nJá existentes serão ignoradas.')">
        <?= csrf_field() ?>
        <input type="hidden" name="mes" value="<?= htmlspecialchars($mes_raw) ?>">
        <button type="submit" class="btn-primary">
            <span class="material-symbols-outlined text-[17px]">add_circle</span>
            Gerar Cobranças do Mês
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- ══════ TOTALIZADORES ══════ -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php
    $cards = [
        ['label'=>'Total a Receber','value'=>fmt_money($total_receber),  'icon'=>'payments',       'color'=>'text-slate-600',   'bg'=>'bg-slate-50',   'border'=>'border-slate-200'],
        ['label'=>'Recebido',       'value'=>fmt_money($total_recebido), 'icon'=>'check_circle',   'color'=>'text-green-600',   'bg'=>'bg-green-50',   'border'=>'border-green-200'],
        ['label'=>'Pendente',       'value'=>fmt_money($total_pendente), 'icon'=>'hourglass_empty','color'=>'text-yellow-600',  'bg'=>'bg-yellow-50',  'border'=>'border-yellow-200'],
        ['label'=>'Adimplência',    'value'=>$adimplencia.'%',           'icon'=>'trending_up',    'color'=>'text-primary',     'bg'=>'bg-lime-50',    'border'=>'border-lime-200'],
    ];
    foreach ($cards as $c): ?>
    <div class="<?= $c['bg'] ?> border <?= $c['border'] ?> rounded-2xl p-4 flex items-center gap-4 shadow-sm">
        <div class="p-2 bg-white rounded-xl shadow-sm border <?= $c['border'] ?> shrink-0">
            <span class="material-symbols-outlined <?= $c['color'] ?> text-[22px] icon-fill"><?= $c['icon'] ?></span>
        </div>
        <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-0.5"><?= $c['label'] ?></p>
            <p class="text-[18px] font-black <?= $c['color'] ?> leading-none truncate"><?= $c['value'] ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══════ FILTROS ══════ -->
<div class="flex flex-wrap gap-3 mb-5 items-center">
    <!-- Busca -->
    <div class="relative">
        <input type="search" id="filtro-busca" placeholder="Buscar cliente..."
               class="pl-9 pr-4 py-2 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/50 w-56 transition-all">
        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[17px]">search</span>
    </div>
    <!-- Status -->
    <div class="flex gap-1.5 flex-wrap">
        <?php
        $filtros_status = [
            'todos'      => ['Todos',     'bg-slate-100 text-slate-600'],
            'pago'       => ['✅ Pagos',   'bg-green-100 text-green-700'],
            'pendente'   => ['⏳ Pendentes','bg-yellow-100 text-yellow-700'],
            'atrasado'   => ['🔴 Atrasados','bg-red-100 text-red-700'],
            'nao_gerado' => ['➕ S/ cobrança','bg-blue-100 text-blue-700'],
            'cancelado'  => ['❌ Cancelados','bg-slate-100 text-slate-500'],
        ];
        foreach ($filtros_status as $key => [$label, $cls]): ?>
        <button type="button" data-filtro-status="<?= $key ?>"
                class="filtro-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?= $cls ?>
                       <?= $key === 'todos' ? 'ring-2 ring-offset-1 ring-slate-400' : '' ?>">
            <?= $label ?>
        </button>
        <?php endforeach; ?>
    </div>
    <!-- Tipo -->
    <div class="flex gap-1.5 ml-auto">
        <?php
        $filtros_tipo = [
            'todos'  => 'Todos',
            'mensal' => '🔁 Mensais',
            'anual'  => '📅 Anuais',
        ];
        foreach ($filtros_tipo as $key => $label): ?>
        <button type="button" data-filtro-tipo="<?= $key ?>"
                class="filtro-tipo-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all
                       bg-slate-100 text-slate-600
                       <?= $key === 'todos' ? 'ring-2 ring-offset-1 ring-slate-400' : '' ?>">
            <?= $label ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══════ TABELA / CARDS ══════ -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

    <!-- Cabeçalho tabela (desktop) -->
    <div class="hidden md:grid grid-cols-[2fr_1fr_1fr_1fr_1.5fr_1.5fr_auto] gap-4 px-6 py-3 bg-slate-50 border-b border-slate-100">
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Cliente</p>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tipo</p>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Valor</p>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Vencimento</p>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Status</p>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pago em</p>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Ações</p>
    </div>

    <!-- Linhas -->
    <div id="lista-pagamentos" class="divide-y divide-slate-50">
    <?php if (empty($rows)): ?>
        <div class="py-16 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-200 block mb-3 icon-fill">payments</span>
            <p class="font-bold text-slate-400">Nenhum cliente para <?= $titulo_mes ?></p>
            <p class="text-sm text-slate-300 mt-1">Clientes anuais só aparecem no mês do seu vencimento.</p>
        </div>
    <?php else: ?>
    <?php foreach ($rows as $row):
        $sv  = $row['status_visual'];
        $val = (float) $row['valor'];

        // Badge status
        $badge_cfg = match($sv) {
            'pago'       => ['✅ Pago',         'bg-green-500/10 text-green-600 border-green-500/30'],
            'pendente'   => ['⏳ Pendente',      'bg-yellow-500/10 text-yellow-600 border-yellow-500/30'],
            'atrasado'   => ['🔴 Atrasado',      'bg-red-500/10 text-red-600 border-red-500/30'],
            'cancelado'  => ['❌ Cancelado',     'bg-slate-500/10 text-slate-500 border-slate-400/30'],
            'nao_gerado' => ['➕ Sem cobrança',  'bg-blue-500/10 text-blue-600 border-blue-400/30'],
            default      => ['— Indefinido',     'bg-slate-100 text-slate-400 border-slate-200'],
        };

        // Badge tipo
        $tipo_badge = $row['tipo_exibicao'] === 'mensal'
            ? '<span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-100 text-blue-700">🔁 Mensal</span>'
            : '<span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-purple-100 text-purple-700">📅 Anual</span>';

        $venc_fmt = $row['data_venc_competencia']
            ? ($row['tipo_exibicao'] === 'mensal'
                ? 'Dia ' . (int)date('d', strtotime($row['data_venc_competencia']))
                : fmt_date($row['data_venc_competencia']))
            : '—';
    ?>
    <div class="pagamento-row flex flex-col md:grid md:grid-cols-[2fr_1fr_1fr_1fr_1.5fr_1.5fr_auto] gap-3 md:gap-4 px-4 md:px-6 py-4 hover:bg-slate-50/60 transition-colors"
         data-nome="<?= strtolower(htmlspecialchars($row['nome'])) ?>"
         data-status="<?= $sv ?>"
         data-tipo="<?= $row['tipo_exibicao'] ?>">

        <!-- Cliente -->
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-[12px] shrink-0"
                 style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);color:#2e7d32">
                <?= strtoupper(substr($row['nome'], 0, 2)) ?>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($row['nome']) ?></p>
                <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($row['dominio'] ?? '—') ?></p>
            </div>
        </div>

        <!-- Tipo -->
        <div class="flex items-center">
            <span class="md:hidden text-[10px] font-bold text-slate-400 mr-2 uppercase">Tipo: </span>
            <?= $tipo_badge ?>
        </div>

        <!-- Valor -->
        <div class="flex items-center">
            <span class="md:hidden text-[10px] font-bold text-slate-400 mr-2 uppercase">Valor: </span>
            <span class="font-bold text-slate-700 text-sm"><?= fmt_money($val) ?></span>
        </div>

        <!-- Vencimento -->
        <div class="flex items-center">
            <span class="md:hidden text-[10px] font-bold text-slate-400 mr-2 uppercase">Venc: </span>
            <span class="text-sm text-slate-600"><?= $venc_fmt ?></span>
        </div>

        <!-- Status -->
        <div class="flex items-center">
            <span class="md:hidden text-[10px] font-bold text-slate-400 mr-2 uppercase">Status: </span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $badge_cfg[1] ?>">
                <?= $badge_cfg[0] ?>
            </span>
        </div>

        <!-- Pago em -->
        <div class="flex items-center">
            <span class="md:hidden text-[10px] font-bold text-slate-400 mr-2 uppercase">Pago em: </span>
            <span class="text-sm text-slate-500">
                <?= $sv === 'pago' ? fmt_datetime($row['pago_em']) : '—' ?>
                <?php if ($sv === 'pago' && $row['metodo']): ?>
                <span class="block text-[10px] text-slate-400 capitalize"><?= htmlspecialchars($row['metodo']) ?></span>
                <?php endif; ?>
            </span>
        </div>

        <!-- Ações -->
        <div class="flex items-center gap-1.5">
            <?php if (can('edit_clients')): ?>

            <?php if (in_array($sv, ['pendente', 'atrasado', 'nao_gerado'])): ?>
            <!-- Botão Marcar como Pago -->
            <button type="button"
                    class="btn-icon btn-pagar"
                    title="Marcar como Pago"
                    data-id="<?= $row['cliente_id'] ?>"
                    data-nome="<?= htmlspecialchars($row['nome']) ?>"
                    data-valor="<?= $val ?>"
                    data-valor-fmt="<?= fmt_money($val) ?>"
                    data-competencia="<?= $primeiro_dia ?>"
                    onclick="abrirModalPagar(this)">
                <span class="material-symbols-outlined text-[20px] text-green-600 hover:text-green-700">check_circle</span>
            </button>
            <?php endif; ?>

            <?php if ($sv === 'pago' && can('manage_users') && $row['pagamento_id']): ?>
            <!-- Botão Cancelar (admin only) -->
            <form method="POST" action="actions/cancelar_pagamento.php"
                  onsubmit="return confirm('Cancelar este pagamento?')">
                <?= csrf_field() ?>
                <input type="hidden" name="pagamento_id" value="<?= $row['pagamento_id'] ?>">
                <button type="submit" class="btn-icon" title="Cancelar pagamento">
                    <span class="material-symbols-outlined text-[20px] text-slate-400 hover:text-red-500">cancel</span>
                </button>
            </form>
            <?php endif; ?>

            <?php endif; // can('edit_clients') ?>

            <!-- Botão Enviar Cobrança -->
            <a href="cobrancas.php" class="btn-icon" title="Ir para Cobranças">
                <span class="material-symbols-outlined text-[20px] text-slate-400 hover:text-primary">send</span>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <!-- Rodapé contador -->
    <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
        <p class="text-xs text-slate-400" id="contador-rows">
            <?= count($rows) ?> cliente(s) para <?= $titulo_mes ?>
        </p>
    </div>
</div>

<!-- ══════ MODAL CONFIRMAR PAGAMENTO ══════ -->
<div id="modal-pagar"
     class="hidden fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 pb-4 sm:pb-0"
     onclick="if(event.target===this)fecharModalPagar()">

    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl flex flex-col max-h-[90vh]"
         onclick="event.stopPropagation()">

        <!-- Header fixo -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 shrink-0">
            <div>
                <h3 class="font-bold text-slate-900">Confirmar Pagamento</h3>
                <p class="text-xs text-slate-400 mt-0.5" id="modal-pagar-subtitle">—</p>
            </div>
            <button onclick="fecharModalPagar()"
                    class="p-2 hover:bg-slate-100 rounded-xl transition-colors text-slate-400">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Corpo com scroll -->
        <form id="form-pagar" method="POST" action="actions/confirmar_pagamento.php"
              class="overflow-y-auto flex-1 px-6 py-5 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="cliente_id"  id="pagar-cliente-id">
            <input type="hidden" name="competencia" id="pagar-competencia">

            <!-- Valor -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                    Valor (R$)
                </label>
                <input type="number" name="valor" id="pagar-valor" step="0.01" min="0" required
                       class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-800
                              focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/50 transition-all">
            </div>

            <!-- Método -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                    Método de Pagamento *
                </label>
                <select name="metodo" required
                        class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm
                               focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/50 transition-all appearance-none cursor-pointer">
                    <option value="">Selecione...</option>
                    <option value="pix">💚 PIX</option>
                    <option value="dinheiro">💵 Dinheiro</option>
                    <option value="transferencia">🏦 Transferência</option>
                    <option value="cartao">💳 Cartão</option>
                </select>
            </div>

            <!-- Data do pagamento -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                    Data do Pagamento
                </label>
                <input type="date" name="data_pagamento" id="pagar-data"
                       value="<?= date('Y-m-d') ?>"
                       class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm
                              focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/50 transition-all">
            </div>

            <!-- Observação -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                    Observação <span class="font-normal text-slate-400">(opcional)</span>
                </label>
                <textarea name="observacao" rows="2" placeholder="Ex: NF emitida, referência..."
                          class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm resize-none
                                 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/50 transition-all"></textarea>
            </div>
        </form>

        <!-- Footer fixo -->
        <div class="flex gap-3 px-6 py-4 border-t border-slate-100 shrink-0">
            <button type="button" onclick="fecharModalPagar()"
                    class="flex-1 py-2.5 text-slate-600 font-semibold hover:bg-slate-100 rounded-xl text-sm transition-colors">
                Cancelar
            </button>
            <button type="submit" form="form-pagar"
                    class="flex-1 btn-primary justify-center">
                <span class="material-symbols-outlined text-[16px]">check_circle</span>
                Confirmar Pagamento
            </button>
        </div>
    </div>
</div>

<script>
// ── Filtros ────────────────────────────────────────────────
let filtroStatus = 'todos';
let filtroTipo   = 'todos';
let filtroBusca  = '';

function aplicarFiltros() {
    const rows     = document.querySelectorAll('.pagamento-row');
    let   visiveis = 0;
    rows.forEach(row => {
        const nome   = row.dataset.nome   || '';
        const status = row.dataset.status || '';
        const tipo   = row.dataset.tipo   || '';

        const okStatus = filtroStatus === 'todos' || status === filtroStatus;
        const okTipo   = filtroTipo   === 'todos' || tipo   === filtroTipo;
        const okBusca  = !filtroBusca || nome.includes(filtroBusca);

        const visivel = okStatus && okTipo && okBusca;
        row.style.display = visivel ? '' : 'none';
        if (visivel) visiveis++;
    });
    document.getElementById('contador-rows').textContent =
        visiveis + ' cliente(s) exibido(s)';
}

// Botões de status
document.querySelectorAll('.filtro-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filtro-btn').forEach(b =>
            b.classList.remove('ring-2', 'ring-offset-1', 'ring-slate-400', 'ring-green-400', 'ring-yellow-400', 'ring-red-400', 'ring-blue-400'));
        btn.classList.add('ring-2', 'ring-offset-1', 'ring-slate-400');
        filtroStatus = btn.dataset.filtroStatus;
        aplicarFiltros();
    });
});

// Botões de tipo
document.querySelectorAll('.filtro-tipo-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filtro-tipo-btn').forEach(b =>
            b.classList.remove('ring-2', 'ring-offset-1', 'ring-slate-400'));
        btn.classList.add('ring-2', 'ring-offset-1', 'ring-slate-400');
        filtroTipo = btn.dataset.filtroTipo;
        aplicarFiltros();
    });
});

// Busca
document.getElementById('filtro-busca').addEventListener('input', function() {
    filtroBusca = this.value.trim().toLowerCase();
    aplicarFiltros();
});

// ── Modal Pagar ────────────────────────────────────────────
function abrirModalPagar(btn) {
    document.getElementById('pagar-cliente-id').value  = btn.dataset.id;
    document.getElementById('pagar-competencia').value = btn.dataset.competencia;
    document.getElementById('pagar-valor').value       = btn.dataset.valor;
    document.getElementById('modal-pagar-subtitle').textContent =
        btn.dataset.nome + ' — ' + btn.dataset.valorFmt;
    document.getElementById('pagar-data').value = '<?= date('Y-m-d') ?>';

    const modal = document.getElementById('modal-pagar');
    modal.classList.remove('hidden');
    modal.offsetHeight; // force reflow para animação
}

function fecharModalPagar() {
    document.getElementById('modal-pagar').classList.add('hidden');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') fecharModalPagar();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
