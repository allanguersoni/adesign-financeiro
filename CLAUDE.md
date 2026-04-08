# 🤖 BRIEFING — ADesign Financeiro
> Leia este arquivo COMPLETO antes de qualquer ação.
> Este documento é sua memória persistente. Não assuma nada que não esteja aqui.

---

## ⚠️ PROTOCOLO OBRIGATÓRIO PARA O CLAUDE

### AO INICIAR A SESSÃO:
1. Leia este arquivo inteiro
2. Leia os arquivos em `issues/doing/` (se existirem)
3. Diga em 3 linhas: o que o projeto faz, o que foi feito por último, e qual é a próxima ação
4. Aguarde confirmação antes de qualquer código

### AO ENCERRAR A SESSÃO (antes de qualquer despedida):
Você DEVE atualizar as seguintes seções deste arquivo:
- `## 🕐 ÚLTIMA SESSÃO` → o que foi feito
- `## 🎯 PRÓXIMA SESSÃO` → próximo passo exato
- `## 📊 ESTADO DAS ISSUES` → mova concluídas para done/
- `## 📝 DECISÕES TÉCNICAS` → registre qualquer decisão importante

> ❌ NUNCA encerre a sessão sem atualizar este arquivo.
> ✅ Após atualizar, confirme: "Briefing atualizado. Até a próxima sessão."

---

## 🎯 O QUE É ESTE PROJETO

**ADesign Financeiro** — SaaS de gestão financeira para agências, freelancers e micro empreendedores (MEI).

Controla: clientes, cobranças recorrentes, inadimplência, pagamentos PIX, notificações por e-mail e WhatsApp.

---

## 🕐 ÚLTIMA SESSÃO
<!-- Claude atualiza aqui ao encerrar -->
- **Data:** (não registrada)
- **O que foi feito:** Estrutura inicial do projeto, WhatsApp Z-API funcionando, PIX estático funcionando
- **Arquivos modificados:** config/whatsapp.php, cron/whatsapp_notificador.php, actions/testar_whatsapp.php
- **Estado ao encerrar:** configuracoes.php precisa de redesign completo

---

## 🎯 PRÓXIMA SESSÃO
<!-- Claude atualiza aqui ao encerrar -->
- **Issue ativa:** Redesign de app/configuracoes.php
- **Primeiro arquivo a abrir:** app/configuracoes.php + app/actions/salvar_config.php
- **Contexto crítico:** WhatsApp já funciona no backend — a tela só não tem os campos reais. Não recriar a lógica, apenas o HTML/UI.
- **Ação imediata:** `wc -l app/configuracoes.php && cat app/actions/salvar_config.php`

---

## 📊 ESTADO DAS ISSUES
<!-- Claude mantém esta tabela atualizada -->
| Issue | Título | Status |
|-------|--------|--------|
| 001 | Login + RBAC + segurança | ✅ done |
| 002 | CRUD clientes (mensal/anual) | ✅ done |
| 003 | Dashboard com métricas | ✅ done |
| 004 | Cobranças por e-mail (SMTP) | ✅ done |
| 005 | PIX QR Code estático | ✅ done |
| 006 | WhatsApp Z-API (backend) | ✅ done |
| 007 | Redesign configuracoes.php | 🔄 doing |
| 008 | Evolution API WhatsApp | 📋 backlog |
| 009 | Multi-segmento + White-label | 📋 backlog |
| 010 | PIX Dinâmico Efí Bank | 📋 backlog |
| 011 | Dashboard financeiro avançado | 📋 backlog |
| 012 | Módulo despesas/fluxo de caixa | 📋 backlog |
| 013 | MEI Helper (DAS) | 📋 backlog |
| 014 | Multi-tenant SaaS | 📋 backlog |

---

## 📝 DECISÕES TÉCNICAS
<!-- Claude registra aqui decisões tomadas durante as sessões -->
| Data | Decisão | Motivo |
|------|---------|--------|
| — | PHP Vanilla sem framework | Simplicidade, deploy fácil no Dreamhost |
| — | TailwindCSS via CDN | Sem build step necessário |
| — | Z-API para WhatsApp | Custo-benefício, fácil integração |
| — | Efí Bank para PIX avançado | Único com suporte a PIX dinâmico via certificado |
| — | Padrão Paged-Action | Separação clara entre leitura e escrita |

---

## 🔄 MISSÃO ATUAL — ISSUE 007

### Arquivo: `app/configuracoes.php`

**Problema:** Arquivo grande sem organização visual. Seção WhatsApp mostra "EM BREVE" com toggle desabilitado — campos reais da Z-API não estão na tela.

**Entregável:**
Redesign completo com layout duas colunas:
`[Menu lateral com seções] | [Conteúdo da seção ativa]`

**Seções do menu:**
- 👤 Perfil (nome, senha)
- 🔔 Alertas (e-mail admin/cliente, dias)
- 💳 Pagamentos (PIX simples + Efí Bank)
- 💬 WhatsApp (Z-API — campos reais)
- 🏢 Empresa (nome, URL, rodapé)
- ⚙️ Sistema (info técnica + notificador manual)
- 👥 Usuários (só admin)

**Seção WhatsApp — campos obrigatórios:**
- Toggle ativar/desativar (`whatsapp_ativo`)
- Instance ID Z-API (`whatsapp_instance_id`)
- Token Z-API com campo password + olhinho (`whatsapp_token`)
- Número WhatsApp próprio (`whatsapp_numero_proprio`)
- Dias antes do vencimento (`whatsapp_dias_antes`)
- Templates colapsáveis: vencimento + atraso
- Badges clicáveis inseríveis: `{nome}` `{dominio}` `{valor}` `{vencimento}`
- Botão testar → fetch `/actions/testar_whatsapp.php`
- Resultado inline verde/vermelho

**Constraints que NÃO podem quebrar:**
- CSRF field no form
- `action="/actions/salvar_config.php"`
- Todos os `name=` devem bater com as chaves da tabela `configuracoes`
- JS puro, TailwindCSS CDN
- Não reescrever lógica que já funciona

---

## 🧠 REGRAS DE COMPORTAMENTO

### Economizar tokens
- NUNCA reescreva arquivos inteiros se a mudança for pequena — use `str_replace` cirúrgico
- Leia apenas os arquivos necessários para a tarefa
- Pergunte antes de abrir arquivos grandes sem necessidade clara
- Um problema por vez

### Antes de qualquer código
1. Leia os arquivos relevantes
2. Mostre o plano em bullets curtos
3. Aguarde aprovação
4. Implemente um arquivo por vez

### Se encontrar algo inesperado
- PARE imediatamente
- Informe o que encontrou
- Pergunte como proceder

---

## 💻 STACK TÉCNICA
```
Backend:    PHP 8.x Vanilla (SEM frameworks)
Banco:      MySQL 8.0 via PDO (Prepared Statements)
Frontend:   TailwindCSS via CDN (SEM build step)
Email:      PHPMailer via SMTP (Dreamhost)
Container:  Docker + Docker Compose (WSL2 Ubuntu)
Servidor:   Apache 2.4 (DocumentRoot = app/)
Deploy:     Dreamhost (produção)
```

---

## 📁 ESTRUTURA DE PASTAS
```
allandesign-financeiro/
├── .env                         ← credenciais (NUNCA no git)
├── .gitignore
├── docker-compose.yml
├── certs/                       ← certificados Efí Bank (NUNCA no git)
│   ├── efi_prod.p12
│   └── efi_homolog.p12
├── app/                         ← DocumentRoot Apache
│   ├── index.php                ← Dashboard
│   ├── login.php                ← Autenticação
│   ├── clientes.php             ← CRUD clientes
│   ├── cobrancas.php            ← Faturas e cobranças
│   ├── pagamentos.php           ← Controle mensal
│   ├── configuracoes.php        ← 🔄 EM REDESIGN (issue 007)
│   ├── pix.php                  ← Página pública PIX
│   ├── config/
│   │   ├── auth.php             ← CORE: sessão, CSRF, RBAC, flash
│   │   ├── conexao.php          ← PDO connection
│   │   ├── env.php              ← Parser do .env
│   │   ├── email.php            ← PHPMailer + templates
│   │   ├── settings.php         ← Helper setting($chave)
│   │   ├── whatsapp.php         ← Z-API client ✅
│   │   ├── pix_simples.php      ← BR Code + QR Code ✅
│   │   └── pix_efi.php          ← Efí Bank API (a implementar)
│   ├── actions/
│   │   ├── salvar_cliente.php
│   │   ├── editar_cliente.php
│   │   ├── enviar_cobranca.php
│   │   ├── confirmar_pagamento.php
│   │   ├── gerar_link_pix.php
│   │   ├── testar_whatsapp.php  ✅
│   │   ├── salvar_config.php
│   │   └── webhook_efi.php      ← (a criar)
│   ├── cron/
│   │   ├── notificador.php               ← E-mail ✅
│   │   └── whatsapp_notificador.php      ← WhatsApp ✅
│   ├── includes/
│   │   ├── header.php
│   │   └── footer.php
│   └── vendor/phpmailer/
├── issues/
│   ├── backlog/
│   ├── doing/
│   └── done/
└── references/
    ├── ARCHITECTURE.md
    ├── STACK.md
    ├── SECURITY.md
    ├── WORKFLOW.md
    └── CLAUDE_CODE_BRIEFING.md   ← este arquivo
```

---

## 🔧 FUNÇÕES GLOBAIS

**`app/config/auth.php`:**
```php
require_auth()                  // bloqueia não logados
require_can('permissao')        // bloqueia por role
can('permissao')                // retorna bool
csrf_field()                    // input hidden token
validate_csrf($token)           // valida ou mata requisição
set_flash('tipo', 'msg')        // mensagem de feedback
get_flash()                     // lê e limpa flash
sanitize_string($input)         // higieniza input
redirect_back('/pagina.php')    // redireciona após action
```

**`app/config/settings.php`:**
```php
setting('chave', $default)      // lê tabela configuracoes
save_setting('chave', 'valor')  // salva no banco
```

---

## 🔐 RBAC — PERMISSÕES
```
admin  → tudo
editor → edit_clients, send_charges
demo   → view_all (somente leitura)
```

---

## 🏗️ PADRÃO ARQUITETURAL — Paged-Action
> NUNCA violar este padrão.

**Página** `app/recurso.php`:
```
→ require_once 'config/auth.php'
→ require_auth()
→ require_once 'includes/header.php'
→ HTML + Tailwind
→ require_once 'includes/footer.php'
```

**Action** `app/actions/recurso_action.php`:
```
→ require_once '../config/auth.php'
→ require_once '../config/conexao.php'
→ validate_csrf($_POST['csrf_token'] ?? '')
→ require_can('permissao')
→ sanitize_string() em TODOS os inputs
→ PDO prepare()->execute() SEMPRE
→ set_flash() + redirect_back()
```

---

## 🗄️ BANCO DE DADOS
```sql
-- Clientes
clientes: id, nome, email, dominio,
  valor_anual DECIMAL(10,2),
  tipo_pagamento ENUM('a vista','2x','3x'),
  status ENUM('em dia','pendente','vence em 15 dias'),
  data_vencimento_base DATE,
  tipo_recorrencia ENUM('mensal','anual') DEFAULT 'anual',
  dia_vencimento TINYINT DEFAULT 1,
  alerta_admin_dias TINYINT DEFAULT 15,
  alerta_cliente_dias TINYINT DEFAULT 7,
  whatsapp VARCHAR(20) NULL,
  whatsapp_ativo TINYINT(1) DEFAULT 1

-- Pagamentos mensais
pagamentos: id, cliente_id, competencia DATE,
  valor DECIMAL(10,2),
  status ENUM('pendente','pago','cancelado'),
  pago_em DATETIME NULL, metodo VARCHAR(50) NULL,
  pix_token VARCHAR(64), pix_txid VARCHAR(64),
  pix_qr_code TEXT, pix_expira_em DATETIME NULL

-- Configurações (chave/valor)
configuracoes: chave, valor, descricao

-- Chaves existentes:
  alerta_admin_dias_padrao      alerta_cliente_dias_padrao
  alertas_email_admin           alertas_email_cliente
  email_rodape_cobranca         nome_empresa
  url_sistema                   pix_modo (simples/avancado)
  pix_chave                     pix_beneficiario
  pix_cidade                    efi_sandbox
  efi_client_id                 efi_client_secret
  efi_certificado               whatsapp_ativo
  whatsapp_instance_id          whatsapp_token
  whatsapp_numero_proprio       whatsapp_dias_antes
  whatsapp_template_vencimento  whatsapp_template_atraso

-- Segurança
usuarios, login_tentativas, password_resets
```

---

## ✅ O QUE ESTÁ FUNCIONANDO
```
✅ Login/logout com rate limiting
✅ CRUD de clientes (mensal/anual)
✅ Dashboard com métricas
✅ Cobranças com e-mail real (SMTP Dreamhost)
✅ PIX QR Code estático (/pix.php público)
✅ Controle de pagamentos mensais
✅ Configurações (parcial — UI desatualizada)
✅ WhatsApp: config/whatsapp.php (Z-API client)
✅ WhatsApp: cron/whatsapp_notificador.php
✅ WhatsApp: actions/testar_whatsapp.php
```

---

## 🎨 TEMA VISUAL
```css
/* Cores */
background:   #0f172a        /* slate-900 */
card-bg:      rgba(255,255,255,0.05)
card-border:  rgba(255,255,255,0.08)
text-primary: #f1f5f9
text-muted:   #94a3b8
accent:       #4ade80        /* green-400 */
accent-dark:  #84cc16        /* lime-400 */
error:        #f87171        /* red-400 */
warning:      #fbbf24        /* amber-400 */

/* Componentes */
border-radius: 12-16px
backdrop-blur: blur(12px)
input-bg:      rgba(255,255,255,0.05)
input-border:  rgba(255,255,255,0.1)
input-focus:   border #4ade80 + shadow verde sutil

/* Menu lateral — item ativo */
background:   rgba(74,222,128,0.1)
border-left:  3px solid #4ade80
text:         #4ade80
```

---

## ⚡ COMANDO PARA COMEÇAR ESTA SESSÃO

```bash
wc -l app/configuracoes.php
cat app/actions/salvar_config.php
```

Após executar, mostre o plano. **Não implemente ainda.**
