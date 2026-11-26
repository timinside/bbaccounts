<?php
declare(strict_types=1);

namespace BBA\Services;

final class OAuthException extends \RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly int $status = 400
    ) {
        parent::__construct($description);
    }
}
