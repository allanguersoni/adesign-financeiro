<?php
/**
 * app/clientes.php — CRUD Completo com Ordenação e Busca
 */
$page_title = 'Clientes';
$page_atual = 'clientes';

require_once 'config/auth.php';

$header_btn = can('edit_clients') ? '<button onclick="abrirModal(\'modal-criar\')"
    class="btn-primary">
    <span class="material-symbols-outlined text-[18px]">add</span> Novo Cliente
</button>' : '';

require_once 'includes/header.php';

// ── Métricas ──────────────────────────────────
$total     = (int)   $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$ativos    = (int)   $pdo->query("SELECT COUNT(*) FROM clientes WHERE status='em dia'")->fetchColumn();
$pendentes = (int)   $pdo->query("SELECT COUNT(*) FROM clientes WHERE status='pendente'")->fetchColumn();
$vencendo  = (int)   $pdo->query("SELECT COUNT(*) FROM clientes WHERE status='vence em 15 dias'")->fetchColumn();

// ── Parâmetros de filtro e ordenação ────────────
$filter = $_GET['status'] ?? '';
$sort   = $_GET['sort']   ?? 'criado_em';
$dir    = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$search = trim($_GET['q'] ?? '');

// Colunas permitidas para ordenação
$allowed_sorts = ['nome', 'dominio', 'valor_anual', 'tipo_pagamento', 'status', 'data_vencimento_base', 'criado_em'];
if (!in_array($sort, $allowed_sorts)) $sort = 'criado_em';

$filtros_validos = ['em dia', 'pendente', 'vence em 15 dias'];

// Monta WHERE
$conditions = [];
$params     = [];

if (in_array($filter, $filtros_validos)) {
    $conditions[] = 'status = ?';
    $params[]     = $filter;
}
if (!empty($search)) {
    $conditions[] = '(nome LIKE ? OR email LIKE ? OR dominio LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sql   = "SELECT * FROM clientes {$where} ORDER BY {$sort} {$dir}";
$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// Helper: gera URL de sort para um campo
function sort_url(string $col, string $current_sort, string $current_dir, string $filter, string $search): string
{
    $new_dir = ($current_sort === $col && $current_dir === 'ASC') ? 'DESC' : 'ASC';
    $params = ['sort' => $col, 'dir' => $new_dir];
    if ($filter) $params['status'] = $filter;
    if ($search) $params['q']     = $search;
    return '?' . http_build_query($params);
}

// Helper: ícone de sort
function sort_icon(string $col, string $current_sort, string $current_dir): string
{
    if ($col !== $current_sort) {
        return '<span class="material-symbols-outlined text-[14px] text-slate-300 ml-0.5 opacity-0 group-hover:opacity-100 transition-opacity">unfold_more</span>';
    }
    $icon = $current_dir === 'ASC' ? 'arrow_upward' : 'arrow_downward';
    return '<span class="material-symbols-outlined text-[14px] text-primary ml-0.5">' . $icon . '</span>';
}
?>

<!-- BUSCA INLINE (complementa a busca do header) -->
<div class="flex flex-wrap items-center gap-3 mb-6">
    <form method="GET" action="clientes.php" class="flex-1 flex items-center gap-2 min-w-0">
        <?php if ($filter): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>"/>
        <?php endif; ?>
        <div class="relative flex-1 max-w-md">
            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[17px] pointer-events-none">search</span>
            <input
                id="inline-search"
                name="q"
                type="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Buscar por nome, e-mail ou domínio..."
                autocomplete="off"
                class="w-full h-10 pl-10 pr-4 bg-white border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400
                       focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/50 transition-all shadow-sm"
            />
            <?php if ($search): ?>
            <a href="?<?= $filter ? 'status=' . urlencode($filter) : '' ?>"
               class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </a>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn-primary h-10 px-5 text-[13px]">
            <span class="material-symbols-outlined text-[16px]">search</span>
            Buscar
        </button>
    </form>

    <?php if ($filter): ?>
    <a href="clientes.php<?= $search ? '?q=' . urlencode($search) : '' ?>"
       class="flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-50 transition-colors shadow-sm">
        <span class="material-symbols-outlined text-[15px]">filter_alt_off</span> Limpar filtro
    </a>
    <?php endif; ?>
</div>

<!-- MÉTRICAS -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-6">
    <?php
    $cards = [
        ['label' => 'Total',            'val' => $total,     'icon' => 'groups',         'color' => 'text-slate-600',  'bg' => 'bg-slate-100',  'filter' => ''],
        ['label' => 'Em Dia',           'val' => $ativos,    'icon' => 'verified',        'color' => 'text-tertiary',   'bg' => 'bg-green-50',   'filter' => 'em dia'],
        ['label' => 'Pendentes',        'val' => $pendentes, 'icon' => 'pending_actions', 'color' => 'text-error',      'bg' => 'bg-red-50',     'filter' => 'pendente'],
        ['label' => 'Vence em 15 dias', 'val' => $vencendo,  'icon' => 'event_upcoming',  'color' => 'text-amber-600',  'bg' => 'bg-amber-50',   'filter' => 'vence em 15 dias'],
    ];
    foreach ($cards as $c):
        $is_active = $filter === $c['filter'];
        $href_params = [];
        if ($c['filter']) $href_params['status'] = $c['filter'];
        if ($search)      $href_params['q']      = $search;
        $href = 'clientes.php' . ($href_params ? '?' . http_build_query($href_params) : '');
    ?>
    <a href="<?= $href ?>"
       class="bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all border cursor-pointer
              <?= $is_active ? 'border-primary/40 ring-2 ring-primary/25' : 'border-slate-100 hover:border-slate-200' ?>">
        <div class="flex justify-between items-start mb-3">
            <div class="p-2 <?= $c['bg'] ?> rounded-xl">
                <span class="material-symbols-outlined <?= $c['color'] ?> text-xl icon-fill"><?= $c['icon'] ?></span>
            </div>
            <?php if ($is_active): ?>
            <span class="text-[10px] font-bold text-primary uppercase tracking-wider bg-primary/10 px-2 py-0.5 rounded-full">Ativo</span>
            <?php endif; ?>
        </div>
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500"><?= $c['label'] ?></p>
        <h3 class="text-2xl font-black text-slate-900 mt-0.5"><?= $c['val'] ?></h3>
    </a>
    <?php endforeach; ?>
</section>

<!-- TABELA COM HEADERS CLICÁVEIS -->
<section class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-7 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-slate-100">
        <div>
            <h3 class="font-bold text-slate-900">Base de Assinantes</h3>
            <p class="text-xs text-slate-400 mt-0.5">
                <?= count($clientes) ?> de <?= $total ?> cliente<?= $total !== 1 ? 's' : '' ?>
                <?php if ($search): ?>· buscando <strong>"<?= htmlspecialchars($search) ?>"</strong><?php endif; ?>
                <?php if ($filter): ?>· filtro: <strong><?= htmlspecialchars($filter) ?></strong><?php endif; ?>
            </p>
        </div>
        <!-- Info de ordenação ativa -->
        <?php if ($sort !== 'criado_em'): ?>
        <span class="text-xs text-slate-500 bg-slate-100 px-3 py-1.5 rounded-lg font-medium flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">sort</span>
            Ordenado por: <strong class="ml-1"><?= match($sort) {
                'nome'                  => 'Nome',
                'dominio'               => 'Domínio',
                'valor_anual'           => 'Valor',
                'tipo_pagamento'        => 'Pagamento',
                'status'                => 'Status',
                'data_vencimento_base'  => 'Vencimento',
                default                 => $sort
            } ?></strong>
            <span class="ml-0.5 text-[11px] text-primary font-bold"><?= $dir ?></span>
        </span>
        <?php endif; ?>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="tabela-clientes">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <?php
                    $cols = [
                        ['key' => 'nome',                 'label' => 'Cliente'],
                        ['key' => 'dominio',              'label' => 'Domínio'],
                        ['key' => 'valor_anual',          'label' => 'Valor Anual', 'align' => 'right'],
                        ['key' => 'tipo_pagamento',       'label' => 'Pgto'],
                        ['key' => 'status',               'label' => 'Status'],
                        ['key' => 'data_vencimento_base', 'label' => 'Vencimento'],
                    ];
                    foreach ($cols as $col):
                        $is_sorted = $sort === $col['key'];
                        $align = ($col['align'] ?? 'left') === 'right' ? 'text-right' : 'text-left';
                        $th_class = $is_sorted ? 'text-primary' : 'text-slate-500';
                    ?>
                    <th class="px-6 py-3.5 text-[11px] font-bold uppercase tracking-wider <?= $align ?>">
                        <a href="<?= sort_url($col['key'], $sort, $dir, $filter, $search) ?>"
                           class="inline-flex items-center gap-0.5 group hover:text-primary transition-colors <?= $th_class ?>">
                            <?= $col['label'] ?>
                            <?= sort_icon($col['key'], $sort, $dir) ?>
                        </a>
                    </th>
                    <?php endforeach; ?>
                    <th class="px-6 py-3.5 text-[11px] font-bold uppercase tracking-wider text-center text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50" id="tbody-clientes">
                <?php if (empty($clientes)): ?>
                <tr>
                    <td colspan="7" class="px-7 py-16 text-center">
                        <span class="material-symbols-outlined text-5xl text-slate-200 block mb-3 icon-fill">
                            <?= $search ? 'search_off' : 'group_off' ?>
                        </span>
                        <p class="text-slate-400 font-medium text-sm">
                            <?= $search
                                ? "Nenhum cliente encontrado para <strong>\"" . htmlspecialchars($search) . "\"</strong>"
                                : 'Nenhum cliente cadastrado ainda.' ?>
                        </p>
                        <?php if (!$search && can('edit_clients')): ?>
                        <button onclick="document.getElementById('modal-criar').classList.remove('hidden')"
                                class="btn-primary mt-4 mx-auto">
                            <span class="material-symbols-outlined text-[16px]">add</span> Adicionar Cliente
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($clientes as $c):
                    $ini   = strtoupper(substr($c['nome'], 0, 1));
                    $badge = match($c['status']) {
                        'em dia'           => 'bg-green-100 text-green-800',
                        'pendente'         => 'bg-red-100 text-red-700',
                        'vence em 15 dias' => 'bg-amber-100 text-amber-700',
                        default            => 'bg-slate-100 text-slate-500',
                    };
                    // Destaca termos buscados
                    $nome_display = $search
                        ? preg_replace('/(' . preg_quote(htmlspecialchars($search), '/') . ')/i',
                            '<mark class="bg-yellow-100 text-yellow-900 rounded px-0.5">$1</mark>',
                            htmlspecialchars($c['nome']))
                        : htmlspecialchars($c['nome']);
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors" data-nome="<?= strtolower(htmlspecialchars($c['nome'])) ?>">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-black text-sm shrink-0"
                                 style="background:linear-gradient(135deg,rgba(153,224,0,.15),rgba(69,104,0,.08))">
                                <?= $ini ?>
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-slate-900"><?= $nome_display ?></p>
                                <p class="text-xs text-slate-400"><?= htmlspecialchars($c['email'] ?? '') ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-500"><?= htmlspecialchars($c['dominio'] ?? '—') ?></td>
                    <td class="px-6 py-4 text-sm font-bold text-slate-900 text-right whitespace-nowrap">
                        <?= $c['valor_anual'] ? 'R$ ' . number_format($c['valor_anual'], 2, ',', '.') : '—' ?>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500 font-medium"><?= htmlspecialchars($c['tipo_pagamento'] ?? '—') ?></td>
                    <td class="px-6 py-4">
                        <span class="<?= $badge ?> px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider whitespace-nowrap">
                            <?= htmlspecialchars($c['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-500 whitespace-nowrap">
                        <?= $c['data_vencimento_base'] ? date('d/m/Y', strtotime($c['data_vencimento_base'])) : '—' ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if (can('edit_clients')): ?>
                        <div class="flex items-center justify-center gap-1">
                            <button type="button" onclick="abrirEdicao(<?= $c['id'] ?>)"
                                    class="btn-icon btn-icon-edit" title="Editar cliente">
                                <span class="material-symbols-outlined text-[17px]">edit</span>
                            </button>
                            <form method="POST" action="actions/excluir_cliente.php" class="inline"
                                  onsubmit="return confirm('Excluir \'<?= htmlspecialchars(addslashes($c['nome'])) ?>\'? Esta ação não pode ser desfeita.')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn-icon btn-icon-del" title="Excluir cliente">
                                    <span class="material-symbols-outlined text-[17px]">delete</span>
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <div class="text-center">
                            <span class="material-symbols-outlined text-[17px] text-slate-300 pointer-events-none" title="Apenas visualização">lock</span>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Rodapé da tabela -->
    <?php if (count($clientes) > 0): ?>
    <div class="px-7 py-3 border-t border-slate-50 flex items-center justify-between">
        <p class="text-xs text-slate-400">
            Mostrando <strong><?= count($clientes) ?></strong> registro<?= count($clientes) !== 1 ? 's' : '' ?>
        </p>
        <a href="clientes.php" class="text-xs text-primary font-bold hover:underline">Limpar tudo</a>
    </div>
    <?php endif; ?>
</section>

<style>
@keyframes modalIn {
  from { opacity:0; transform:translateY(18px) scale(0.98); }
  to   { opacity:1; transform:translateY(0)    scale(1);    }
}
@keyframes overlayIn {
  from { opacity:0; }
  to   { opacity:1; }
}
.modal-box { animation: modalIn 0.26s cubic-bezier(0.16,1,0.3,1) both; }
.modal-overlay { animation: overlayIn 0.2s ease both; }
.modal-input {
  width:100%; background:rgba(255,255,255,0.05);
  border:1px solid rgba(255,255,255,0.1); border-radius:12px;
  padding:11px 16px; color:#f1f5f9; font-size:14px;
  outline:none; transition:border-color .15s,box-shadow .15s;
  -webkit-appearance:none; appearance:none;
}
.modal-input::placeholder { color:#475569; }
.modal-input:focus {
  border-color:rgba(74,222,128,.6);
  box-shadow:0 0 0 3px rgba(74,222,128,.12);
}
.modal-input option { background:#0f172a; color:#f1f5f9; }
.modal-label {
  display:block; font-size:10px; font-weight:700;
  text-transform:uppercase; letter-spacing:.1em;
  color:#64748b; margin-bottom:6px;
}
.modal-footer-btn-cancel {
  padding:10px 20px; border-radius:12px; font-size:14px;
  font-weight:600; color:#cbd5e1;
  border:1px solid rgba(255,255,255,0.1);
  background:transparent; cursor:pointer; transition:background .15s;
}
.modal-footer-btn-cancel:hover { background:rgba(255,255,255,0.06); }
.modal-footer-btn-save {
  padding:10px 20px; border-radius:12px; font-size:14px;
  font-weight:700; color:#fff; cursor:pointer;
  background:linear-gradient(135deg,#22c55e,#059669);
  box-shadow:0 4px 16px rgba(34,197,94,.3);
  border:none; display:flex; align-items:center; gap:6px;
  transition:box-shadow .15s,transform .15s;
}
.modal-footer-btn-save:hover {
  box-shadow:0 6px 24px rgba(34,197,94,.45);
  transform:translateY(-1px);
}
</style>

<!-- ══════ MODAL CRIAR ══════ -->
<div id="modal-criar"
     class="modal-overlay hidden fixed inset-0 z-[60] flex items-end sm:items-center justify-center"
     style="background:rgba(2,8,23,.8);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)"
     onclick="if(event.target===this)fecharModal('modal-criar')">

  <div class="modal-box w-full sm:max-w-lg flex flex-col rounded-t-2xl sm:rounded-2xl"
       style="max-height:90vh;background:rgba(15,23,42,.97);border:1px solid rgba(255,255,255,.08);box-shadow:0 25px 60px rgba(0,0,0,.6)"
       onclick="event.stopPropagation()">

    <!-- Header fixo -->
    <div class="flex items-center justify-between px-6 py-4 shrink-0" style="border-bottom:1px solid rgba(255,255,255,.08)">
      <div>
        <h3 class="font-bold text-slate-100">Novo Cliente</h3>
        <p class="text-xs text-slate-500 mt-0.5">Preencha os dados do assinante</p>
      </div>
      <button onclick="fecharModal('modal-criar')" class="p-2 rounded-xl text-slate-500 hover:text-slate-200 transition-colors" style="background:rgba(255,255,255,.05)">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <!-- Form: scroll interno + footer fixo -->
    <form method="POST" action="actions/salvar_cliente.php" class="flex flex-col flex-1 min-h-0">
      <?= csrf_field() ?>

      <!-- Área com scroll -->
      <div class="overflow-y-auto flex-1 px-6 py-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          <!-- Nome -->
          <div class="sm:col-span-2">
            <label class="modal-label">Nome do Cliente <span style="color:#4ade80">*</span></label>
            <input name="nome" type="text" required placeholder="Ex: Empresa LTDA" class="modal-input"/>
          </div>

          <!-- E-mail -->
          <div>
            <label class="modal-label">E-mail</label>
            <input name="email" type="email" placeholder="contato@empresa.com" class="modal-input"/>
          </div>

          <!-- Domínio -->
          <div>
            <label class="modal-label">Domínio</label>
            <input name="dominio" type="text" placeholder="empresa.com.br" class="modal-input"/>
          </div>

          <!-- Tipo de Cobrança -->
          <div>
            <label class="modal-label">Tipo de Cobrança</label>
            <select name="tipo_recorrencia" id="criar-recorrencia"
                    onchange="toggleCamposRecorrencia('criar')"
                    class="modal-input cursor-pointer">
              <option value="anual">Anual</option>
              <option value="mensal">Mensal</option>
            </select>
          </div>

          <!-- Dia de Vencimento (só para mensal) -->
          <div id="criar-dia-wrap" class="hidden">
            <label class="modal-label">Dia de Vencimento <span style="color:#4ade80">*</span></label>
            <input name="dia_vencimento" id="criar-dia" type="number" min="1" max="28" value="1" placeholder="1–28" class="modal-input"/>
          </div>

          <!-- Valor (label muda anual ↔ mensal) -->
          <div>
            <label class="modal-label" id="criar-label-valor">Valor Anual (R$)</label>
            <input name="valor_anual" type="number" step="0.01" min="0" placeholder="0.00" class="modal-input"/>
          </div>

          <!-- Forma de Pagamento -->
          <div>
            <label class="modal-label">Forma de Pagamento</label>
            <select name="tipo_pagamento" class="modal-input cursor-pointer">
              <option value="a vista">À vista</option>
              <option value="2x">2x</option>
              <option value="3x">3x</option>
            </select>
          </div>

          <!-- Alertar Admin -->
          <div>
            <label class="modal-label">Alertar Admin (dias antes)</label>
            <input name="alerta_admin_dias" type="number" min="1" max="60" value="15" placeholder="15" class="modal-input"/>
          </div>

          <!-- Alertar Cliente -->
          <div>
            <label class="modal-label">Alertar Cliente (dias antes)</label>
            <input name="alerta_cliente_dias" type="number" min="1" max="60" value="7" placeholder="7" class="modal-input"/>
          </div>

          <!-- Data de Vencimento (oculta se mensal) -->
          <div id="criar-data-wrap">
            <label class="modal-label">Data de Vencimento</label>
            <input name="data_vencimento_base" type="date" class="modal-input"/>
          </div>

          <!-- Status -->
          <div>
            <label class="modal-label">Status</label>
            <select name="status" class="modal-input cursor-pointer">
              <option value="em dia">Em dia</option>
              <option value="pendente">Pendente</option>
              <option value="vence em 15 dias">Vence em 15 dias</option>
            </select>
          </div>

        </div>
      </div>

      <!-- Footer fixo — botões sempre visíveis -->
      <div class="px-6 py-4 flex justify-end gap-3 shrink-0" style="border-top:1px solid rgba(255,255,255,.08)">
        <button type="button" onclick="fecharModal('modal-criar')" class="modal-footer-btn-cancel">Cancelar</button>
        <button type="submit" class="modal-footer-btn-save">
          <span class="material-symbols-outlined text-[16px]">save</span> Salvar Cliente
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════ MODAL EDITAR ══════ -->
<div id="modal-editar"
     class="modal-overlay hidden fixed inset-0 z-[60] flex items-end sm:items-center justify-center"
     style="background:rgba(2,8,23,.8);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)"
     onclick="if(event.target===this)fecharModal('modal-editar')">

  <div class="modal-box w-full sm:max-w-lg flex flex-col rounded-t-2xl sm:rounded-2xl"
       style="max-height:90vh;background:rgba(15,23,42,.97);border:1px solid rgba(255,255,255,.08);box-shadow:0 25px 60px rgba(0,0,0,.6)"
       onclick="event.stopPropagation()">

    <!-- Header fixo -->
    <div class="flex items-center justify-between px-6 py-4 shrink-0" style="border-bottom:1px solid rgba(255,255,255,.08)">
      <div>
        <h3 class="font-bold text-slate-100">Editar Cliente</h3>
        <p class="text-xs text-slate-500 mt-0.5">Atualize os dados do assinante</p>
      </div>
      <button onclick="fecharModal('modal-editar')" class="p-2 rounded-xl text-slate-500 hover:text-slate-200 transition-colors" style="background:rgba(255,255,255,.05)">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <!-- Form -->
    <form method="POST" action="actions/editar_cliente.php" class="flex flex-col flex-1 min-h-0">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="edit-id"/>

      <!-- Loading spinner -->
      <div id="edit-loading" class="hidden flex-1 flex flex-col items-center justify-center py-16">
        <div class="w-10 h-10 border-2 rounded-full animate-spin mb-3"
             style="border-color:rgba(74,222,128,.2);border-top-color:#4ade80"></div>
        <p class="text-sm text-slate-500">Carregando dados...</p>
      </div>

      <!-- Área com scroll -->
      <div id="edit-fields" class="overflow-y-auto flex-1 px-6 py-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          <!-- Nome -->
          <div class="sm:col-span-2">
            <label class="modal-label">Nome do Cliente <span style="color:#4ade80">*</span></label>
            <input name="nome" id="edit-nome" type="text" required class="modal-input"/>
          </div>

          <!-- E-mail -->
          <div>
            <label class="modal-label">E-mail</label>
            <input name="email" id="edit-email" type="email" class="modal-input"/>
          </div>

          <!-- Domínio -->
          <div>
            <label class="modal-label">Domínio</label>
            <input name="dominio" id="edit-dominio" type="text" class="modal-input"/>
          </div>

          <!-- Tipo de Cobrança -->
          <div>
            <label class="modal-label">Tipo de Cobrança</label>
            <select name="tipo_recorrencia" id="edit-recorrencia"
                    onchange="toggleCamposRecorrencia('edit')"
                    class="modal-input cursor-pointer">
              <option value="anual">Anual</option>
              <option value="mensal">Mensal</option>
            </select>
          </div>

          <!-- Dia de Vencimento (só mensal) -->
          <div id="edit-dia-wrap" class="hidden">
            <label class="modal-label">Dia de Vencimento <span style="color:#4ade80">*</span></label>
            <input name="dia_vencimento" id="edit-dia" type="number" min="1" max="28" placeholder="1–28" class="modal-input"/>
          </div>

          <!-- Valor (label dinâmico) -->
          <div>
            <label class="modal-label" id="edit-label-valor">Valor Anual (R$)</label>
            <input name="valor_anual" id="edit-valor" type="number" step="0.01" min="0" placeholder="0.00" class="modal-input"/>
          </div>

          <!-- Forma de Pagamento -->
          <div>
            <label class="modal-label">Forma de Pagamento</label>
            <select name="tipo_pagamento" id="edit-tipo" class="modal-input cursor-pointer">
              <option value="a vista">À vista</option>
              <option value="2x">2x</option>
              <option value="3x">3x</option>
            </select>
          </div>

          <!-- Alertar Admin -->
          <div>
            <label class="modal-label">Alertar Admin (dias antes)</label>
            <input name="alerta_admin_dias" id="edit-alerta-admin" type="number" min="1" max="60" placeholder="15" class="modal-input"/>
          </div>

          <!-- Alertar Cliente -->
          <div>
            <label class="modal-label">Alertar Cliente (dias antes)</label>
            <input name="alerta_cliente_dias" id="edit-alerta-cliente" type="number" min="1" max="60" placeholder="7" class="modal-input"/>
          </div>

          <!-- Data de Vencimento (oculta se mensal) -->
          <div id="edit-data-wrap">
            <label class="modal-label">Data de Vencimento</label>
            <input name="data_vencimento_base" id="edit-vencimento" type="date" class="modal-input"/>
          </div>

          <!-- Status -->
          <div>
            <label class="modal-label">Status</label>
            <select name="status" id="edit-status" class="modal-input cursor-pointer">
              <option value="em dia">Em dia</option>
              <option value="pendente">Pendente</option>
              <option value="vence em 15 dias">Vence em 15 dias</option>
            </select>
          </div>

        </div>
      </div>

      <!-- Footer fixo -->
      <div class="px-6 py-4 flex justify-end gap-3 shrink-0" style="border-top:1px solid rgba(255,255,255,.08)">
        <button type="button" onclick="fecharModal('modal-editar')" class="modal-footer-btn-cancel">Cancelar</button>
        <button type="submit" class="modal-footer-btn-save">
          <span class="material-symbols-outlined text-[16px]">save</span> Salvar Alterações
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Abrir modal com animação
function abrirModal(id) {
    const overlay = document.getElementById(id);
    overlay.classList.remove('hidden');
    // Reinicia animação do box a cada abertura
    const box = overlay.querySelector('.modal-box');
    if (box) { box.style.animation = 'none'; box.offsetHeight; box.style.animation = ''; }
}

// ── Fechar modal
function fecharModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// ── Tecla ESC fecha qualquer modal aberto
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        ['modal-criar', 'modal-editar'].forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.classList.contains('hidden')) fecharModal(id);
        });
    }
});

// ── Toggle campos dinâmicos conforme tipo de recorrência
function toggleCamposRecorrencia(prefix) {
    const sel        = document.getElementById(prefix + '-recorrencia');
    const diaWrap    = document.getElementById(prefix + '-dia-wrap');
    const dataWrap   = document.getElementById(prefix + '-data-wrap');
    const labelValor = document.getElementById(prefix + '-label-valor');
    const isMensal   = sel && sel.value === 'mensal';
    if (diaWrap)    diaWrap.classList.toggle('hidden', !isMensal);
    if (dataWrap)   dataWrap.classList.toggle('hidden',  isMensal);
    if (labelValor) labelValor.textContent = isMensal ? 'Valor Mensal (R$)' : 'Valor Anual (R$)';
}

// ── Alias de compatibilidade (chamado em código gerado anteriormente)
function toggleDiaVenc(prefix) { toggleCamposRecorrencia(prefix); }

// ── Abre modal de edição e popula via fetch
async function abrirEdicao(id) {
    abrirModal('modal-editar');
    const loading = document.getElementById('edit-loading');
    const fields  = document.getElementById('edit-fields');
    loading.classList.remove('hidden');
    fields.classList.add('hidden');
    try {
        const data = await fetch(`actions/get_cliente.php?id=${id}`).then(r => r.json());
        document.getElementById('edit-id').value             = data.id                   ?? '';
        document.getElementById('edit-nome').value           = data.nome                 ?? '';
        document.getElementById('edit-email').value          = data.email                ?? '';
        document.getElementById('edit-dominio').value        = data.dominio              ?? '';
        document.getElementById('edit-valor').value          = data.valor_anual          ?? '';
        document.getElementById('edit-tipo').value           = data.tipo_pagamento       ?? 'a vista';
        document.getElementById('edit-status').value         = data.status               ?? 'em dia';
        document.getElementById('edit-vencimento').value     = data.data_vencimento_base ?? '';
        document.getElementById('edit-recorrencia').value    = data.tipo_recorrencia     ?? 'anual';
        document.getElementById('edit-dia').value            = data.dia_vencimento       ?? 1;
        document.getElementById('edit-alerta-admin').value   = data.alerta_admin_dias    ?? 15;
        document.getElementById('edit-alerta-cliente').value = data.alerta_cliente_dias  ?? 7;
        toggleCamposRecorrencia('edit');
        loading.classList.add('hidden');
        fields.classList.remove('hidden');
    } catch {
        fecharModal('modal-editar');
        alert('Erro ao carregar dados. Tente novamente.');
    }
}

// ── Auto-abre modal de criação se vier via ?novo=1 (ex: botão do dashboard)
document.addEventListener('DOMContentLoaded', () => {
    if (new URLSearchParams(location.search).get('novo') === '1') {
        abrirModal('modal-criar');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
