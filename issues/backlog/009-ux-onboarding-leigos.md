# Issue 009 — UX/Onboarding para Leigos

## Descrição
Redesign da experiência inicial para usuários sem conhecimento técnico.
Foco em linguagem simples, tutoriais inline e mobile first revisado.

## Por que importante
- O sistema é usado por freelancers e MEI que não são técnicos
- Interface atual assume familiaridade com termos financeiros e de sistema
- Reduz fricção no onboarding de novos clientes do SaaS

## Arquivos a modificar
- `app/index.php` — estado vazio com tutorial ("Cadastre seu primeiro cliente")
- `app/clientes.php` — tooltips nos campos do formulário
- `app/cobrancas.php` — explicação do que é cada status
- `app/pagamentos.php` — legenda do calendário de competências
- `app/configuracoes.php` — wizard de setup inicial (PIX + empresa + alertas)
- `app/includes/header.php` — revisão do layout mobile (padding, tamanhos, tap targets)
- `app/includes/footer.php` — eventual barra de ajuda contextual

## Aceite
- [ ] Dashboard vazio exibe CTA claro: "Cadastre seu primeiro cliente →"
- [ ] Formulário de cliente tem tooltip em cada campo explicando o que preencher
- [ ] Status "em dia", "pendente", "vence em 15 dias" têm descrição visível
- [ ] Configurações têm wizard de primeiro acesso (detecta campos vazios)
- [ ] Todos os botões principais têm `min-height: 44px` (tap target mobile)
- [ ] Nenhuma tela exige scroll horizontal em viewport 375px
- [ ] Zero jargão técnico sem explicação (ex: "competência" explicado como "mês de referência")
