# TRAXTER RH — Recrutamento e Seleção

Sistema de recrutamento em PHP 8 (MVC próprio + PDO) com MySQL/MariaDB.

Funcionalidades: portal público de vagas, candidatura com currículo em PDF, funil de seleção (kanban), histórico por candidato, programa de indicação com controle de comissões, benefícios, perfis de acesso (admin, RH, visualização), recuperação de senha, política de senhas, limite de tentativas de login e trilha de auditoria.

> Modelo atual: **uma instalação e um banco por cliente**. A versão multiempresa (SaaS) é uma etapa futura.

## Requisitos

- PHP 8.1+ com `pdo_mysql`, `mbstring`, `fileinfo`
- MySQL 8+ ou MariaDB 10.4+
- Apache com `mod_rewrite` (o Laragon já vem pronto)

## Instalação local no Laragon

No **Terminal** do Laragon:

```bash
cd C:\laragon\www
git clone https://github.com/fozuna/traxter-rh.git
cd traxter-rh
php scripts/setup_full.php
```

O setup:
1. cria o banco `traxter_rh` e importa `database/schema.sql`;
2. gera `app/config/config.php` (fora do Git) apontando para esse banco;
3. cria o administrador `admin@traxter.local` com **senha aleatória exibida uma única vez**.

Depois clique em **Reload** no Laragon e acesse:

- Painel: http://traxter-rh.test/login
- Vagas públicas: http://traxter-rh.test/vagas

Opções: `php scripts/setup_full.php <banco> <email_admin>`. Se o MySQL tiver senha: `set DB_PASS=suasenha` antes do comando.

## Configuração

Tudo fica em **`app/config/config.php`** (modelo: `app/config/config.example.php`). Esse arquivo contém credenciais e **nunca deve ser versionado**.

Domínios `.test`, `.local` e `localhost` são tratados como desenvolvimento (erros visíveis). Em produção, os erros vão para `storage/logs/`.

## Configurar a instalação para um cliente

Cada cliente tem sua instalação e seu `app/config/config.php`. A identidade visual fica na seção `cliente`:

```php
'cliente' => [
    'nome' => 'Agro Exemplo Ltda',              // portal de vagas, títulos, exportações
    'logo' => 'uploads/marca/logo.png',         // versão clara do logo, para fundo escuro
    'site' => 'https://www.agroexemplo.com.br',
    'cores' => [
        'escuro' => '#14532d',                  // cabeçalho, menu lateral, títulos
        'medio'  => '#15803d',                  // botões e destaques
        'claro'  => '#4d7c0f',                  // detalhes, bordas, foco
    ],
],
```

- Coloque o arquivo do logo em `uploads/marca/` (PNG, SVG ou WEBP). Essa pasta não vai para o Git.
- Sem logo configurado, o sistema usa o logo TRAXTER. Cores inválidas voltam ao padrão.
- O contato de suporte exibido no manual fica na seção `suporte`.
- Não é preciso recompilar o CSS: as cores são aplicadas por variáveis CSS em tempo de execução.

## Scripts úteis

| Comando | Para quê |
|---|---|
| `php scripts/setup_full.php` | Setup local completo |
| `php scripts/reset_password_cli.php <email> <nova_senha>` | Redefinir senha (valida a política de senhas) |
| `php scripts/preflight.php` | Checagem antes de publicar |
| `for %f in (tests\php\*.php) do php %f` | Testes PHP (usam o banco configurado) |
| `npm run build:css` | Recompilar o Tailwind |

## Estrutura

```
app/
  config/       config.example.php (modelo) e config.php (local, fora do Git)
  controllers/  Controllers (sem SQL)
  core/         Router, Auth, Security, Database, Logger, Upload, Mailer
  models/       Todo o acesso a banco (PDO + prepared statements)
  views/        Templates PHP + Tailwind
database/
  schema.sql    Estrutura completa, sem dados
  migrations/   Ajustes incrementais
scripts/        Setup, deploy e utilitários de linha de comando
storage/        Currículos, logs, sessões (fora do Git)
tests/          Testes PHP e Playwright
```

## Regras de segurança do repositório

- **Nunca** versionar `app/config/config.php`, dumps de banco ou arquivos de `storage/` e `uploads/`.
- Dados de candidatos (CPF, telefone, currículos) são dados pessoais sob a LGPD.
- Senhas iniciais são geradas aleatoriamente pelo setup; não existem senhas padrão no código.

## Deploy (cPanel)

Use `.cpanel.yml.example` como base: copie para `.cpanel.yml`, ajuste `DEPLOYPATH` para a conta do cliente e crie o `app/config/config.php` diretamente no servidor. Guia complementar em `DEPLOY_RAPIDO.md` e `INSTALACAO_WEB.md`.
