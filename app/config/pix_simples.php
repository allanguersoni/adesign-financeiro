<?php
/**
 * app/config/pix_simples.php
 * ==========================
 * Funções para geração de PIX Estático (BR Code BACEN)
 * Sem dependências externas de bibliotecas.
 */

/**
 * Calcula o CRC16-CCITT-FALSE do payload para o campo 63 do BR Code
 *
 * @param string $payload Payload do PIX (sem o hash crc16 final)
 * @return string Hash CRC16 hexadecimal maiúsculo com 4 dígitos
 */
function crc16_pix(string $payload): string {
    // Adiciona o ID do CRC16 (63) e o tamanho (04) para gerar o hash
    $payload .= '6304';
    
    // Algoritmo CRC16-CCITT-FALSE
    $polinomio = 0x1021;
    $resultado = 0xFFFF;
    
    for ($i = 0; $i < strlen($payload); $i++) {
        $resultado ^= (ord($payload[$i]) << 8);
        for ($bitwise = 0; $bitwise < 8; $bitwise++) {
            if (($resultado <<= 1) & 0x10000) {
                $resultado ^= $polinomio;
            }
            $resultado &= 0xFFFF;
        }
    }
    
    return strtoupper(str_pad(dechex($resultado), 4, '0', STR_PAD_LEFT));
}

/**
 * Formata um objeto do payload PIX (ID + Tamanho + Valor)
 * 
 * @param string $id Identificador do campo (2 dígitos)
 * @param string $valor Valor do campo
 * @return string String concatenada no formato BR Code
 */
function formatar_tamanho_pix(string $id, string $valor): string {
    $tamanho = str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT);
    return $id . $tamanho . $valor;
}

/**
 * Remove acentos e caracteres especiais, deixando apenas letras, números e espaços
 * Necessário para os campos do PIX (nome, cidade)
 */
function sanitizar_texto_pix(string $texto): string {
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = preg_replace('/[^A-Za-z0-9 ]/u', '', $texto);
    return trim($texto);
}

/**
 * Gera o payload e o QR Code em Base64 para um PIX Estático
 *
 * @param array $dados [chave, beneficiario, cidade, valor, txid, descricao]
 * @return array ['payload' => string, 'qrcode_base64' => string]
 */
function gerar_pix_estatico(array $dados): array {
    $chave = $dados['chave'] ?? '';
    $beneficiario = sanitizar_texto_pix($dados['beneficiario'] ?? '');
    $cidade = sanitizar_texto_pix($dados['cidade'] ?? '');
    $valor = $dados['valor'] ?? 0;
    $txid = sanitizar_texto_pix($dados['txid'] ?? '');
    $descricao = sanitizar_texto_pix($dados['descricao'] ?? '');

    // Restrições de tamanho do BACEN
    $beneficiario = substr($beneficiario, 0, 25);
    $cidade = substr($cidade, 0, 15);
    if (empty($txid)) {
        $txid = '***';
    } else {
        // txid de chave aleatória no máximo 25 caracteres validos a-z, A-Z, 0-9
        $txid = substr(preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 25);
        if (empty($txid)) $txid = '***';
    }

    $valor_formatado = number_format(floatval($valor), 2, '.', '');
    
    // 26 - Merchant Account Information
    $gui = formatar_tamanho_pix('00', 'BR.GOV.BCB.PIX');
    $chave_formatada = formatar_tamanho_pix('01', $chave);
    
    $merchant_account_info = $gui . $chave_formatada;
    if (!empty($descricao)) {
        $merchant_account_info .= formatar_tamanho_pix('02', substr($descricao, 0, 40));
    }
    
    $payload_parts = [];
    $payload_parts[] = formatar_tamanho_pix('00', '01'); // 00 Payload Format Indicator
    $payload_parts[] = formatar_tamanho_pix('26', $merchant_account_info); // 26 Merchant Account Info
    $payload_parts[] = formatar_tamanho_pix('52', '0000'); // 52 Merchant Category Code
    $payload_parts[] = formatar_tamanho_pix('53', '986');  // 53 Transaction Currency (BRL=986)
    
    if (floatval($valor_formatado) > 0) {
        $payload_parts[] = formatar_tamanho_pix('54', $valor_formatado); // 54 Transaction Amount
    }
    
    $payload_parts[] = formatar_tamanho_pix('58', 'BR'); // 58 Country Code
    $payload_parts[] = formatar_tamanho_pix('59', $beneficiario); // 59 Merchant Name
    $payload_parts[] = formatar_tamanho_pix('60', $cidade); // 60 Merchant City
    
    // 62 - Additional Data Field Template
    $additional_data_txid = formatar_tamanho_pix('05', $txid);
    $payload_parts[] = formatar_tamanho_pix('62', $additional_data_txid);
    
    // Junta o payload e finaliza com o CRC16
    $payload = implode('', $payload_parts);
    $crc16 = crc16_pix($payload);
    $payload_final = $payload . '6304' . $crc16;
    
    // Geração do QR Code Base64 via API pública
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($payload_final);
    
    $qrcode_base64 = '';
    $ch = curl_init($qr_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $image_data !== false) {
        $qrcode_base64 = 'data:image/png;base64,' . base64_encode($image_data);
    }
    
    return [
        'payload' => $payload_final,
        'qrcode_base64' => $qrcode_base64
    ];
}
