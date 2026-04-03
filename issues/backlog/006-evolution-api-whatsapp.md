# Issue 006 — Migrar WhatsApp para Evolution API (self-hosted)

## Descrição
Substituir Z-API (pago) pelo Evolution API gratuito
rodando no próprio Docker do projeto.
Zero custo de API, cada cliente usa sua instância.

## Motivação
- Z-API custa ~R$49/mês por instância
- Evolution API é open source e gratuito
- Já temos Docker configurado no projeto

## Arquivos a modificar
- docker-compose.yml → adicionar container evolution-api
- app/config/whatsapp.php → trocar endpoint Z-API → Evolution
- app/configuracoes.php → atualizar campos de config

## Compatibilidade
- Manter mesma interface: send_whatsapp($numero, $msg)
- Apenas trocar o client por baixo
- Zero impacto no resto do sistema

## Referência
- https://github.com/EvolutionAPI/evolution-api
- Porta padrão: 8080

## Aceite
- [ ] Evolution API rodando no Docker
- [ ] QR Code para conectar WhatsApp na tela de config
- [ ] send_whatsapp() funcionando com nova API
- [ ] Remover dependência Z-API completamente
