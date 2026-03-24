<?php
/**
 * app/esqueci_senha.php
 * Formulário de recuperação de senha — sem vazar informação de user enumeration.
 */

require_once __DIR__ . '/config/auth.php';

// Usuário já logado → redireciona
if (is_authenticated()) {
    header('Location: /index.php');
    exit;
}

$csrf_token = get_csrf_token();
$flash      = get_flash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha — ADesign Financeiro</title>
    <link rel="icon" type="image/png" href="assets/img/icons/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { primary: '#99E000', 'primary-dark': '#456800' } } }
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .logo-glow { filter: drop-shadow(0 0 20px rgba(153, 224, 0, 0.4)); }
        .bg-grid { background-image: radial-gradient(#456800 1px, transparent 1px); background-size: 28px 28px; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .delay-100 { animation-delay: 100ms; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-10 relative overflow-hidden">
    
    <div class="fixed inset-0 bg-grid opacity-[0.03] pointer-events-none"></div>
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-gradient-to-b from-[#99E000]/10 to-transparent blur-[80px] rounded-full pointer-events-none"></div>

    <div class="w-full max-w-[420px] relative z-10 animate-fade-up">

        <!-- Logo -->
        <div class="flex flex-col items-center mb-8">
            <img src="assets/img/icons/logo.png" alt="Logo" class="w-[84px] h-[84px] object-contain mb-4 logo-glow hover:scale-105 transition-transform duration-300" />
            <h1 class="text-xl font-extrabold text-slate-900">ADesign Financeiro</h1>
            <p class="text-sm text-slate-500 mt-0.5">Recuperação de acesso</p>
        </div>

        <!-- Flash messages -->
        <?php if ($flash): ?>
        <?php
            $is_ok  = $flash['type'] === 'success';
            $bg     = $is_ok ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700';
            $icon   = $is_ok ? 'check_circle' : 'error';
        ?>
        <div class="mb-5 flex items-start gap-3 px-4 py-3.5 rounded-xl border text-sm <?= $bg ?>">
            <span class="material-symbols-outlined text-[18px] mt-0.5"><?= $icon ?></span>
            <p><?= htmlspecialchars($flash['message']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Card -->
        <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-[0_8px_40px_rgba(0,0,0,0.04)] p-8 border border-white/60 animate-fade-up delay-100 opacity-0">

            <?php if (!($flash && $flash['type'] === 'success')): ?>
            <div class="mb-6">
                <h2 class="text-xl font-extrabold text-slate-900">Esqueceu a senha?</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Informe seu e-mail cadastrado. Se existir uma conta, enviaremos as instruções de recuperação.
                </p>
            </div>

            <form method="POST" action="actions/solicitar_reset.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">E-mail cadastrado</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[19px]">mail</span>
                        <input type="email" name="email" required autocomplete="email"
                               placeholder="seu@email.com"
                               class="w-full h-12 pl-11 pr-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/50 transition-all"/>
                    </div>
                </div>

                <button type="submit"
                        class="w-full h-12 mt-2 flex items-center justify-center gap-2 font-bold rounded-xl text-white text-[15px] transition-all duration-300 relative overflow-hidden group"
                        style="background:linear-gradient(135deg,#5a8000,#3a5600);box-shadow:0 4px 16px rgba(69,104,0,.25),0 2px 4px rgba(0,0,0,.1);"
                        onmouseover="this.style.boxShadow='0 8px 24px rgba(153,224,0,.35),0 4px 8px rgba(0,0,0,.15)';this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.boxShadow='0 4px 16px rgba(69,104,0,.25),0 2px 4px rgba(0,0,0,.1)';this.style.transform=''"
                        onmousedown="this.style.transform='scale(.97)'">
                    <span class="relative z-10 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">send</span>
                        Enviar instruções
                    </span>
                    <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-5 text-center">
                <a href="/login.php" class="text-sm text-primary font-bold hover:underline flex items-center justify-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    Voltar ao login
                </a>
            </div>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            Por segurança, o link expira em <strong>30 minutos</strong>.
        </p>
    </div>
</body>
</html>
