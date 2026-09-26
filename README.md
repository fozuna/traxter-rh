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

## Se a instalação falhar

O instalador valida senha do admin e conexão com o banco **antes** de gravar qualquer arquivo, e desfaz o `config.php` se algo falhar no meio. Se mesmo assim o `install.php` ficar bloqueado sem administrador criado:

1. No Gerenciador de Arquivos, apague `app/config/config.php` (e `storage/install.done`, se existir).
2. Abra o `install.php` de novo e repita. As tabelas já criadas são reaproveitadas, sem perda.

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

- Coloque o arquivo do logo em `uploads/marca/` (PNG, JPG ou WEBP; SVG não é aceito por segurança). Essa pasta não vai para o Git.
- Sem logo configurado, o sistema usa o logo TRAXTER. Cores inválidas voltam ao padrão.
- O contato de suporte exibido no manual fica na seção `suporte`.
- Não é preciso recompilar o CSS: as cores são aplicadas por variáveis CSS em tempo de execução.

## Scripts úteis

| Comando | Para quê |
|---|---|
| `php scripts/setup_full.php` | Setup local completo |
| `php scripts/reset_password_cli.php <email> <nova_senha>` | Redefinir senha (valida a política de senhas) |
| `php scripts/preflight.php` | Checagem antes de publicar (inclui local dos arquivos privados) |
| `php scripts/migrate_resumes.php [--apply]` | Migra currículos de instalações antigas para o padrão seguro |
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

## Currículos e arquivos privados

- Currículos são salvos com **nome aleatório** (sem dados do candidato) e só são entregues pelo download autenticado do painel, que gera o nome amigável `Candidato_Vaga.pdf`.
- A pasta de arquivos privados (currículos, logs, sessões) é definida em `storage.path` no `config.php`:
  - **Laragon / desenvolvimento:** deixe vazio (usa `storage/` do projeto).
  - **Produção:** use uma pasta **fora** da pasta pública do site, por exemplo `/home/USUARIO/traxter-rh-storage`. O `php scripts/preflight.php` avisa se isso não estiver configurado.
- O `.htaccess` da raiz bloqueia o acesso web a `app/`, `storage/`, `database/`, `scripts/`, `tests/`, arquivos ocultos e extensões sensíveis. Isso vale para Apache (padrão do Laragon e do cPanel). **Se usar Nginx, essas regras não se aplicam**: nesse caso o `storage.path` fora da pasta pública é obrigatório.
- A pasta `uploads/` (logos) não executa scripts nem aceita SVG.
- O instalador web (`install.php`) fica bloqueado assim que existe um `config.php` com banco configurado.

### Atualizando uma instalação antiga

Instalações anteriores salvavam currículos com o nome do candidato dentro de `storage/resumes`. Para migrar:

```bash
# 1. backup do banco e da pasta storage/resumes
# 2. configure storage.path no config.php (produção)
php scripts/migrate_resumes.php            # simulação: mostra o que será feito
php scripts/migrate_resumes.php --apply    # move, renomeia e atualiza o banco
```

## LGPD no portal de vagas

- O formulário de candidatura exige o aceite da **Política de Privacidade** (`/privacidade`). O aceite é gravado na tabela `lgpd_consentimentos` com data, IP, navegador e versão da política, e aparece no detalhe da candidatura no painel.
- A política usa os dados da seção `cliente` do `config.php`: `nome`, `cnpj`, `email_privacidade` (canal para pedidos dos candidatos) e `retencao_meses`.
- O texto é um **modelo**: revise com o jurídico antes do uso com candidatos reais. Ao alterar o texto, atualize `Consentimento::VERSAO_POLITICA`.
- CPF, telefone, nome e experiência dos candidatos são mascarados nos logs.

## Regras de segurança do repositório

- **Nunca** versionar `app/config/config.php`, dumps de banco ou arquivos de `storage/` e `uploads/`.
- Dados de candidatos (CPF, telefone, currículos) são dados pessoais sob a LGPD.
- Senhas iniciais são geradas aleatoriamente pelo setup; não existem senhas padrão no código.

## Publicar em subdomínio (cPanel)

Exemplo: `rh.traxter.com.br`.

1. **Subdomínio:** cPanel → *Domínios* → criar `rh.traxter.com.br` com raiz `public_html/rh` (ou `/home/USUARIO/rh.traxter.com.br`).
2. **SSL:** cPanel → *SSL/TLS Status* → *Run AutoSSL* para o subdomínio.
3. **PHP:** *MultiPHP Manager* → PHP 8.1 ou superior, com `pdo_mysql`, `mbstring` e `fileinfo`.
4. **Banco:** *MySQL Databases* → criar banco e usuário, e dar **todas as permissões** ao usuário no banco.
5. **Arquivos:** no GitHub, *Code → Download ZIP*; no *Gerenciador de Arquivos*, envie e extraia o ZIP **dentro** da raiz do subdomínio (os arquivos `index.php` e `.htaccess` devem ficar direto na raiz).
6. **Pasta privada:** crie `/home/USUARIO/traxter-rh-storage` (fora de `public_html`).
7. **Instalação:** acesse `https://rh.traxter.com.br/install.php`, informe o DSN `mysql:host=localhost;dbname=BANCO;charset=utf8mb4`, usuário e senha do banco, e-mails e o administrador (senha com 12+ caracteres, maiúscula, minúscula, número e símbolo). Ao concluir, o instalador se bloqueia e se remove.
8. **Ajustes finais** em `app/config/config.php` (pelo Gerenciador de Arquivos): seção `cliente` (nome, logo, cores) e `'storage' => ['path' => '/home/USUARIO/traxter-rh-storage']`.
9. **Conferência:** `https://rh.traxter.com.br/login` e `https://rh.traxter.com.br/vagas`. Teste que `https://rh.traxter.com.br/app/config/config.php` e `https://rh.traxter.com.br/database/schema.sql` retornam **403**.

Se o cPanel tiver *Terminal*, `php scripts/preflight.php` confere tudo de uma vez.

Deploy automatizado: `.cpanel.yml.example` (copie para `.cpanel.yml` e ajuste `DEPLOYPATH`).

## Publicar pela Hostinger (Git do hPanel)

1. hPanel → *Sites* → *Domínios / Subdomínios*: crie `rh.traxter.com.br`. A pasta do subdomínio precisa estar **vazia** (apague o `default.php`/`index.html` que a Hostinger cria).
2. hPanel → *Avançado* → *Git*:
   - Repositório privado: clique em **Gerar chave SSH**, copie e adicione no GitHub em *traxter-rh → Settings → Deploy keys* (somente leitura).
   - Repositório: `git@github.com:fozuna/traxter-rh.git` · Branch: `main` · Diretório: a pasta do subdomínio.
3. Crie o banco em *Bancos de Dados MySQL* e siga os passos 6 a 9 da seção cPanel (pasta privada, `install.php`, ajustes do `config.php`, conferência dos 403).
4. **Antes de usar com clientes, teste o redeploy:** faça um commit simples, clique em *Redeploy* e confirme que `app/config/config.php` continua lá. Guarde sempre uma cópia do `config.php` fora da pasta do site.
5. Deploy automático (webhook) é opcional: bom para a demonstração; para clientes, prefira redeploy manual, para controlar quando cada um recebe atualização.

A Hostinger usa LiteSpeed, que respeita as regras do `.htaccess` (inclusive o bloqueio da pasta `.git`).
