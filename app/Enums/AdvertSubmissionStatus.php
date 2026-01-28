<?php

namespace App\Enums;

class AdvertSubmissionStatus
{
    public const PENDING_DESIGN   = 'PENDING_DESIGN';
    public const DESIGN_DONE      = 'DESIGN_DONE';
    public const PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const REJECTED         = 'REJECTED';
    public const PUBLISHED        = 'PUBLISHED';

    public static function all(): array
    {
        return [
            self::PENDING_DESIGN,
            self::DESIGN_DONE,
            self::PENDING_APPROVAL,
            self::REJECTED,
            self::PUBLISHED,
        ];
    }
}
