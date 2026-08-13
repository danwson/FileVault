<?php

namespace App\Enums;

enum AccessEventType: string
{
    case Upload = 'upload';
    case Download = 'download';
    case ShareAccess = 'share_access';
}
