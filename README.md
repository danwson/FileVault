# FileVault

![CI](https://github.com/danwson/FileVault/actions/workflows/ci.yml/badge.svg)

Serviço de upload, armazenamento e compartilhamento seguro de arquivos, com
links de download temporários e auditoria de acesso.

## Destaques técnicos

- **Arquitetura assíncrona real**: eventos → listeners → jobs processados por um worker dedicado via fila Redis, não processamento simulado
- **Presigned URLs geradas sob demanda**: nunca persistidas, validadas empiricamente contra a AWS real (assinatura e timestamp distintos a cada acesso)
- **Concorrência tratada corretamente**: contagem de acessos via UPDATE atômico, testada contra corrida real entre requisições simultâneas
- **CI/CD completo**: testes, lint e build/push de imagem versionada (GHCR) a cada push
- **Storage trocável por variável de ambiente**: mesmo código roda contra MinIO (dev) ou S3 real (validação), sem alterar uma linha
- **Segurança por design**: IAM com política de menor privilégio, bucket sem acesso público, rate limiting, autorização por policy em cada recurso

## Objetivo

Projeto pessoal de portfólio, construído em etapas para praticar — de ponta
a ponta e num cenário realista — o que normalmente fica espalhado em vários
tutoriais isolados: containerização de uma aplicação Laravel, integração
com armazenamento compatível com S3, pipeline de CI/CD e validação real
contra a AWS. O objetivo é validar a teoria e construir material de
portfólio — não manter um deploy real/produtivo rodando na AWS (ver
[MinIO vs. S3 real](#minio-vs-s3-real)).

Ao final, o sistema permite que um usuário se registre, faça upload de
arquivos, gere links de compartilhamento temporários (com expiração
configurável) e tenha visibilidade de quem acessou cada arquivo — com toda
a infraestrutura (aplicação, banco, fila, storage) rodando em containers e
publicada via pipeline automatizado.

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

## Funcionalidades implementadas

- Registro, login, logout e perfil autenticado (Sanctum), com rate limiting no login
- Upload, listagem paginada, detalhe e exclusão de arquivos — autorização por dono via `FilePolicy`, sem órfãos no storage
- Links de compartilhamento com expiração e limite de usos configuráveis, distinguindo `404` (token inexistente) de `410` (expirado/esgotado)
- Auditoria assíncrona de acessos (upload, download, acesso a link), com endpoint de consulta paginado
- Pipeline de CI/CD: testes automatizados, lint (Pint), build e publicação de imagem versionada no GHCR
- Validado de ponta a ponta contra AWS S3 real (upload, download, delete, compartilhamento e auditoria)

Fora do escopo da v1: pastas/organização em árvore, compartilhamento entre
usuários, permissões granulares.

### Roteiro do projeto

1. ~~Setup Laravel + Docker Compose (app, MySQL, Redis, MinIO)~~ ✅
2. ~~Multi-stage build, healthchecks, otimização de imagem~~ ✅
3. ~~Auth (Sanctum)~~ ✅
4. ~~Upload de arquivo (entidade `File`) pro MinIO~~ ✅
5. ~~`ShareLink` com presigned URLs e expiração~~ ✅
6. ~~`AccessLog` e eventos assíncronos (fila Redis)~~ ✅
7. ~~CI/CD com GitHub Actions~~ ✅ (adiantado)
8. ~~Migração de MinIO para S3 real na AWS~~ ✅ (validação, sem deploy mantido)

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

O serviço `queue-worker` (mesma imagem do `app`, rodando `queue:work` em
vez de `php-fpm`) processa os jobs assíncronos de `AccessLog` — sem ele,
os eventos de upload/download/acesso a link ficam parados na fila do
Redis e nunca viram registro no banco.

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

## MinIO vs. S3 real

O disco `s3` da aplicação (`config/filesystems.php`, usado explicitamente
via `Storage::disk('s3')` em `FileController`/`ShareLinkController`) fala
o mesmo protocolo S3 independentemente de apontar pro MinIO local ou pro
S3 real da AWS — a troca é só de variáveis de ambiente, nenhuma linha de
código muda.

**Decisão de arquitetura:**

- **MinIO** (`docker-compose.yml`) é o storage de **desenvolvimento e
  CI** — zero custo, zero conta AWS necessária, é o que roda por padrão
  ao clonar o repositório e o que os testes automatizados usam (via
  `Storage::fake('s3')`, nem chega a bater no MinIO de verdade)
- **S3 real** foi usado só pra **validar a integração de ponta a ponta**
  contra a AWS de verdade (upload, download via presigned URL, delete
  sem órfão no bucket, `ShareLink` e `AccessLog` assíncrono) — não fica
  rodando continuamente. O objetivo deste projeto é validar a teoria e
  gerar material de portfólio, não manter uma instância produtiva no ar
- O **CI não testa contra AWS real** de propósito — evitar guardar
  credenciais reais da AWS no GitHub Actions só pra isso, e evitar
  custo/flakiness em runs automáticos. A suíte de testes já cobre a
  lógica da aplicação com o disco fake; a integração real foi validada
  manualmente
- Bucket e usuário IAM foram criados manualmente pelo console da AWS
  (sem Terraform/CDK) — infraestrutura como código seria o próximo passo
  natural se isso virasse um deploy real, mas está fora do escopo aqui

**Para rodar contra S3 real:** ver o bloco comentado em
[`.env.example`](.env.example) (procure por "Rodando contra S3 real").
Nunca commitar as credenciais reais — elas ficam só no `.env` local
(`.gitignore`).

**Permissão IAM mínima** usada (escopada só ao bucket, não à conta
inteira):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "FileVaultBucketObjectAccess",
      "Effect": "Allow",
      "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"],
      "Resource": "arn:aws:s3:::<nome-do-bucket>/*"
    },
    {
      "Sid": "FileVaultBucketListAccess",
      "Effect": "Allow",
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::<nome-do-bucket>"
    }
  ]
}
```
