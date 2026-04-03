# Issue 005 — WhatsApp: Notificações Automáticas

## Descrição
Enviar mensagem WhatsApp automática para clientes
quando estiver próximo do vencimento.
Usar Evolution API (self-hosted, gratuito) ou Z-API.

## Arquivos a criar
- app/config/whatsapp.php → client da API
- app/cron/whatsapp_notificador.php → worker diário
- app/configuracoes.php → adicionar seção WhatsApp

## Aceite
- [ ] Configurar número WhatsApp Business no painel
- [ ] Template de mensagem customizável
- [ ] Envio X dias antes do vencimento (configurável)
- [ ] Log de mensagens enviadas
- [ ] Opt-out por cliente (não enviar para este)
