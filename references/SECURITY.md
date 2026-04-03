# SECURITY.md — ADesign Financeiro

## Arquivos que NUNCA sobem para o Git
- .env               → credenciais DB + SMTP em texto plano
- cookies.txt        → restos de sessões de teste
- init.sql           → pode conter dados reais de clientes
- migrate.php        → apagar do servidor após instalação

## Checklist de Segurança por Action
- [ ] validate_csrf() na primeira linha
- [ ] require_can() com permissão correta
- [ ] sanitize_string() em todo input do usuário
- [ ] PDO prepare()->execute() sem concatenação
- [ ] Nenhum dado sensível no redirect ou URL
- [ ] set_flash() para feedback (nunca echo direto)

## Anti Brute Force
- Tabela: login_tentativas (ip_address, sucesso, criado_em)
- Bloqueio: 5 tentativas falhas = block por 15 minutos por IP

## Password Reset
- Token: bin2hex(random_bytes(32)) → enviado por e-mail
- Banco: armazena apenas hash SHA-256 do token
- Expiração: 30 minutos
- Anti-enumeration: resposta idêntica se e-mail existe ou não

## Bcrypt
- Algoritmo: PASSWORD_BCRYPT
- Cost: 12
- Nunca armazenar senha em texto plano
