# Issue 010 — PIX Dinâmico Efí Bank

## Descrição
Implementar PIX dinâmico via Efí Bank para geração de cobranças com QR Code único por pagamento.
Credenciais e certificados já estão disponíveis no ambiente.

## Por que importante
- PIX estático não confirma pagamento automaticamente — exige confirmação manual
- PIX dinâmico gera `txid` rastreável e dispara webhook ao receber pagamento
- Elimina trabalho manual de confirmar pagamentos no painel

## Arquivos a modificar
- `app/config/pix_efi.php` — implementar client Efí Bank (cob, webhook)
- `app/actions/gerar_link_pix.php` — chamar Efí API em vez de gerar QR estático
- `app/actions/webhook_efi.php` — criar handler do webhook de confirmação
- `app/actions/confirmar_pagamento.php` — aceitar confirmação via webhook
- `app/pix.php` — exibir QR Code dinâmico com valor e expiração
- `.htaccess` — rota pública para `/webhook/efi`
- `.env` / `.env.example` — documentar `EFI_CLIENT_ID`, `EFI_CLIENT_SECRET`, `EFI_SANDBOX`

## Aceite
- [ ] `pix_efi.php` autentica via OAuth com certificado `.p12` em `certs/`
- [ ] `gerar_link_pix.php` cria cobrança (cob) e retorna QR Code dinâmico
- [ ] QR Code exibe valor correto, nome do beneficiário e data de expiração
- [ ] Webhook `/webhook/efi` recebe POST da Efí e marca pagamento como `pago`
- [ ] Modo sandbox funciona para testes sem movimentação real
- [ ] Modo produção ativado via toggle em Configurações → PIX Avançado
- [ ] Certificados em `certs/` nunca comitados no git (`.gitignore` já cobre)
