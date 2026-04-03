# STACK.md — ADesign Financeiro

## Backend
- Linguagem: PHP 8.x Vanilla (sem frameworks)
- Banco: MySQL 8.0 via PDO (Prepared Statements)
- Email: PHPMailer via SMTP TLS porta 587
- Sessões: PHP Sessions nativas (httponly + samesite=Strict)

## Frontend
- CSS: Tailwind CSS via CDN
- Estilo: Glassmorphism customizado
- Ícones: Material Symbols Outlined (Google MD3)
- Fonte: Inter (Google Fonts)

## Infraestrutura
- Containers: Docker + Docker Compose
- Servidor: Apache 2.4 (DocumentRoot = app/)
- Ambiente: WSL2 Ubuntu 24.04 + Docker Desktop
- Deploy: cPanel/Dreamhost + Apache .htaccess + Cron Jobs

## Serviços Docker
- web (apache/php) → porta 80:80
- db (mysql:8.0) → porta 3306:3306
- phpmyadmin → porta 8080:8080

## Dependências PHP (vendor/)
- PHPMailer (via Composer)

## Proibido adicionar sem justificativa
- Frameworks MVC (Laravel, Symfony) — projeto é PHP Vanilla por design
- jQuery — JS nativo é suficiente
- Qualquer lib que exija build step (webpack, vite)
