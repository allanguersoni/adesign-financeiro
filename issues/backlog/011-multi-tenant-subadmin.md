# Issue 011 — Multi-tenant + Sub-administradores

## Descrição
Transformar o sistema em SaaS multi-tenant com isolamento por empresa.
Cada empresa tem seu admin, seus clientes e sua identidade visual (white-label).

## Por que importante
- Permite vender o sistema como SaaS para múltiplas agências/freelancers
- `super_admin` (você) gerencia todas as empresas sem acesso aos dados entre elas
- White-label permite que cada empresa use sua própria marca

## Arquivos a modificar
- `app/migrate_v8.php` — adicionar `empresa_id` em `clientes`, `pagamentos`, `usuarios`; criar tabela `empresas`
- `app/config/auth.php` — `require_auth()` filtra por `empresa_id` da sessão
- `app/config/conexao.php` — eventual row-level security helper
- `app/config/settings.php` — `setting()` filtra por `empresa_id`
- Todos os `SELECT` em pages/actions — adicionar `WHERE empresa_id = ?`
- `app/configuracoes.php` — seção White-label (logo upload, cor primária)
- `app/includes/header.php` — logo e `primary` color dinâmicos por empresa
- `app/actions/salvar_config.php` — salvar configs por empresa
- `app/actions/salvar_usuario.php` — criar usuário vinculado à empresa

## Aceite
- [ ] Tabela `empresas` criada com: `id`, `nome`, `logo_url`, `cor_primaria`, `slug`, `ativo`
- [ ] Todas as tabelas de dados têm `empresa_id` com FK e índice
- [ ] Login isola dados: usuário da empresa A nunca vê dados da empresa B
- [ ] Role `super_admin` acessa painel de gestão de empresas
- [ ] Role `admin_empresa` gerencia usuários e configurações da própria empresa
- [ ] White-label: logo e cor primária carregados dinamicamente no header
- [ ] Migração `migrate_v8.php` não perde dados existentes (empresa_id = 1 para todos)
