# Deploy das correções de segurança (2026-08-18)

Runbook para aplicar em staging e, depois, em produção as correções de:

1. Injeção de iframe via campo `video` de combo ([app/Services/VideoEmbedResolver.php](../app/Services/VideoEmbedResolver.php))
2. Senhas de jogo/lista/combo armazenadas em texto puro ([app/Services/GamePasswordChecker.php](../app/Services/GamePasswordChecker.php), controllers relacionados)
3. Falta de rate limiting nos endpoints protegidos por senha ([routes/web.php](../routes/web.php))
4. Headers de segurança ausentes ([app/Http/Middleware/SecurityHeaders.php](../app/Http/Middleware/SecurityHeaders.php))

A peça crítica é a migration `2026_08_18_000000_hash_plaintext_passwords`, que amplia as colunas
`game.globalPass`, `list.password` e `combo.password` de `VARCHAR(16)` para `VARCHAR(255)` e
re-hasheia com bcrypt tudo que ainda estiver em texto puro. **É unidirecional**: `down()` só desfaz
a largura da coluna, não recupera o texto original. Ela é **idempotente** — pula qualquer valor que já
comece com `$2y$`, então pode ser reexecutada com segurança se cair no meio.

Validado localmente em 2026-08-18 contra ~3.100 senhas de combo + 67 de lista + 40 de jogo: rodou em
~10 minutos (bcrypt custo 12 é proposicionalmente lento) e zerou o total de senhas não-hasheadas.

## Antes de rodar em qualquer ambiente

- [ ] Confirmar que o código já está commitado/mergeado no branch de deploy do ambiente
- [ ] Confirmar acesso SSH (evitar rodar via terminal do hPanel no navegador — sessões de ~10min
      correm risco de a aba cair antes de terminar; se só houver terminal web, use `nohup` + `disown`
      conforme abaixo)
- [ ] **Backup do banco antes de qualquer coisa** — a etapa de hash é irreversível

## Passo a passo

### 1. Backup do banco

```bash
mysqldump -u USUARIO -p NOME_DO_BANCO > backup_pre_security_fix_$(date +%Y%m%d_%H%M).sql
```

Em produção, prefira um snapshot completo (hPanel costuma ter backup automático de banco+arquivos —
confirme que existe um recente antes de prosseguir, além deste dump manual).

### 2. Deploy do código

```bash
cd /caminho/da/app/laravel
git pull origin <branch-do-ambiente>   # stagging para staging; branch/tag de produção quando for a vez
composer install --no-dev --optimize-autoloader
```

Nenhuma dependência nova foi adicionada (a migration usa `DB::statement` bruto em vez de
`Schema::change()`, então não precisa de `doctrine/dbal`).

### 3. Modo de manutenção

Evita que alguém tente validar uma senha no meio da migração (linhas ainda não hasheadas falham a
comparação até serem processadas).

```bash
php artisan down --secret="escolha-um-token-aqui"
```

Use `--secret` para poder acessar `/escolha-um-token-aqui` e testar antes de reabrir para todos.

### 4. Rodar a migration

```bash
nohup php artisan migrate --force > /tmp/migrate_security.log 2>&1 &
disown
tail -f /tmp/migrate_security.log
```

- `--force` é obrigatório porque `APP_ENV` de staging/produção bloqueia `migrate` interativo.
- `nohup ... & disown` mantém o processo rodando mesmo se a sessão SSH/terminal cair.
- `Ctrl+C` no `tail -f` só sai do acompanhamento, não mata a migration.
- Se a sessão cair de verdade e não houver certeza se terminou, rode `php artisan migrate:status`
  (mostra `[3] Ran` quando concluída) — ou apenas rode `migrate --force` de novo; é seguro por ser
  idempotente.

Estimativa de tempo: proporcional ao nº de linhas em `combo`/`list`/`game` com senha preenchida.
Em staging foram ~3.200 linhas em ~10 min. Antes de rodar em produção, meça o volume:

```bash
php artisan tinker --execute="
echo DB::table('combo')->whereNotNull('password')->where('password','!=','')->count().' combos'.PHP_EOL;
echo DB::table('list')->whereNotNull('password')->where('password','!=','')->count().' lists'.PHP_EOL;
echo DB::table('game')->whereNotNull('globalPass')->where('globalPass','!=','')->count().' games'.PHP_EOL;
"
```

e estime `tempo ≈ (linhas_produção / linhas_staging) × 10min`. Se for muito maior, considere rodar
fora do horário de pico e avisar os usuários com antecedência.

### 5. Limpar caches

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### 6. Verificar

```bash
php artisan migrate:status | tail -5   # deve mostrar hash_plaintext_passwords como Ran

php artisan tinker --execute="
\$col = DB::select('SHOW COLUMNS FROM combo LIKE \'password\'');
echo 'combo.password: '.\$col[0]->Type.PHP_EOL;
echo 'combo ainda em texto puro: '.DB::table('combo')->whereNotNull('password')->where('password','!=','')->where('password','not like','\$2y\$%')->count().PHP_EOL;
echo 'list ainda em texto puro: '.DB::table('list')->whereNotNull('password')->where('password','!=','')->where('password','not like','\$2y\$%')->count().PHP_EOL;
echo 'game ainda em texto puro: '.DB::table('game')->whereNotNull('globalPass')->where('globalPass','!=','')->where('globalPass','not like','\$2y\$%')->count().PHP_EOL;
"
```

Os três contadores "ainda em texto puro" devem ser `0`.

### 7. Sair do modo de manutenção

```bash
php artisan up
```

### 8. Teste funcional (com o site já público de novo)

- [ ] Editar um jogo/lista existente com a senha real que já era usada antes → deve continuar
      funcionando normalmente (o valor foi re-hasheado, não trocado)
- [ ] Enviar uma senha errada de propósito 11+ vezes seguidas num mesmo endpoint → a partir da
      tentativa 11 deve vir `429 Too Many Requests` (rate limit `throttle:10,1`)
- [ ] Submeter um combo com `video` = `javascript:alert(1)//streamable.com/https` (ou qualquer URL
      não-https/não-whitelisted) → a página do combo não deve renderizar nenhum `<iframe>` com esse
      valor
- [ ] `curl -I https://<ambiente>/` → deve trazer `X-Frame-Options`, `X-Content-Type-Options`,
      `Referrer-Policy`, `Content-Security-Policy: frame-ancestors 'self'` (e `Strict-Transport-Security`
      já que é HTTPS)

## Diferenças para produção

- Volume de dados é maior → confirme a estimativa de tempo do passo 4 antes de começar, e escolha
  uma janela de baixo tráfego
- Backup deve ser o procedimento completo de produção (banco + arquivos), não só o `mysqldump` manual
- Rodar em staging primeiro (este runbook) serve como ensaio: se algo comportar diferente do
  esperado aqui, pare e investigue antes de tocar em produção
- Se algo der errado a meio da migração em produção e for necessário abortar: restaurar o backup do
  passo 1 é a única forma de voltar ao texto puro (a migration em si não desfaz o hash); depois disso
  reavalie antes de tentar de novo
