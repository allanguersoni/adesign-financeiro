# Issue 004 — PIX QR Code Estático

## Descrição
Gerar QR Code PIX por cliente para facilitar pagamento.
Fase 1: PIX estático (chave + valor fixo, confirmação manual).
Fase 2 futura: PIX dinâmico com webhook (Asaas/Efí).

## Arquivos a criar
- app/pix.php → exibir QR code por cliente/cobrança
- vendor/pixqrcode → lib PHP para gerar QR PIX

## Aceite
- [ ] QR Code gerado com chave PIX + valor + nome pagador
- [ ] Botão "Copiar código PIX" (copia e cola)
- [ ] Página pública /pagar/{token} acessível sem login
- [ ] Admin confirma pagamento manualmente após receber
