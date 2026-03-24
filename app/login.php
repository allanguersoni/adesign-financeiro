<?php
/**
 * app/login.php — Página de Login
 * Layout independente — não usa header.php/sidebar
 */

require_once 'config/auth.php';

// Se já está logado, vai ao dashboard
if (is_authenticated()) {
    header('Location: /index.php');
    exit;
}

require_once 'config/conexao.php';

$csrf_token = get_csrf_token();
$flash      = get_flash();
$bye        = isset($_GET['bye']);
?>
<!DOCTYPE html>
<html class="light" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Login | ADesign Financeiro</title>
    <link rel="icon" type="image/png" href="assets/img/icons/logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: {
                    primary: "#456800",
                    "primary-container": "#99e000",
                    "surface-container-low": "#f3f4f5",
                    "surface-container-lowest": "#ffffff",
                    error: "#ba1a1a",
                    "error-container": "#ffdad6",
                    "on-error-container": "#93000a",
                }
            }}
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
        .logo-glow { filter: drop-shadow(0 0 20px rgba(153, 224, 0, 0.4)); }
        .bg-grid { background-image: radial-gradient(#456800 1px, transparent 1px); background-size: 28px 28px; }
        
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
    </style>
</head>
<body class="min-h-screen bg-[#f8f9fa] flex items-center justify-center p-4 relative overflow-hidden">

    <!-- Fundo decorativo -->
    <div class="fixed inset-0 bg-grid opacity-[0.03] pointer-events-none"></div>
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-gradient-to-b from-[#99E000]/10 to-transparent blur-[80px] rounded-full pointer-events-none"></div>

    <div class="w-full max-w-[420px] relative z-10 animate-fade-up">

        <!-- Logo ADesign -->
        <div class="flex flex-col items-center mb-8">
            <img src="assets/img/icons/logo.png" alt="Logo" class="w-[84px] h-[84px] object-contain mb-4 logo-glow hover:scale-105 transition-transform duration-300" />
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">ADesign Financeiro</h1>
            <p class="text-slate-500 text-sm mt-1">Gestão financeira para agências</p>
        </div>

        <!-- Mensagem de saída -->
        <?php if ($bye): ?>
        <div class="mb-4 flex items-center gap-2 px-4 py-3 bg-surface-container-low rounded-xl text-sm text-slate-600">
            <span class="material-symbols-outlined text-primary text-lg">check_circle</span>
            Você saiu com segurança.
        </div>
        <?php endif; ?>

        <!-- Flash error/success -->
        <?php if ($flash): ?>
        <div class="mb-4 flex items-start gap-3 px-4 py-3 rounded-xl text-sm font-medium
                    <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-error-container text-on-error-container border border-red-200' ?>">
            <span class="material-symbols-outlined text-lg shrink-0">
                <?= $flash['type'] === 'success' ? 'check_circle' : 'warning' ?>
            </span>
            <span><?= htmlspecialchars($flash['message']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Card de Login -->
        <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-[0_8px_40px_rgba(0,0,0,0.04)] p-8 border border-white/60 animate-fade-up delay-100 opacity-0">
            <h2 class="text-[19px] font-extrabold text-slate-800 mb-6 tracking-tight">Acesse sua conta</h2>

            <form action="actions/auth_login.php" method="POST" class="space-y-5" autocomplete="on">
                <!-- CSRF -->
                <?= csrf_field() ?>

                <!-- E-mail -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5" for="email">
                        E-mail corporativo
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">mail</span>
                        <input
                            class="w-full h-12 pl-10 pr-4 bg-[#f3f4f5] border border-transparent rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:bg-white focus:border-primary/30 transition-all"
                            id="email" name="email" placeholder="exemplo@empresa.com" type="email"
                            required autocomplete="email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        />
                    </div>
                </div>

                <!-- Senha -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5" for="password">
                        Senha
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">lock</span>
                        <input
                            class="w-full h-12 pl-10 pr-12 bg-[#f3f4f5] border border-transparent rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:bg-white focus:border-primary/30 transition-all"
                            id="password" name="password" type="password"
                            required autocomplete="current-password"
                        />
                        <button type="button"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary transition-colors p-1"
                                onclick="const i=document.getElementById('password');i.type=i.type==='password'?'text':'password'">
                            <span class="material-symbols-outlined text-[18px]">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Lembrar + Esqueci -->
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input class="w-4 h-4 rounded text-primary border-slate-300 focus:ring-primary" type="checkbox" name="lembrar"/>
                        <span class="text-slate-600">Lembrar de mim</span>
                    </label>
                    <a class="text-primary font-semibold hover:underline underline-offset-2" href="/esqueci_senha.php">Esqueceu a senha?</a>
                </div>

                <!-- Submit -->
                <button type="submit"
                        class="w-full h-12 mt-2 flex items-center justify-center gap-2 font-bold rounded-xl text-white text-[15px] transition-all duration-300 relative overflow-hidden group"
                        style="background:linear-gradient(135deg,#5a8000,#3a5600);box-shadow:0 4px 16px rgba(69,104,0,.25),0 2px 4px rgba(0,0,0,.1);"
                        onmouseover="this.style.boxShadow='0 8px 24px rgba(153,224,0,.35),0 4px 8px rgba(0,0,0,.15)';this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.boxShadow='0 4px 16px rgba(69,104,0,.25),0 2px 4px rgba(0,0,0,.1)';this.style.transform=''"
                        onmousedown="this.style.transform='scale(.97)'">
                    <span class="relative z-10 flex items-center gap-2">
                        Entrar no painel
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </span>
                    <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                </button>
            </form>
        </div>

        <!-- Rodapé -->
        <div class="mt-6 flex justify-center items-center gap-4">
            <span class="h-px flex-1 bg-slate-200"></span>
            <span class="text-[10px] text-slate-400 uppercase tracking-widest font-bold">Conexão Segura SSL</span>
            <span class="h-px flex-1 bg-slate-200"></span>
        </div>
        <p class="text-center text-[11px] text-slate-400 mt-4">
            © <?= date('Y') ?> ADesign Financeiro · clientes.allandesign.com.br
        </p>
    </div>

</body>
</html>
