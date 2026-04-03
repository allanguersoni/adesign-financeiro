<?php
/**
 * app/pix.php
 * ==========================
 * Página pública de pagamento via PIX.
 * Compatível com Modo Simples e Avançado.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/pix_simples.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die('Token de acesso inválido ou não informado.');
}

// Busca o pagamento e os dados do cliente
$stmt = $pdo->prepare("
    SELECT p.*, c.nome, c.dominio
    FROM pagamentos p
    JOIN clientes c ON p.cliente_id = c.id
    WHERE p.pix_token = ?
");
$stmt->execute([$token]);
$pagamento = $stmt->fetch();

if (!$pagamento) {
    die('Cobrança não encontrada ou token inválido.');
}

// Busca configurações globais do PIX
$stmt_conf = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('pix_modo', 'pix_chave', 'pix_beneficiario', 'pix_cidade')");
$configs = $stmt_conf->fetchAll(PDO::FETCH_KEY_PAIR);
$pix_modo = $configs['pix_modo'] ?? 'simples';

$payload = $pagamento['pix_qr_code'] ?? '';
$qrcode_src = '';
$foi_pago = ($pagamento['status'] === 'pago');
$pago_em = $foi_pago ? date('d/m/Y H:i', strtotime($pagamento['pago_em'])) : '';
$erro_geracao = false;

if (!$foi_pago) {
    // Se o QR Code/Payload não foi gerado/salvo e estamos no modo simples, gera agora on-the-fly.
    if (empty($payload)) {
        if ($pix_modo === 'simples') {
            $dados = [
                'chave' => $configs['pix_chave'] ?? '',
                'beneficiario' => $configs['pix_beneficiario'] ?? '',
                'cidade' => $configs['pix_cidade'] ?? 'Sao Paulo',
                'valor' => $pagamento['valor'],
                'txid' => 'FAT' . str_pad($pagamento['id'], 6, '0', STR_PAD_LEFT),
                'descricao' => 'Fatura ' . $pagamento['id'] . ' ' . substr($pagamento['dominio'], 0, 20)
            ];
            
            $resultado = gerar_pix_estatico($dados);
            $payload = $resultado['payload'];
            $qrcode_src = $resultado['qrcode_base64'];
            
            // Grava para evitar recálculo
            $stmt_upd = $pdo->prepare("UPDATE pagamentos SET pix_qr_code = ?, pix_txid = ? WHERE id = ?");
            $stmt_upd->execute([$payload, $dados['txid'], $pagamento['id']]);
        } else {
            // Modo avançado: a Action de geração deveria ter populado o payload. Se não, deu erro com a Efí.
            $erro_geracao = true;
        }
    } else {
        // Já existe um payload salvo
        if (str_starts_with($payload, 'http')) {
            $qrcode_src = $payload; // URL de imagem direta retornada pela API (ex: log Efí)
        } elseif (preg_match('/^data:image/', $payload)) {
            $qrcode_src = $payload; // Base64 direto (se for alterado futuramente)
        } else {
            // Payload padrão BR Code: Gera a imagem via PIX public API (no-dependencies cache)
            $qrcode_src = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=1&data=" . urlencode($payload);
        }
    }
}

$expira_em_timestamp = !empty($pagamento['pix_expira_em']) ? strtotime($pagamento['pix_expira_em']) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX — <?= htmlspecialchars($pagamento['dominio']) ?></title>
    <!-- TailwindCDN + Icones -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <!-- Toastify para Notificações -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { vertical-align: middle; }
        .glass-card { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        .success-card { background: rgba(74, 222, 128, 0.1); border: 1px solid rgba(74, 222, 128, 0.2); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-[#0f172a] text-slate-200 relative overflow-hidden">
    
    <!-- Efeitos Visuais (Glassmorphism blobs) -->
    <div class="fixed top-[-15%] left-[-10%] w-[500px] h-[500px] bg-[#4ade80] rounded-full mix-blend-screen filter blur-[150px] opacity-10 pointer-events-none"></div>
    <div class="fixed bottom-[-15%] right-[-10%] w-[500px] h-[500px] bg-[#3b82f6] rounded-full mix-blend-screen filter blur-[150px] opacity-10 pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        
        <!-- Header minimalista -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-white/10 rounded-2xl mx-auto mb-4 flex items-center justify-center shadow-lg border border-white/5">
                <span class="material-symbols-outlined text-3xl text-[#4ade80]" style="font-variation-settings:'FILL' 1">pix</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Pagamento Rápido</h1>
            <p class="text-sm text-slate-400 mt-1">Fatura referente a <span class="text-slate-300 font-semibold"><?= htmlspecialchars($pagamento['dominio']) ?></span></p>
        </div>

        <div class="glass-card rounded-3xl p-8 border border-white/10 shadow-2xl relative overflow-hidden">
            
            <?php if ($foi_pago): ?>
                <!-- Estado Pago -->
                <div class="text-center py-6">
                    <div class="w-24 h-24 success-card rounded-full mx-auto flex items-center justify-center mb-6">
                        <span class="material-symbols-outlined text-6xl text-[#4ade80]" style="font-variation-settings:'FILL' 1">check_circle</span>
                    </div>
                    <h2 class="text-xl font-bold text-white mb-2">Fatura Paga!</h2>
                    <p class="text-sm text-slate-400">Obrigado! O pagamento foi confirmado em <br><strong class="text-white"><?= $pago_em ?></strong>.</p>
                </div>

            <?php elseif ($erro_geracao): ?>
                <!-- Erro na geração (Modo Avançado/Efí) -->
                <div class="text-center py-6">
                    <span class="material-symbols-outlined text-5xl text-amber-500 mb-4" style="font-variation-settings:'FILL' 1">error</span>
                    <h2 class="text-lg font-bold text-white">QR Code Indisponível</h2>
                    <p class="text-sm text-slate-400 mt-2">A fatura está pendente, mas houve um problema na comunicação com o banco ao gerar o PIX.<br>Tente novamente mais tarde ou atualize a página.</p>
                </div>

            <?php else: ?>
                <!-- Estado Pendente (Aguardando Pagamento) -->
                <div class="text-center mb-6">
                    <p class="text-sm text-slate-400 mb-1">Valor a pagar</p>
                    <p class="text-4xl font-black text-white px-4 py-2 bg-black/20 rounded-2xl inline-block border border-white/5 shadow-inner">
                        <span class="text-[#4ade80] text-2xl align-super mr-1">R$</span><?= number_format($pagamento['valor'], 2, ',', '.') ?>
                    </p>
                    <p class="text-xs text-slate-400 mt-3 font-medium">Olá, <?= htmlspecialchars($pagamento['nome']) ?>! Escaneie o QR Code abaixo pelo app do seu banco para finalizar.</p>
                </div>

                <div class="bg-white p-3 rounded-2xl mx-auto w-[240px] h-[240px] shadow-xl relative mt-4 mb-8">
                    <img src="<?= $qrcode_src ?>" alt="QR Code PIX" class="w-full h-full object-contain rounded-xl opacity-90 transition-opacity duration-300 hover:opacity-100">
                    <div class="absolute inset-0 border-4 border-black/5 rounded-2xl pointer-events-none"></div>
                </div>

                <!-- Timer de Expiração -->
                <?php if ($expira_em_timestamp > 0): ?>
                <div class="flex items-center justify-center gap-2 text-sm text-slate-400 font-medium bg-black/20 border border-white/5 py-2.5 rounded-full mb-6 w-full max-w-[240px] mx-auto" id="timerBadge">
                    <span class="material-symbols-outlined text-lg">timer</span>
                    Expira em: <span id="timerText" class="text-white font-bold w-12 text-left">--:--</span>
                </div>
                <?php endif; ?>

                <div class="w-full">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest pl-1 mb-2 block">PIX Copia e Cola / Pix Payload</label>
                    <div class="flex items-stretch gap-2">
                        <input type="text" id="pixCode" readonly value="<?= htmlspecialchars($payload) ?>" 
                            class="w-full bg-black/40 border border-white/10 text-slate-300 text-sm font-mono rounded-xl px-4 py-3 outline-none focus:border-[#4ade80]/50 transition-colors shadow-inner"
                            onclick="this.select();">
                        <button onclick="copiarPix()" class="bg-[#4ade80] hover:bg-[#22c55e] text-[#022c22] font-bold px-4 rounded-xl flex items-center justify-center gap-2 transition-all transform active:scale-95 shrink-0 focus:outline-none focus:ring-2 focus:ring-[#4ade80]/50 shadow-lg">
                            <span class="material-symbols-outlined text-[20px]">content_copy</span>
                        </button>
                    </div>
                </div>

                <!-- Seção Compartilhar -->
                <div class="mt-8 border-t border-white/5 pt-6">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-widest text-center mb-4">Compartilhar link de pagamento</p>
                    
                    <?php 
                    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                    $encoded_url = urlencode($current_url);
                    ?>
                    
                    <div class="grid grid-cols-1 gap-3">
                        <button onclick="copiarLinkSite(this)" class="w-full flex items-center justify-center gap-2 py-3 rounded-xl border border-[#4ade80]/50 text-[#4ade80] font-bold text-sm bg-transparent hover:bg-[#4ade80]/10 transition-colors">
                            <span class="material-symbols-outlined text-[18px]">link</span>
                            <span>Copiar Link</span>
                        </button>
                        
                        <a href="https://wa.me/?text=Ol%C3%A1!+Segue+seu+link+de+pagamento:+<?= $encoded_url ?>" target="_blank" class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-white font-bold text-sm hover:opacity-90 transition-opacity" style="background-color: #25D366;">
                            <span class="material-symbols-outlined text-[18px]">chat</span>
                            <span>Compartilhar no WhatsApp</span>
                        </a>
                        
                        <a href="mailto:?subject=Link+de+pagamento&body=<?= $encoded_url ?>" class="w-full flex items-center justify-center gap-2 py-3 rounded-xl border border-white/10 text-slate-300 font-bold text-sm bg-slate-800/50 hover:bg-slate-700/50 transition-colors">
                            <span class="material-symbols-outlined text-[18px]">mail</span>
                            <span>Enviar por E-mail</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        
        <div class="text-center mt-8 text-xs text-slate-500 font-medium">
            <p>Gerado de forma segura.</p>
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($configs['pix_beneficiario'] ?? 'ADesign') ?></p>
        </div>
    </div>

    <!-- Script de Interação -->
    <script>
        function copiarPix() {
            var input = document.getElementById("pixCode");
            input.select();
            input.setSelectionRange(0, 99999);
            
            navigator.clipboard.writeText(input.value).then(() => {
                Toastify({
                    text: "PIX Copiado! Agora abra o app do seu banco.",
                    duration: 3000,
                    gravity: "top", 
                    position: "center", 
                    style: {
                        background: "#059669",
                        color: "white",
                        borderRadius: "12px",
                        boxShadow: "0 10px 15px -3px rgba(0,0,0,0.3)"
                    }
                }).showToast();
            }).catch(err => {
                alert('Erro ao copiar o código. Por favor, tente selecionar e copiar manualmente.');
            });
        }

        function copiarLinkSite(btn) {
            navigator.clipboard.writeText(window.location.href).then(() => {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check</span><span>✅ Copiado!</span>';
                setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
            });
        }

        // Timer de Expiração
        <?php if (!$foi_pago && !$erro_geracao && $expira_em_timestamp > 0): ?>
        const expiraEm = <?= $expira_em_timestamp * 1000 ?>;
        
        function updateTimer() {
            const agora = new Date().getTime();
            const diferenca = expiraEm - agora;

            if (diferenca <= 0) {
                document.getElementById('timerText').innerHTML = "Expirado";
                document.getElementById('timerText').classList.replace('text-white', 'text-red-400');
                document.getElementById('timerBadge').classList.replace('border-white/5', 'border-red-400/30');
            } else {
                const minutos = Math.floor((diferenca % (1000 * 60 * 60)) / (1000 * 60));
                const segundos = Math.floor((diferenca % (1000 * 60)) / 1000);
                document.getElementById('timerText').innerHTML = 
                    minutos.toString().padStart(2, '0') + ':' + segundos.toString().padStart(2, '0');
            }
        }
        
        setInterval(updateTimer, 1000);
        updateTimer();
        <?php endif; ?>
    </script>
</body>
</html>
