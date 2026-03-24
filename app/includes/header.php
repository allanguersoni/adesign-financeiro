<?php
/**
 * app/includes/header.php
 * Componente compartilhado: HTML base + sidebar + navbar
 * Rebranding: ADesign Financeiro
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';

require_auth();

$csrf_token = get_csrf_token();
$flash      = get_flash();
$user       = auth_user();

$page_title = $page_title ?? 'Dashboard';
$page_atual = $page_atual ?? 'dashboard';
$header_btn = $header_btn ?? '';

function nav_class(string $page, string $current): string
{
    if ($page === $current) {
        return 'flex items-center gap-3 px-4 py-3 rounded-xl relative text-[#99E000] before:absolute before:left-0 before:top-[20%] before:h-[60%] before:w-[3px] before:bg-[#99E000] before:rounded-full before:shadow-[0_0_12px_#99E000] bg-white/[0.08] transition-all duration-300 font-extrabold shadow-[inset_0_1px_1px_rgba(255,255,255,0.05)]';
    }
    return 'flex items-center gap-3 px-4 py-3 rounded-xl text-[#8a9090] hover:text-white hover:bg-white/[0.04] transition-all duration-300 font-medium group';
}

$nav_items = [
    ['href' => 'index.php',         'key' => 'dashboard',     'icon' => 'dashboard',      'label' => 'Dashboard'],
    ['href' => 'clientes.php',      'key' => 'clientes',      'icon' => 'groups',         'label' => 'Clientes'],
    ['href' => 'cobrancas.php',     'key' => 'cobrancas',     'icon' => 'receipt_long',   'label' => 'Cobranças'],
    ['href' => 'configuracoes.php', 'key' => 'configuracoes', 'icon' => 'settings',       'label' => 'Configurações'],
];

$ini = strtoupper(substr($user['nome'], 0, 2));
?>
<!DOCTYPE html>
<html class="light" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>"/>
    <title><?= htmlspecialchars($page_title) ?> | ADesign Financeiro</title>
    <link rel="icon" type="image/png" href="assets/img/icons/logo.png" />

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary":                  "#456800",
                        "on-primary":               "#ffffff",
                        "primary-container":        "#99e000",
                        "on-primary-container":     "#3f5f00",
                        "secondary":                "#5d5e61",
                        "on-secondary":             "#ffffff",
                        "tertiary":                 "#1b6d24",
                        "on-tertiary":              "#ffffff",
                        "tertiary-container":       "#8ddf87",
                        "on-tertiary-container":    "#0e641c",
                        "error":                    "#ba1a1a",
                        "on-error":                 "#ffffff",
                        "error-container":          "#ffdad6",
                        "on-error-container":       "#93000a",
                        "background":               "#f4f5f6",
                        "on-background":            "#191c1d",
                        "surface":                  "#f8f9fa",
                        "on-surface":               "#191c1d",
                        "surface-variant":          "#e1e3e4",
                        "on-surface-variant":       "#424935",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-low":    "#f3f4f5",
                        "surface-container":        "#edeeef",
                        "surface-container-high":   "#e7e8e9",
                        "outline":                  "#727a62",
                        "outline-variant":          "#c2caae",
                        "inverse-surface":          "#2e3132",
                        "inverse-on-surface":       "#f0f1f2",
                        "inverse-primary":          "#95da00",
                        "lime":                     "#99e000",
                    },
                    fontFamily: {
                        headline: ["Inter", "sans-serif"],
                        body:     ["Inter", "sans-serif"],
                    },
                    boxShadow: {
                        'glow-lime': '0 0 16px rgba(153,224,0,0.35)',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .icon-fill { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #99e00055; border-radius: 10px; }
        body { font-family: 'Inter', sans-serif; }

        /* ── Buscador Premium ── */
        .search-wrap {
            position: relative;
            transition: all .25s;
        }
        .search-wrap input {
            width: 220px;
            height: 38px;
            padding: 0 14px 0 40px;
            border-radius: 20px;
            background: #f1f3f4;
            border: 1.5px solid transparent;
            font-size: 13px;
            color: #1e293b;
            outline: none;
            transition: all .25s cubic-bezier(.4,0,.2,1);
        }
        .search-wrap input:focus {
            width: 300px;
            background: #fff;
            border-color: #99e000;
            box-shadow: 0 0 0 4px rgba(153,224,0,.12);
        }
        .search-wrap input::placeholder { color: #94a3b8; }
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 17px !important;
            pointer-events: none;
            transition: color .2s;
        }
        .search-wrap input:focus ~ .search-icon,
        .search-wrap:focus-within .search-icon { color: #456800; }

        /* ── Toggle Switch Premium ── */
        .toggle-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        .toggle-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
        .toggle-track {
            width: 44px; height: 24px;
            background: #d1d5db;
            border-radius: 999px;
            transition: background .25s cubic-bezier(.4,0,.2,1);
            display: flex;
            align-items: center;
            padding: 0 3px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,.12);
        }
        .toggle-thumb {
            width: 18px; height: 18px;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.22);
            transition: transform .25s cubic-bezier(.4,0,.2,1);
            flex-shrink: 0;
        }
        .toggle-switch input:checked + .toggle-track {
            background: linear-gradient(135deg, #99e000, #5aac00);
            box-shadow: 0 0 10px rgba(153,224,0,.35), inset 0 1px 3px rgba(0,0,0,.06);
        }
        .toggle-switch input:checked + .toggle-track .toggle-thumb {
            transform: translateX(20px);
        }
        .toggle-switch input:focus + .toggle-track {
            outline: 3px solid rgba(153,224,0,.3);
            outline-offset: 2px;
        }

        /* ── Botões Base ── */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0 20px;
            height: 40px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            color: #fff;
            background: linear-gradient(135deg, #5a8000 0%, #3a5600 100%);
            box-shadow: 0 4px 16px rgba(69,104,0,.25), 0 2px 4px rgba(0,0,0,.1);
            transition: all .3s cubic-bezier(.4,0,.2,1);
            cursor: pointer;
            border: none;
            position: relative;
            overflow: hidden;
        }
        .btn-primary::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.15);
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(.4,0,.2,1);
        }
        .btn-primary:hover::after { transform: translateY(0); }
        .btn-primary:hover {
            box-shadow: 0 8px 24px rgba(153,224,0,.35), 0 4px 8px rgba(0,0,0,.15);
            transform: translateY(-2px);
        }
        .btn-primary:active { transform: scale(.97); }
        .btn-primary > * { position: relative; z-index: 10; }

        .btn-danger {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0 16px;
            height: 36px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 12px;
            color: #df3030;
            background: #fef2f2;
            border: 1.5px solid #fca5a5;
            transition: all .2s;
            cursor: pointer;
        }
        .btn-danger:hover {
            background: #fee2e2;
            border-color: #f87171;
            color: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 9px;
            transition: all .18s;
            cursor: pointer;
            background: transparent;
            border: none;
        }
        .btn-icon:hover { transform: scale(1.08); }
        .btn-icon-edit  { color: #64748b; }
        .btn-icon-edit:hover  { background: #f0fdf4; color: #456800; }
        .btn-icon-del   { color: #94a3b8; }
        .btn-icon-del:hover   { background: #fef2f2; color: #ba1a1a; }

        /* Notif badge */
        .notif-badge {
            position: absolute;
            top: 7px; right: 7px;
            width: 8px; height: 8px;
            background: #ef4444;
            border-radius: 999px;
            border: 2px solid #fff;
        }
    </style>
</head>
<body class="bg-[#f4f5f6] text-on-background antialiased">

<!-- ─────────────────── SIDEBAR ─────────────── -->
<aside class="h-screen w-64 fixed left-0 top-0 z-50 flex-col pt-6 pb-4 select-none hidden md:flex"
       style="background: linear-gradient(180deg, #252a2b 0%, #1c2122 60%, #171c1d 100%);
              border-right: 1px solid rgba(153,224,0,.08)">

    <!-- Logo ADesign -->
    <div class="px-6 mb-8 mt-2 flex items-center gap-3">
        <img src="assets/img/icons/logo.png" alt="Logo" class="w-11 h-11 object-contain drop-shadow-[0_2px_8px_rgba(153,224,0,0.15)] hover:scale-105 transition-transform" />
        <div>
            <p class="text-white text-[15px] font-extrabold leading-none tracking-tight">ADesign</p>
            <p style="color:#99e000;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.12em">Financeiro</p>
        </div>
    </div>

    <!-- Navegação -->
    <nav class="flex-1 px-3 space-y-0.5 overflow-y-auto">
        <p class="text-[10px] font-bold uppercase tracking-widest text-[#4b5563] px-4 mb-2">Menu</p>
        <?php foreach ($nav_items as $item): ?>
        <?php $isActive = ($item['key'] === $page_atual); ?>
        <a href="<?= $item['href'] ?>" class="<?= nav_class($item['key'], $page_atual) ?>">
            <span class="material-symbols-outlined <?= $isActive ? 'text-[#99E000] drop-shadow-[0_0_12px_rgba(153,224,0,0.6)] scale-110 icon-fill' : 'text-slate-400 group-hover:text-[#99E000] group-hover:scale-110 group-hover:drop-shadow-[0_0_8px_rgba(153,224,0,0.3)]' ?> transition-all duration-300 text-[23px]"><?= $item['icon'] ?></span>
            <span class="text-[13px] tracking-wide"><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Usuário + Logout -->
    <div class="px-3 mt-4 border-t pt-4" style="border-color:rgba(255,255,255,.07)">
        <div class="flex items-center gap-3 px-3 py-2 mb-1">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center font-black text-[12px] shrink-0"
                 style="background:linear-gradient(135deg,#99e000,#456800);color:#1e3300">
                <?= $ini ?>
            </div>
            <div class="min-w-0">
                <p class="text-white text-[12px] font-semibold truncate"><?= htmlspecialchars($user['nome']) ?></p>
                <p class="text-[#4b5563] text-[10px] truncate"><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>
        <a href="actions/auth_logout.php"
           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[#6b7280] hover:text-red-400 hover:bg-red-500/10 transition-all text-[12px] mt-0.5"
           onclick="return confirm('Sair do sistema?')">
            <span class="material-symbols-outlined text-[19px]">logout</span>
            <span>Sair do sistema</span>
        </a>
    </div>
</aside>

<!-- ─────────────────── NAVBAR SUPERIOR ─────────────────── -->
<header class="fixed top-0 right-0 md:left-64 left-0 h-16 z-40 flex items-center px-4 md:px-8 gap-3 md:gap-5"
        style="background:rgba(255,255,255,.85);
               backdrop-filter:blur(20px);
               border-bottom:1px solid rgba(0,0,0,.06);
               box-shadow:0 1px 12px rgba(0,0,0,.05)">

    <!-- Título -->
    <div class="flex-1">
        <h1 class="text-[16px] font-extrabold tracking-tight text-slate-900 leading-none">
            <?= htmlspecialchars($page_title) ?>
        </h1>
    </div>

    <!-- ── Buscador Premium ── -->
    <div class="search-wrap hidden md:block">
        <input type="search" id="global-search" placeholder="Buscar clientes..."
               autocomplete="off" spellcheck="false"/>
        <span class="material-symbols-outlined search-icon">search</span>
    </div>

    <!-- Botão de ação (injetado pela página) -->
    <?php if (!empty($header_btn)): ?>
        <?= $header_btn ?>
    <?php endif; ?>

    <!-- Notificação -->
    <?php
    // Busca notificações reais do banco (clientes pendentes ou a vencer)
    $notifs_db = $pdo->query(
        "SELECT nome, status, data_vencimento_base FROM clientes
         WHERE status IN ('pendente','vence em 15 dias')
         ORDER BY FIELD(status,'pendente','vence em 15 dias'), data_vencimento_base ASC
         LIMIT 10"
    )->fetchAll();
    $n_count = count($notifs_db);
    ?>
    <div class="relative" id="notif-wrapper">
        <button id="btn-notif"
                onclick="document.getElementById('notif-panel').classList.toggle('hidden')"
                class="relative p-2 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-all"
                aria-label="Notificações">
            <span class="material-symbols-outlined text-[22px]">notifications</span>
            <?php if ($n_count > 0): ?>
            <span class="absolute top-1.5 right-1.5 min-w-[16px] h-4 px-1 bg-red-500 text-white text-[9px] font-black rounded-full flex items-center justify-center border-2 border-white">
                <?= $n_count > 9 ? '9+' : $n_count ?>
            </span>
            <?php endif; ?>
        </button>

        <!-- Dropdown -->
        <div id="notif-panel"
             class="hidden absolute right-0 top-[calc(100%+10px)] w-80 bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden z-[200]"
             style="box-shadow:0 8px 40px rgba(0,0,0,.12),0 2px 8px rgba(0,0,0,.06)">

            <!-- Header do painel -->
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-primary icon-fill">notifications_active</span>
                    <h4 class="font-bold text-slate-900 text-sm">Notificações</h4>
                    <?php if ($n_count > 0): ?>
                    <span class="bg-red-100 text-red-600 text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $n_count ?></span>
                    <?php endif; ?>
                </div>
                <a href="cobrancas.php" class="text-[11px] text-primary font-bold hover:underline">Ver cobranças →</a>
            </div>

            <!-- Lista -->
            <div class="max-h-72 overflow-y-auto divide-y divide-slate-50">
                <?php if ($n_count === 0): ?>
                <div class="py-10 text-center">
                    <span class="material-symbols-outlined text-4xl text-slate-200 block mb-2 icon-fill">check_circle</span>
                    <p class="text-sm font-medium text-slate-400">Tudo em dia! 🎉</p>
                    <p class="text-xs text-slate-300 mt-1">Nenhum cliente pendente</p>
                </div>
                <?php else: ?>
                <?php foreach ($notifs_db as $n):
                    $is_pendente = $n['status'] === 'pendente';
                    $icon  = $is_pendente ? 'warning'       : 'event_upcoming';
                    $color = $is_pendente ? 'text-red-500'  : 'text-amber-500';
                    $bg    = $is_pendente ? 'bg-red-50'     : 'bg-amber-50';
                    $label = $is_pendente ? 'Pendente'       : 'Vence em 15 dias';
                    $venc  = $n['data_vencimento_base']
                        ? date('d/m/Y', strtotime($n['data_vencimento_base']))
                        : 'sem data';
                ?>
                <div class="flex items-start gap-3 px-5 py-3.5 hover:bg-slate-50 transition-colors">
                    <div class="p-1.5 <?= $bg ?> rounded-lg shrink-0 mt-0.5">
                        <span class="material-symbols-outlined <?= $color ?> text-[16px] icon-fill"><?= $icon ?></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($n['nome']) ?></p>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            <span class="font-bold <?= $color ?>"><?= $label ?></span>
                            · venc. <strong><?= $venc ?></strong>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <?php if ($n_count > 0): ?>
            <div class="px-5 py-3 border-t border-slate-100 bg-slate-50">
                <a href="cobrancas.php?status=pendente" class="w-full btn-primary flex justify-center text-center text-[12px] h-9">
                    <span class="material-symbols-outlined text-[15px]">send</span>
                    Enviar cobranças pendentes
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Fecha o painel de notificação ao clicar fora
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('notif-wrapper');
        const panel   = document.getElementById('notif-panel');
        if (wrapper && panel && !wrapper.contains(e.target)) {
            panel.classList.add('hidden');
        }
    });
    // Busca global no header redireciona para clientes.php?q=
    const gs = document.getElementById('global-search');
    if (gs) {
        gs.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && this.value.trim()) {
                window.location.href = 'clientes.php?q=' + encodeURIComponent(this.value.trim());
            }
        });
    }
    </script>

</header>

<!-- ─────────────────── FLASH NOTIFICATION ─────────────────── -->
<?php if ($flash): ?>
<div id="flash-notification"
     class="fixed top-20 right-6 z-[100] max-w-sm flex items-center gap-3 px-5 py-3.5 rounded-2xl text-sm font-semibold
            shadow-2xl transition-all duration-300
            <?= $flash['type'] === 'success' ? 'bg-[#1e3d00] text-[#99e000]' : 'bg-[#3d0000] text-red-300' ?>"
     style="box-shadow:0 8px 32px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.12)">
    <span class="material-symbols-outlined text-xl icon-fill opacity-90">
        <?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?>
    </span>
    <span class="flex-1 leading-snug"><?= htmlspecialchars($flash['message']) ?></span>
    <button onclick="this.parentElement.remove()" class="ml-1 opacity-50 hover:opacity-100 transition-opacity">
        <span class="material-symbols-outlined text-[17px]">close</span>
    </button>
</div>
<script>
    setTimeout(() => {
        const el = document.getElementById('flash-notification');
        if (el) {
            el.style.opacity = '0';
            el.style.transform = 'translateX(16px)';
            setTimeout(() => el?.remove(), 400);
        }
    }, 5000);
</script>
<?php endif; ?>

<!-- ─────────────────── MAIN ─────────────────── -->
<main class="md:ml-64 ml-0 min-h-screen flex flex-col bg-[#f4f5f6] pb-20 md:pb-0">
    <div class="mt-16 p-4 md:p-8 flex-1">

<!-- ─────────────────── MOBILE BOTTOM NAV ─────────────────── -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 flex items-center justify-around h-16 z-50 shadow-[0_-4px_24px_rgba(0,0,0,0.04)] px-2">
    <?php foreach ($nav_items as $item): ?>
    <?php $isActive = ($item['key'] === $page_atual); ?>
    <a href="<?= $item['href'] ?>" class="flex flex-col items-center justify-center w-full h-full relative <?= $isActive ? 'text-[#456800]' : 'text-slate-400 hover:text-slate-600' ?>">
        <span class="material-symbols-outlined text-[24px] <?= $isActive ? 'icon-fill drop-shadow-[0_2px_8px_rgba(153,224,0,0.4)]' : '' ?>"><?= $item['icon'] ?></span>
        <span class="text-[10px] font-bold mt-0.5"><?= $item['label'] ?></span>
        <?php if ($isActive): ?>
        <div class="absolute top-0 w-8 h-[3px] bg-[#99E000] rounded-b-md shadow-[0_2px_8px_#99e000]"></div>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</nav>
