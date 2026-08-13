<?php

namespace App\Contracts;

/**
 * Contrato comum entre os eventos que geram AccessLog (FileUploaded,
 * FileDownloaded, ShareLinkAccessed) — permite que um único listener
 * (LogAccessListener) trate os três sem precisar saber a estrutura
 * interna de cada evento.
 */
interface AccessLoggable
{
    /**
     * @return array{file_id: int, share_link_id: ?int, event_type: string, ip_address: string, user_agent: ?string}
     */
    public function logAttributes(): array;
}
