# FileVault

![CI](https://github.com/danwson/FileVault/actions/workflows/ci.yml/badge.svg)

Serviço de upload, armazenamento e compartilhamento seguro de arquivos, com
links de download temporários e auditoria de acesso.

## Objetivo

Projeto pessoal de portfólio, construído em etapas para praticar — de ponta
a ponta e num cenário realista — o que normalmente fica espalhado em vários
tutoriais isolados: containerização de uma aplicação Laravel, integração
com armazenamento compatível com S3, pipeline de CI/CD e, na etapa final,
deploy real na AWS.

Ao final, o sistema permite que um usuário se registre, faça upload de
arquivos, gere links de compartilhamento temporários (com expiração
configurável) e tenha visibilidade de quem acessou cada arquivo — com toda
a infraestrutura (aplicação, banco, fila, storage) rodando em containers e
publicada via pipeline automatizado.

## Funcionalidades (v1)

- Registro, login e logout
- Upload de arquivo, armazenado via driver S3-compatible (MinIO)
- Listagem dos arquivos do usuário
- Download via link gerado sob demanda (presigned URL)
- Exclusão de arquivo (remove do storage, não só do banco)
- Geração de link de compartilhamento com expiração configurável
- Log de eventos de acesso (upload, download, acesso ao link)

Fora do escopo da v1: pastas/organização em árvore, compartilhamento entre
usuários, permissões granulares.

## Stack

- **Laravel 13** + **PHP 8.3**
- **MySQL** 8.4
- **MinIO** (compatível com S3, roda localmente sem custo/conta AWS)
- **Redis** (fila / eventos assíncronos)
- **Sanctum** (autenticação)
- **Pest** (testes)
- **Docker / Docker Compose**
- **GitHub Actions** (CI: testes + build/push de imagem para o GHCR)

> Nota sobre versão: o plano original previa Laravel 11, mas na data em que
> este projeto foi iniciado o 11.x já estava fora da janela de suporte de
> segurança (CVE-2026-48019, sem correção retroativa). Optou-se por ir
> direto para o Laravel 13 (LTS mais recente), mantendo PHP 8.3.

## Status atual: Etapa 4 concluída (adiantada: Etapa 7 também)

- [x] Projeto Laravel criado, Pest como test runner
- [x] `docker-compose.yml` com `app`, `webserver` (Nginx), `mysql`, `redis`,
      `minio` e um job `minio-init` que cria o bucket automaticamente
- [x] Dockerfile multi-stage (etapa `vendor` + etapa `app` com PHP-FPM 8.3
      Alpine), imagem otimizada (225MB → 207MB)
- [x] `HEALTHCHECK` em todos os serviços, com `depends_on: condition:
      service_healthy` respeitando a ordem real de disponibilidade
- [x] CI no GitHub Actions: testes + migrations contra MySQL real a cada
      push/PR, e build/publicação da imagem no GHCR a cada push em `main`
- [x] Autenticação via Sanctum: registro, login, logout (revoga só o
      token da própria requisição), `/api/me` e rate limiting no login
      (`tests/Feature/AuthTest.php`)
- [x] Upload de arquivo (`app/Models/File.php`) pro MinIO via disco `s3`:
      criar, listar (paginado, escopado ao dono), ver detalhe e apagar
      (do storage **e** do banco). Autorização via `FilePolicy` — usuário
      nunca acessa/apaga arquivo de outro (`tests/Feature/FileTest.php`)
- [ ] Ainda **sem** links de compartilhamento com expiração — isso vem na
      próxima etapa

### Roteiro do projeto

1. ~~Setup Laravel + Docker Compose (app, MySQL, Redis, MinIO)~~ ✅
2. ~~Multi-stage build, healthchecks, otimização de imagem~~ ✅
3. ~~Auth (Sanctum)~~ ✅
4. ~~Upload de arquivo (entidade `File`) pro MinIO~~ ✅
5. `ShareLink` com presigned URLs e expiração
6. `AccessLog` e eventos assíncronos (fila Redis)
7. ~~CI/CD com GitHub Actions~~ ✅ (adiantado)
8. Migração de MinIO para S3 real na AWS
9. Consolidação, documentação, decisão sobre certificação

## Rodando o ambiente

Pré-requisito: Docker Desktop (ou outro engine compatível com Docker Compose
v2) instalado e rodando.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

| Serviço           | URL local              |
| ----------------- | ----------------------- |
| Aplicação (Nginx) | http://localhost:8000   |
| MySQL             | localhost:3306           |
| Redis             | localhost:6380 (porta alternativa para evitar conflito com um Redis local já em uso) |
| MinIO API         | http://localhost:9000   |
| MinIO Console     | http://localhost:9001   |

Acompanhar o status dos healthchecks:

```bash
docker compose ps
```

As credenciais do MySQL, Redis e MinIO ficam no `.env` (veja
`.env.example`). O bucket configurado em `MINIO_BUCKET` é criado
automaticamente pelo serviço `minio-init` na primeira subida do ambiente.

## Rodando os testes

```bash
docker compose exec app ./vendor/bin/pest
```

Localmente (sem Docker), os testes usam SQLite em memória
(`phpunit.xml`), então também é possível rodar `./vendor/bin/pest`
diretamente com PHP/Composer instalados na máquina.

## CI/CD

A cada push/PR para `main`, o [workflow de CI](.github/workflows/ci.yml)
sobe um MySQL 8.4 efêmero, aplica as migrations nele, roda a suite Pest e
valida o estilo do código (Pint). A cada push em `main`, um segundo job
builda a imagem da aplicação e publica em
[`ghcr.io/danwson/filevault`](https://github.com/danwson/filevault/pkgs/container/filevault).
