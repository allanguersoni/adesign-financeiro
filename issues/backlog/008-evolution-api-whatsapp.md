# Issue 008 — Migrar WhatsApp para Evolution API

## Descrição
Substituir a integração Z-API (paga) pela Evolution API (gratuita, self-hosted).
A Evolution API roda no próprio Docker e elimina a dependência de serviço externo.

## Por que importante
- Z-API tem custo mensal; Evolution API é open-source e self-hosted
- Maior controle sobre a instância WhatsApp
- Mesma interface HTTP — mudança cirúrgica no client PHP

## Arquivos a modificar
- `docker-compose.yml` — adicionar serviço `evolution-api`
- `app/config/whatsapp.php` — trocar endpoint Z-API → Evolution API
- `app/config/settings.php` — novas chaves: `evolution_url`, `evolution_api_key`
- `app/configuracoes.php` — seção WhatsApp: campos Instance Z-API → Evolution
- `app/actions/salvar_config.php` — salvar novas chaves
- `app/actions/testar_whatsapp.php` — adaptar payload da requisição
- `app/cron/whatsapp_notificador.php` — verificar compatibilidade
- `.env.example` — documentar novas variáveis

## Aceite
- [ ] Evolution API sobe via `docker-compose up` sem configuração manual
- [ ] `config/whatsapp.php` conecta e envia mensagem de teste com sucesso
- [ ] Seção WhatsApp em `configuracoes.php` tem campos corretos para Evolution API
- [ ] Botão "Testar Conexão" retorna sucesso no browser
- [ ] Cron `whatsapp_notificador.php` envia notificações corretamente
- [ ] Z-API removida de todas as referências no código
