# Contexto do Projeto — ADesign Financeiro

## Ambiente
- Windows 11 com WSL2 (Ubuntu 24.04)
- Docker Desktop com integração WSL ativa
- Git instalado no WSL

## Stack
- PHP 8+ / MySQL 8 / Apache 2.4
- TailwindCSS + Glassmorphism
- PHPMailer SMTP TLS
- Docker + Docker Compose

## Problema atual
- Remote Git pode estar em HTTPS (precisa trocar para SSH)
- SSH key já gerada e adicionada no GitHub
- Autenticação SSH funcionando (testada com ssh -T git@github.com)

## Objetivo
- Versionar o projeto no GitHub sem vazar .env
- Configurar push sem senha via SSH
