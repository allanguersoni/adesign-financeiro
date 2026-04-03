# Como iniciar qualquer sessão de desenvolvimento

Cole este bloco no início de TODA conversa nova com IA:

---

Projeto: ADesign Financeiro
Tipo: SaaS PHP Vanilla de gestão financeira para agências
Stack: PHP 8.x + MySQL 8.0 + PDO + TailwindCSS CDN + PHPMailer + Docker

Padrão arquitetural: Paged-Action (sem MVC)
- Páginas em: app/recurso.php
- Actions em: app/actions/recurso_action.php
- Config/Auth em: app/config/auth.php

Funções globais disponíveis:
require_auth(), require_can(), can(), csrf_field(),
validate_csrf(), set_flash(), sanitize_string(), redirect_back()

RBAC: admin > editor > demo (view-only)

Features prontas: login, CRUD clientes, dashboard,
cobranças por e-mail, recuperação de senha, rate limiting

Tarefa desta sessão: [DESCREVA AQUI]

Regras obrigatórias:
- Leia ARCHITECTURE.md antes de sugerir código
- Mostre o plano antes de implementar
- Siga o padrão Paged-Action existente
- Use sempre prepare()->execute() no PDO
- Nunca lógica de permissão no front-end

---
