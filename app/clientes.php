<?php
/**
 * app/clientes.php — CRUD Completo com Ordenação e Busca
 */
$page_title = 'Clientes';
$page_atual = 'clientes';

require_once 'config/auth.php';

$header_btn = can('edit_clients') ? '<button onclick="document.getElementById(\'modal-criar\').classList.remove(\'hidden\')"
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

<!-- ══════ MODAL CRIAR ══════ -->
<div id="modal-criar"
     class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-4"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-7 py-5 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-slate-900">Novo Cliente</h3>
                <p class="text-xs text-slate-400">Preencha os dados do assinante</p>
            </div>
            <button onclick="document.getElementById('modal-criar').classList.add('hidden')"
                    class="p-2 hover:bg-slate-100 rounded-xl transition-colors text-slate-400">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="actions/salvar_cliente.php" class="px-7 py-6 space-y-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Nome do Cliente *</label>
                    <input name="nome" type="text" required placeholder="Ex: Empresa LTDA"
                           class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/50 transition-all"/>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">E-mail</label>
                    <input name="email" type="email" placeholder="contato@empresa.com"
                           class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Domínio</label>
                    <input name="dominio" type="text" placeholder="empresa.com.br"
                           class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Valor Anual (R$)</label>
                    <input name="valor_anual" type="number" step="0.01" min="0" placeholder="1200.00"
                           class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Forma de Pagamento</label>
                    <select name="tipo_pagamento"
                            class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all appearance-none cursor-pointer">
                        <option value="a vista">À vista</option>
                        <option value="2x">2x</option>
                        <option value="3x">3x</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Status</label>
                    <select name="status"
                            class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all appearance-none cursor-pointer">
                        <option value="em dia">Em dia</option>
                        <option value="pendente">Pendente</option>
                        <option value="vence em 15 dias">Vence em 15 dias</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Data de Vencimento</label>
                    <input name="data_vencimento_base" type="date"
                           class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-criar').classList.add('hidden')"
                        class="px-5 py-2.5 text-slate-600 font-semibold hover:bg-slate-100 rounded-xl text-sm transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="btn-primary">
                    <span class="material-symbols-outlined text-[16px]">save</span> Salvar Cliente
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════ MODAL EDITAR ══════ -->
<div id="modal-editar"
     class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-4"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-7 py-5 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-slate-900">Editar Cliente</h3>
                <p class="text-xs text-slate-400">Atualize os dados do assinante</p>
            </div>
            <button onclick="document.getElementById('modal-editar').classList.add('hidden')"
                    class="p-2 hover:bg-slate-100 rounded-xl transition-colors text-slate-400">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="actions/editar_cliente.php" class="px-7 py-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit-id"/>

            <div id="edit-loading" class="hidden text-center py-8 text-slate-400">
                <div class="inline-block w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin mb-2"></div>
                <p class="text-sm">Carregando...</p>
            </div>

            <div id="edit-fields" class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Nome do Cliente *</label>
                    <input name="nome" id="edit-nome" type="text" required
                           class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">E-mail</label>
                    <input name="email" id="edit-email" type="email"
                           class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Domínio</label>
                    <input name="dominio" id="edit-dominio" type="text"
                           class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Valor Anual (R$)</label>
                    <input name="valor_anual" id="edit-valor" type="number" step="0.01" min="0"
                           class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Forma de Pagamento</label>
                    <select name="tipo_pagamento" id="edit-tipo"
                            class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all appearance-none cursor-pointer">
                        <option value="a vista">À vista</option>
                        <option value="2x">2x</option>
                        <option value="3x">3x</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Status</label>
                    <select name="status" id="edit-status"
                            class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all appearance-none cursor-pointer">
                        <option value="em dia">Em dia</option>
                        <option value="pendente">Pendente</option>
                        <option value="vence em 15 dias">Vence em 15 dias</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Data de Vencimento</label>
                    <input name="data_vencimento_base" id="edit-vencimento" type="date"
                           class="w-full h-11 px-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-editar').classList.add('hidden')"
                        class="px-5 py-2.5 text-slate-600 font-semibold hover:bg-slate-100 rounded-xl text-sm transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="btn-primary">
                    <span class="material-symbols-outlined text-[16px]">save</span> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
async function abrirEdicao(id) {
    const modal   = document.getElementById('modal-editar');
    const loading = document.getElementById('edit-loading');
    const fields  = document.getElementById('edit-fields');
    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    fields.classList.add('hidden');
    try {
        const data = await fetch(`actions/get_cliente.php?id=${id}`).then(r => r.json());
        document.getElementById('edit-id').value        = data.id          ?? '';
        document.getElementById('edit-nome').value      = data.nome        ?? '';
        document.getElementById('edit-email').value     = data.email       ?? '';
        document.getElementById('edit-dominio').value   = data.dominio     ?? '';
        document.getElementById('edit-valor').value     = data.valor_anual ?? '';
        document.getElementById('edit-tipo').value      = data.tipo_pagamento ?? 'a vista';
        document.getElementById('edit-status').value    = data.status      ?? 'em dia';
        document.getElementById('edit-vencimento').value = data.data_vencimento_base ?? '';
        loading.classList.add('hidden');
        fields.classList.remove('hidden');
    } catch {
        modal.classList.add('hidden');
        alert('Erro ao carregar dados. Tente novamente.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
