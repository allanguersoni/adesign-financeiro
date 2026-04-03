# 🤖 BRIEFING — Claude Code Session
> Leia este arquivo COMPLETO antes de qualquer ação.
> Este documento é sua bússola. Não assuma nada que não esteja aqui.

---

## 🎯 O QUE É ESTE PROJETO

**ADesign Financeiro** — SaaS de gestão financeira para agências,
freelancers e micro empreendedores (MEI).

Controla: clientes, cobranças recorrentes, inadimplência,
pagamentos PIX, notificações por e-mail e WhatsApp.

---

## 🧠 COMO SE COMPORTAR NESTA SESSÃO

### Economizar tokens
- NUNCA reescreva arquivos inteiros se a mudança for pequena
- Use str_replace cirúrgico para alterações pontuais
- Leia apenas os arquivos necessários para a tarefa
- Pergunte antes de ler arquivos grandes desnecessários
- Um problema por vez — não resolva tudo de uma vez

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
Backend:    PHP 8.x Vanilla (SEM frameworks)
Banco:      MySQL 8.0 via PDO (Prepared Statements)
Frontend:   TailwindCSS via CDN (SEM build step)
Email:      PHPMailer via SMTP
Container:  Docker + Docker Compose (WSL2 Ubuntu)
Servidor:   Apache 2.4 (DocumentRoot = app/)
Deploy:     Dreamhost (produção)

---

## 📁 ESTRUTURA DE PASTAS
allandesign-financeiro/
├── .env                    ← credenciais (NUNCA no git)
├── .gitignore
├── docker-compose.yml
├── certs/                  ← certificados Efí Bank .p12 (NUNCA no git)
│   ├── efi_prod.p12
│   └── efi_homolog.p12
├── app/                    ← DocumentRoot Apache
│   ├── index.php           ← Dashboard
│   ├── login.php           ← Autenticação
│   ├── clientes.php        ← CRUD clientes
│   ├── cobrancas.php       ← Faturas e cobranças
│   ├── pagamentos.php      ← Controle mensal de pagamentos
│   ├── configuracoes.php   ← ⚠️ ARQUIVO COM PROBLEMA (ver abaixo)
│   ├── pix.php             ← Página pública de pagamento PIX
│   ├── config/
│   │   ├── auth.php        ← CORE: sessão, CSRF, RBAC, flash
│   │   ├── conexao.php     ← PDO connection
│   │   ├── env.php         ← Parser do .env
│   │   ├── email.php       ← PHPMailer + templates
│   │   ├── settings.php    ← Helper setting($chave)
│   │   ├── whatsapp.php    ← Z-API client ✅ FUNCIONANDO
│   │   ├── pix_simples.php ← BR Code + QR Code ✅ FUNCIONANDO
│   │   └── pix_efi.php     ← Efí Bank API (a implementar)
│   ├── actions/            ← Endpoints POST
│   │   ├── salvar_cliente.php
│   │   ├── editar_cliente.php
│   │   ├── enviar_cobranca.php
│   │   ├── confirmar_pagamento.php
│   │   ├── gerar_link_pix.php
│   │   ├── testar_whatsapp.php  ✅ FUNCIONANDO
│   │   ├── salvar_config.php
│   │   └── webhook_efi.php (a criar)
│   ├── cron/
│   │   ├── notificador.php          ← E-mail ✅ FUNCIONANDO
│   │   └── whatsapp_notificador.php ← WhatsApp ✅ FUNCIONANDO
│   ├── includes/
│   │   ├── header.php
│   │   └── footer.php
│   └── vendor/
│       └── phpmailer/
├── issues/
│   ├── backlog/            ← features futuras
│   ├── doing/              ← em desenvolvimento
│   └── done/               ← concluídas
└── references/             ← documentação para IA
├── ARCHITECTURE.md
├── STACK.md
├── SECURITY.md
├── WORKFLOW.md
└── este arquivo

---

## 🔧 FUNÇÕES GLOBAIS DISPONÍVEIS

Todas definidas em `app/config/auth.php`:
```php
require_auth()                    // bloqueia não logados
require_can('permissao')          // bloqueia por role
can('permissao')                  // retorna bool
csrf_field()                      // input hidden token
validate_csrf($token)             // valida ou mata requisição
set_flash('tipo', 'msg')          // mensagem de feedback
get_flash()                       // lê e limpa flash
sanitize_string($input)           // higieniza input
redirect_back('/pagina.php')      // redireciona após action
```

Definida em `app/config/settings.php`:
```php
setting('chave', $default)        // lê tabela configuracoes
save_setting('chave', 'valor')    // salva no banco
```

---

## 🔐 RBAC — PERMISSÕES
admin  → tudo
editor → edit_clients, send_charges
demo   → view_all (somente leitura)

---

## 🏗️ PADRÃO ARQUITETURAL — Paged-Action
NUNCA violar este padrão:
Página: app/recurso.php
→ require_once 'config/auth.php'
→ require_auth()
→ require_once 'includes/header.php'
→ HTML + Tailwind
→ require_once 'includes/footer.php'
Action: app/actions/recurso_action.php
→ require_once '../config/auth.php'
→ require_once '../config/conexao.php'
→ validate_csrf($_POST['csrf_token'] ?? '')
→ require_can('permissao')
→ sanitize_string() em todos os inputs
→ PDO prepare()->execute() SEMPRE
→ set_flash() + redirect_back()

---

## 🗄️ BANCO DE DADOS — TABELAS EXISTENTES
```sql
-- Clientes com recorrência
clientes:
  id, nome, email, dominio
  valor_anual DECIMAL(10,2)
  tipo_pagamento ENUM('a vista','2x','3x')
  status ENUM('em dia','pendente','vence em 15 dias')
  data_vencimento_base DATE
  tipo_recorrencia ENUM('mensal','anual') DEFAULT 'anual'
  dia_vencimento TINYINT DEFAULT 1
  alerta_admin_dias TINYINT DEFAULT 15
  alerta_cliente_dias TINYINT DEFAULT 7
  whatsapp VARCHAR(20) NULL
  whatsapp_ativo TINYINT(1) DEFAULT 1

-- Pagamentos mensais
pagamentos:
  id, cliente_id, competencia DATE
  valor DECIMAL(10,2)
  status ENUM('pendente','pago','cancelado')
  pago_em DATETIME NULL
  metodo VARCHAR(50) NULL
  pix_token VARCHAR(64) NULL
  pix_txid VARCHAR(64) NULL
  pix_qr_code TEXT NULL
  pix_expira_em DATETIME NULL

-- Configurações chave/valor
configuracoes:
  chave, valor, descricao

-- Chaves existentes em configuracoes:
  alerta_admin_dias_padrao
  alerta_cliente_dias_padrao
  alertas_email_admin
  alertas_email_cliente
  email_rodape_cobranca
  nome_empresa
  url_sistema
  pix_modo (simples/avancado)
  pix_chave
  pix_beneficiario
  pix_cidade
  efi_sandbox
  efi_client_id
  efi_client_secret
  efi_certificado
  whatsapp_ativo
  whatsapp_instance_id
  whatsapp_token
  whatsapp_numero_proprio
  whatsapp_dias_antes
  whatsapp_template_vencimento
  whatsapp_template_atraso

-- Controle de segurança
usuarios, login_tentativas, password_resets
```

---

## ✅ O QUE ESTÁ FUNCIONANDO
✅ Login/logout com rate limiting
✅ CRUD de clientes (mensal/anual)
✅ Dashboard com métricas
✅ Cobranças com envio de e-mail real (SMTP Dreamhost)
✅ PIX QR Code estático (página pública /pix.php)
✅ Controle de pagamentos mensais
✅ Configurações (parcial)
✅ WhatsApp config/whatsapp.php (client Z-API)
✅ WhatsApp cron/whatsapp_notificador.php
✅ WhatsApp actions/testar_whatsapp.php

---

## ⚠️ PROBLEMA ATUAL — SUA MISSÃO

### Arquivo: `app/configuracoes.php`

**O que está errado:**
O arquivo está grande, sem organização visual clara.
A seção WhatsApp ainda mostra badge "EM BREVE" e
toggle desabilitado — não tem os campos reais da Z-API.

**O que precisa:**

1. REDESIGN COMPLETO da tela de configurações:
   Layout duas colunas:
   [Menu lateral com seções] | [Conteúdo da seção ativa]

   Seções do menu:
   - 👤 Perfil (nome, senha)
   - 🔔 Alertas (e-mail admin/cliente, dias)
   - 💳 Pagamentos (PIX simples + Efí Bank)
   - 💬 WhatsApp (Z-API — campos reais)
   - 🏢 Empresa (nome, URL, rodapé)
   - ⚙️ Sistema (info técnica + notificador manual)
   - 👥 Usuários (só admin)

2. SEÇÃO WHATSAPP com campos reais:
   - Toggle ativar/desativar
   - Instance ID Z-API
   - Token Z-API (password + olhinho)
   - Seu número WhatsApp
   - Dias antes do vencimento
   - Templates colapsáveis (vencimento + atraso)
   - Badges clicáveis: {nome} {dominio} {valor} etc
   - Botão testar → fetch /actions/testar_whatsapp.php
   - Resultado inline verde/vermelho

**Constraints importantes:**
- Manter CSRF field no form
- Manter action="/actions/salvar_config.php"
- Todos os campos devem ter name= correto
- JS puro, TailwindCSS CDN
- Não quebrar o que já funciona

---

## 🎨 TEMA VISUAL
```css
/* Cores principais */
background:    #0f172a  (slate-900)
card-bg:       rgba(255,255,255,0.05)
card-border:   rgba(255,255,255,0.08)
text-primary:  #f1f5f9
text-muted:    #94a3b8
accent:        #4ade80  (green-400)
accent-dark:   #84cc16  (lime-400)
error:         #f87171  (red-400)
warning:       #fbbf24  (amber-400)

/* Componentes */
border-radius: 12-16px
backdrop-blur: blur(12px)
input-bg:      rgba(255,255,255,0.05)
input-border:  rgba(255,255,255,0.1)
input-focus:   border #4ade80 + shadow verde sutil

/* Menu lateral ativo */
background:    rgba(74,222,128,0.1)
border-left:   3px solid #4ade80
text:          #4ade80
```

---

## 📋 BACKLOG (não implementar agora)
006 — Evolution API WhatsApp (gratuito)
007 — Multi-segmento + White-label
008 — PIX Dinâmico Efí Bank
009 — Dashboard financeiro avançado
010 — Módulo despesas/fluxo de caixa
011 — MEI Helper (DAS)
012 — Multi-tenant SaaS

---

## 🚀 ORDEM DE EXECUÇÃO DESTA SESSÃO
PASSO 1: Ler app/configuracoes.php atual
PASSO 2: Ler app/actions/salvar_config.php
PASSO 3: Mostrar plano do redesign
PASSO 4: Aguardar aprovação
PASSO 5: Implementar configuracoes.php novo
PASSO 6: Testar WhatsApp funcionando
PASSO 7: Commitar

---

## ⚡ COMANDO PARA COMEÇAR

Após ler este arquivo, execute:
```bash
wc -l app/configuracoes.php
cat app/actions/salvar_config.php
```

Depois mostre o plano. Não implemente ainda.
