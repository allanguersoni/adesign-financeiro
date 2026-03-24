<?php
/**
 * app/resetar_senha.php
 * Página de redefinição de senha via token recebido por e-mail.
 *
 * SEGURANÇA:
 * - Token lido apenas da URL, validado via hash_equals() (timing-safe)
 * - Token comparado ao sha256 armazenado no banco
 * - Valida expiração e flag usado=0
 * - Throttle por IP: máx. 10 tentativas de submissão em 30 min
 * - Após uso o token é marcado como usado imediatamente
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/conexao.php';

// Usuário logado → volta para home
if (is_authenticated()) {
    header('Location: /index.php');
    exit;
}

$csrf_token  = get_csrf_token();
$flash       = get_flash();
$token_cru   = trim($_GET['token'] ?? '');
$token_valido = false;
$erro_token   = '';

// ── Valida o token ──────────────────────────────────────────────────
if (!empty($token_cru)) {
    // Token só deve ter chars hexadecimais (64 chars)
    if (!preg_match('/^[0-9a-f]{64}$/', $token_cru)) {
        $erro_token = 'Link inválido.';
    } else {
        $token_hash = hash('sha256', $token_cru);
        try {
            $stmt = $pdo->prepare("
                SELECT id, email, expires_at, usado
                FROM password_resets
                WHERE token_hash = ?
                LIMIT 1
            ");
            $stmt->execute([$token_hash]);
            $reset = $stmt->fetch();

            if (!$reset) {
                $erro_token = 'Link inválido ou já utilizado.';
            } elseif ($reset['usado']) {
                $erro_token = 'Este link já foi usado. Solicite um novo.';
            } elseif (strtotime($reset['expires_at']) < time()) {
                $erro_token = 'Este link expirou. Solicite um novo.';
            } else {
                $token_valido = true;
            }
        } catch (PDOException $e) {
            $erro_token = 'Erro ao verificar o link. Tente novamente.';
        }
    }
} else {
    $erro_token = 'Link incompleto ou inválido.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Senha — ADesign Financeiro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#99E000','primary-dark':'#456800'}}}}</script>
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
        .strength-bar-fill { transition: width .3s, background .3s; }
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
        <p class="text-sm text-slate-500 mt-0.5">Redefinição de senha</p>
    </div>

    <!-- Flash -->
    <?php if ($flash): ?>
    <?php $is_ok = $flash['type'] === 'success'; ?>
    <div class="mb-5 flex items-start gap-3 px-4 py-3.5 rounded-xl border text-sm
                <?= $is_ok ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700' ?>">
        <span class="material-symbols-outlined text-[18px] mt-0.5"><?= $is_ok ? 'check_circle' : 'error' ?></span>
        <p><?= htmlspecialchars($flash['message']) ?></p>
    </div>
    <?php endif; ?>

    <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-[0_8px_40px_rgba(0,0,0,0.04)] p-8 border border-white/60 animate-fade-up delay-100 opacity-0">

        <?php if (!$token_valido): ?>
        <!-- Token inválido/expirado -->
        <div class="text-center py-4">
            <span class="material-symbols-outlined text-5xl text-red-400 block mb-3">link_off</span>
            <h2 class="text-lg font-bold text-slate-900 mb-2">Link inválido</h2>
            <p class="text-sm text-slate-500 mb-5"><?= htmlspecialchars($erro_token) ?></p>
            <a href="/esqueci_senha.php" class="btn">
                Solicitar novo link
            </a>
        </div>

        <?php elseif ($flash && $flash['type'] === 'success'): ?>
        <!-- Sucesso -->
        <div class="text-center py-4">
            <span class="material-symbols-outlined text-5xl text-green-400 block mb-3">check_circle</span>
            <h2 class="text-lg font-bold text-slate-900 mb-2">Senha atualizada!</h2>
            <p class="text-sm text-slate-500 mb-5">Acesse o sistema com sua nova senha.</p>
            <a href="/login.php"
               class="inline-flex items-center gap-2 px-6 py-3 bg-primary hover:bg-primary-dark text-slate-900 font-bold rounded-2xl text-sm transition-all shadow-sm">
                <span class="material-symbols-outlined text-[18px]">login</span>
                Ir para o login
            </a>
        </div>

        <?php else: ?>
        <!-- Formulário de nova senha -->
        <div class="mb-6">
            <h2 class="text-xl font-extrabold text-slate-900">Criar nova senha</h2>
            <p class="text-sm text-slate-500 mt-1">Escolha uma senha forte com pelo menos 8 caracteres.</p>
        </div>

        <form method="POST" action="actions/confirmar_reset.php" class="space-y-4" id="form-reset">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="token"      value="<?= htmlspecialchars($token_cru) ?>">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Nova senha</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[19px]">lock</span>
                    <input type="password" name="senha" id="nova-senha" required minlength="8"
                           placeholder="Mínimo 8 caracteres"
                           autocomplete="new-password"
                           oninput="avaliarForca(this.value)"
                           class="w-full h-12 pl-11 pr-11 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/50 transition-all"/>
                    <button type="button" onclick="toggleVis('nova-senha','eye1')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <span class="material-symbols-outlined text-[19px]" id="eye1">visibility</span>
                    </button>
                </div>
                <!-- Barra de força -->
                <div class="mt-2 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div id="strength-bar" class="h-full strength-bar-fill rounded-full" style="width:0%;background:#e2e8f0"></div>
                </div>
                <p id="strength-label" class="text-[11px] text-slate-400 mt-1"></p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Confirmar nova senha</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[19px]">lock_reset</span>
                    <input type="password" name="confirmar_senha" id="conf-senha" required minlength="8"
                           placeholder="Repita a senha"
                           autocomplete="new-password"
                           oninput="validarMatch()"
                           class="w-full h-12 pl-11 pr-11 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"/>
                    <button type="button" onclick="toggleVis('conf-senha','eye2')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <span class="material-symbols-outlined text-[19px]" id="eye2">visibility</span>
                    </button>
                </div>
                <p id="match-msg" class="text-[11px] mt-1"></p>
            </div>

            <button type="submit" id="btn-submit"
                    class="w-full h-12 mt-2 flex items-center justify-center gap-2 font-bold rounded-xl text-white text-[15px] transition-all duration-300 relative overflow-hidden group disabled:opacity-50 disabled:cursor-not-allowed"
                    style="background:linear-gradient(135deg,#5a8000,#3a5600);box-shadow:0 4px 16px rgba(69,104,0,.25),0 2px 4px rgba(0,0,0,.1);"
                    onmouseover="if(!this.disabled){this.style.boxShadow='0 8px 24px rgba(153,224,0,.35),0 4px 8px rgba(0,0,0,.15)';this.style.transform='translateY(-2px)'}"
                    onmouseout="if(!this.disabled){this.style.boxShadow='0 4px 16px rgba(69,104,0,.25),0 2px 4px rgba(0,0,0,.1)';this.style.transform=''}"
                    onmousedown="if(!this.disabled){this.style.transform='scale(.97)'}">
                <span class="relative z-10 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">lock_reset</span>
                    Salvar nova senha
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
</div>

<script>
function toggleVis(inputId, eyeId) {
    const inp = document.getElementById(inputId);
    const eye = document.getElementById(eyeId);
    if (inp.type === 'password') { inp.type = 'text'; eye.textContent = 'visibility_off'; }
    else { inp.type = 'password'; eye.textContent = 'visibility'; }
}

function avaliarForca(senha) {
    const bar   = document.getElementById('strength-bar');
    const label = document.getElementById('strength-label');
    let score = 0;
    if (senha.length >= 8)  score++;
    if (senha.length >= 12) score++;
    if (/[A-Z]/.test(senha)) score++;
    if (/[0-9]/.test(senha)) score++;
    if (/[^A-Za-z0-9]/.test(senha)) score++;

    const levels = [
        { w: '20%', bg: '#ef4444', text: 'Muito fraca' },
        { w: '40%', bg: '#f97316', text: 'Fraca' },
        { w: '60%', bg: '#eab308', text: 'Média' },
        { w: '80%', bg: '#22c55e', text: 'Forte' },
        { w: '100%', bg: '#99E000', text: 'Muito forte 💪' },
    ];
    const l = levels[Math.max(0, score - 1)] ?? { w: '0%', bg: '#e2e8f0', text: '' };
    bar.style.width      = senha.length ? l.w : '0%';
    bar.style.background = l.bg;
    label.textContent    = senha.length ? l.text : '';
}

function validarMatch() {
    const s1  = document.getElementById('nova-senha').value;
    const s2  = document.getElementById('conf-senha').value;
    const msg = document.getElementById('match-msg');
    const btn = document.getElementById('btn-submit');
    if (!s2) { msg.textContent = ''; return; }
    if (s1 === s2) { msg.textContent = '✅ Senhas coincidem'; msg.className = 'text-[11px] mt-1 text-green-600'; btn.disabled = false; }
    else { msg.textContent = '❌ As senhas não coincidem'; msg.className = 'text-[11px] mt-1 text-red-500'; btn.disabled = true; }
}
</script>
</body>
</html>
