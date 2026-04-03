# Issue 007 — Multi-segmento e White-label

## Descrição
Permitir que o sistema seja adaptado para diferentes
tipos de negócio (agências, estacionamentos, clínicas,
academias) com terminologia, campos e fluxos específicos.

## Problema
Hoje o sistema usa terminologia de agência:
"domínio", "assinatura anual/mensal"

Um estacionamento precisa de:
"placa", "mensalista", "vaga"

Uma academia precisa de:
"aluno", "plano", "modalidade"

## Solução proposta: Segmentos configuráveis

### Nova tabela: segmentos
  id, nome, slug, configuracoes JSON
  Exemplos:
    agencia    → {campo1: "Domínio", campo2: "Plano"}
    estacion.  → {campo1: "Placa", campo2: "Vaga"}
    academia   → {campo1: "Modalidade", campo2: "Plano"}
    clinica    → {campo1: "Especialidade", campo2: "Plano"}

### Nova tabela: empresas (multi-tenant)
  id, nome, logo, cor_primaria, cor_secundaria,
  segmento_id, plano, max_clientes,
  dominio_custom, ativo, created_at

### Cada usuário terá empresa_id
  Sistema lê configs visuais e terminologia da empresa

## Personalização por segmento
- Logo próprio
- Cores primária e secundária
- Nome dos campos ("Cliente" → "Mensalista")
- Templates de e-mail com identidade da empresa
- Templates de WhatsApp customizados
- Favicon próprio

## White-label
- Subdomínio: cliente.adesignfinanceiro.com.br
- Domínio próprio: financeiro.empresa.com.br (+premium)

## Planos sugeridos
  Starter  → 1 empresa, 30 clientes, R$47/mês
  Pro      → 1 empresa, 150 clientes, R$97/mês
  Business → 1 empresa, ilimitado, R$197/mês
  Agency   → múltiplas empresas, white-label, R$397/mês

## Aceite
- [ ] Tabela empresas criada
- [ ] Tabela segmentos com configs JSON
- [ ] Sistema detecta empresa pelo subdomínio/login
- [ ] Terminologia muda de acordo com segmento
- [ ] Logo e cores aplicados globalmente
- [ ] Admin master gerencia todas as empresas
- [ ] Página pública de cadastro/planos
