<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class TorrentContributionException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status = 409)
    {
        parent::__construct('The torrent contribution could not be confirmed.');
    }
}
