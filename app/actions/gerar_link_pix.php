<?php
/**
 * app/actions/gerar_link_pix.php
 * ==============================
 * Action para gerar token público do PIX, calcular o payload
 * do BR Code estático e redirecionar para a página pública.
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/pix_simples.php';

validate_csrf($_POST['csrf_token'] ?? '') || (set_flash('error', 'Token inválido') & redirect_back());
require_can('edit_clients');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método não permitido.');
}

$pagamento_id = (int) ($_POST['pagamento_id'] ?? 0);
$cliente_id = (int) ($_POST['cliente_id'] ?? 0);

if ($pagamento_id <= 0 && $cliente_id <= 0) {
    set_flash('error', 'ID de cobrança/cliente inválido.');
    redirect_back();
}

try {
    if ($pagamento_id > 0) {
        $stmt = $pdo->prepare("
            SELECT p.*, c.nome, c.dominio
            FROM pagamentos p
            JOIN clientes c ON p.cliente_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$pagamento_id]);
        $pagamento = $stmt->fetch();

        if (!$pagamento) {
            set_flash('error', 'Cobrança não encontrada.');
            redirect_back();
        }
    } else {
        // Criar pagamento pendente via cliente_id
        $stmt_cli = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt_cli->execute([$cliente_id]);
        $cliente = $stmt_cli->fetch();

        if (!$cliente) {
            set_flash('error', 'Cliente não encontrado.');
            redirect_back();
        }

        $stmt_ins = $pdo->prepare("
            INSERT INTO pagamentos (cliente_id, competencia, valor, status)
            VALUES (:cliente_id, CURDATE(), :valor_anual, 'pendente')
        ");
        $stmt_ins->execute([
            'cliente_id' => $cliente_id,
            'valor_anual' => $cliente['valor_anual']
        ]);
        
        $pagamento_id = (int) $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            SELECT p.*, c.nome, c.dominio
            FROM pagamentos p
            JOIN clientes c ON p.cliente_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$pagamento_id]);
        $pagamento = $stmt->fetch();
    }

    if ($pagamento['status'] === 'pago') {
        set_flash('error', 'Esta cobrança já consta como paga.');
        redirect_back();
    }

    $token = bin2hex(random_bytes(16));
    
    // Na solicitação "txid => substr(uniqid(), 0, 25)"
    $txid = substr(uniqid('PIX'), 0, 25);

    $dados = [
        'chave'        => setting('pix_chave'),
        'beneficiario' => setting('pix_beneficiario'),
        'cidade'       => setting('pix_cidade'),
        'valor'        => $pagamento['valor'],
        'txid'         => $txid,
        'descricao'    => 'Cobranca ' . substr($pagamento['dominio'] ?? '', 0, 20)
    ];

    $resultado = gerar_pix_estatico($dados);
    $payload = $resultado['payload'];

    $stmt_upd = $pdo->prepare("
        UPDATE pagamentos 
        SET pix_token = :token,
            pix_qr_code = :payload,
            pix_txid = :txid
        WHERE id = :id
    ");

    $stmt_upd->execute([
        'token' => $token,
        'payload' => $payload,
        'txid' => $txid,
        'id' => $pagamento_id
    ]);

    // Redireciona para exibir o QRCode (público)
    header("Location: /pix.php?token={$token}");
    exit;

} catch (PDOException $e) {
    error_log("Erro em gerar_link_pix: " . $e->getMessage());
    set_flash('error', 'Ocorreu um erro interno ao gerar o PIX.');
    redirect_back();
}
