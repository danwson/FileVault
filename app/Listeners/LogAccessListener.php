<?php

namespace App\Listeners;

use App\Contracts\AccessLoggable;
use App\Jobs\LogAccessJob;

/**
 * Um único listener pros três eventos de acesso (FileUploaded,
 * FileDownloaded, ShareLinkAccessed) — todos implementam AccessLoggable,
 * então o listener não precisa saber a estrutura interna de cada um.
 *
 * Só despacha o job pra fila (Redis) e retorna na hora — a escrita real
 * no banco acontece em background, no LogAccessJob, sem atrasar a
 * resposta da requisição que disparou o evento.
 */
class LogAccessListener
{
    public function handle(AccessLoggable $event): void
    {
        LogAccessJob::dispatch($event->logAttributes());
    }
}
