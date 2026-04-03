# Issue 004 — PIX: Módulo Completo (Simples + Dinâmico)

## Descrição
Implementar dois modos de pagamento PIX no sistema,
permitindo que cada administrador escolha o mais adequado
ao seu perfil. O modo ativo é configurado no painel.

## Modo Simples (PIX Estático)
- Qualquer pessoa pode usar — só precisa de chave PIX
- QR Code gerado com chave + valor + nome beneficiário
- Confirmação de pagamento feita manualmente pelo admin
- Zero burocracia, zero cadastro externo

## Modo Avançado (PIX Dinâmico — Efí Bank)
- Requer conta Efí Bank (gratuita, Pessoa Física aceita)
- QR Code único por cobrança com valor exato
- Confirmação AUTOMÁTICA via webhook
- Sistema atualiza status do pagamento sozinho

## Arquivos a criar
- app/pix.php                        → página de cobrança PIX
- app/config/pix.php                 → client unificado (detecta modo)
- app/config/efi.php                 → client Efí Bank API
- app/actions/confirmar_pix.php      → webhook Efí (modo avançado)
- app/actions/gerar_qr_pix.php       → gerar QR por cobrança
- app/migrate_v6.php                 → novas colunas/tabelas
- Lib: phpqrcode ou bacon/bacon-qr-code

## Novas configs no painel (configuracoes.php)
Card PIX com toggle de modo:
  pix_modo               → 'simples' ou 'avancado'
  pix_chave              → chave PIX (CPF/CNPJ/email/telefone)
  pix_beneficiario       → nome do recebedor
  pix_cidade             → cidade do recebedor
  efi_client_id          → credencial Efí (modo avançado)
  efi_client_secret      → credencial Efí (modo avançado)
  efi_certificado        → path do .p12 (modo avançado)
  efi_sandbox            → '1' para testes, '0' produção

## Aceite
### Modo Simples
- [ ] QR Code gerado com chave PIX + valor + nome + cidade
- [ ] Botão "Copiar código PIX copia e cola"
- [ ] Página pública /pagar/{token} sem necessidade de login
- [ ] Admin confirma pagamento manualmente
- [ ] QR Code aparece na tela de cobranças e pagamentos

### Modo Avançado (Efí)
- [ ] QR Code dinâmico único por cobrança
- [ ] Webhook recebe confirmação do Efí Bank
- [ ] Status do pagamento atualizado automaticamente
- [ ] Log de transações PIX no banco

### Geral
- [ ] Painel de configurações com toggle Simples/Avançado
- [ ] Sistema detecta modo ativo e usa automaticamente
- [ ] Funciona em localhost (modo simples) e produção (ambos)
- [ ] Responsivo mobile (cliente paga pelo celular)

## Dependências
- Issue 002 (pagamentos) deve estar concluída ✅
- Issue 003 (configurações) deve estar concluída ✅
- Conta Efí Bank aprovada (apenas modo avançado)

## Notas técnicas
- PIX Estático: seguir manual BACEN BR Code
- PIX Dinâmico: SDK oficial Efí ou chamadas REST diretas
- Webhook deve ser URL pública (não funciona em localhost)
- Certificado .p12 do Efí deve ficar FORA do DocumentRoot