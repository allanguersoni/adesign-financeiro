# Issue 001 — Clientes: Recorrência Mensal/Anual

## Descrição
Adicionar suporte a clientes mensais além dos anuais.
Cada cliente terá: tipo_recorrencia, dia_vencimento,
configuração de quantos dias antes o alerta é enviado.

## Arquivos a modificar
- app/clientes.php → formulário + campos novos
- app/actions/cliente_action.php → salvar novos campos
- Migration → ALTER TABLE clientes

## Aceite
- [ ] Campo tipo_recorrencia (mensal/anual) no cadastro
- [ ] Campo dia_vencimento (1-28)
- [ ] Campo alerta_dias_antes separado para admin e cliente
- [ ] Status calculado automaticamente baseado no dia_vencimento
- [ ] Clientes mensais geram registro em pagamentos todo mês
