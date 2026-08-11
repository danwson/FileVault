# FileVault

Serviço de upload, armazenamento e compartilhamento seguro de arquivos, com
links de download temporários e auditoria de acesso.

Projeto pessoal de portfólio, construído em etapas seguindo um plano de
estudo estruturado (Docker avançado → CI/CD → AWS).

## Stack

- **Laravel 13** + **PHP 8.3**
- **MySQL** 8.4
- **MinIO** (compatível com S3, roda localmente sem custo/conta AWS)
- **Redis** (fila / eventos assíncronos)
- **Sanctum** (autenticação) — a partir da etapa 3
- **Pest** (testes)
- **Docker / Docker Compose**

> Nota sobre versão: o plano original previa Laravel 11, mas na data em que
> este projeto foi iniciado o 11.x já estava fora da janela de suporte de
> segurança (CVE-2026-48019, sem correção retroativa). Optou-se por ir
> direto para o Laravel 13 (LTS mais recente), mantendo PHP 8.3.

## Status atual: Etapa 1 — estrutura inicial

- [x] Projeto Laravel criado
- [x] Pest configurado como test runner
- [x] `docker-compose.yml` com `app`, `webserver` (Nginx), `mysql`, `redis`,
      `minio` e um job `minio-init` que cria o bucket automaticamente
- [x] Dockerfile multi-stage para a aplicação (etapa `vendor` +
      etapa `app` com PHP-FPM 8.3 Alpine)
- [ ] Ainda **sem** funcionalidades de negócio (auth, upload, share links,
      logs) — isso vem nas próximas etapas

### Roteiro do projeto

1. ~~Setup Laravel + Docker Compose (app, MySQL, Redis, MinIO)~~ ✅
2. Multi-stage build, healthchecks, otimização de imagem
3. Auth (Sanctum) + entidade `File` com upload básico pro MinIO
4. `ShareLink` com presigned URLs e expiração
5. `AccessLog` e eventos assíncronos (fila Redis)
6. CI/CD com GitHub Actions
7. Migração de MinIO para S3 real na AWS
8. Consolidação, documentação, decisão sobre certificação

Fora do escopo da v1: pastas/organização em árvore, compartilhamento entre
usuários, permissões granulares.

## Rodando o ambiente

Pré-requisito: Docker Desktop (ou outro engine compatível com Docker Compose
v2) instalado e rodando.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

| Serviço          | URL local                          |
| ---------------- | ----------------------------------- |
| Aplicação (Nginx)| http://localhost:8000               |
| MySQL            | localhost:3306                      |
| Redis            | localhost:6379                      |
| MinIO API        | http://localhost:9000               |
| MinIO Console     | http://localhost:9001               |

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
