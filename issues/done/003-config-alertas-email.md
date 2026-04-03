# Issue 003 — Configuração de Alertas de E-mail

## Descrição
Painel para o admin configurar quando os alertas
são enviados: X dias antes para o admin, Y dias para o cliente.

## Arquivos a criar
- app/configuracoes.php → painel de config
- app/actions/config_action.php → salvar configs
- tabela: configuracoes (chave/valor)

## Aceite
- [ ] Configurar dias de antecedência para alerta ao admin
- [ ] Configurar dias de antecedência para alerta ao cliente
- [ ] Ativar/desativar alertas por tipo (email admin, email cliente)
- [ ] Salvar e refletir no cron/notificador.php
