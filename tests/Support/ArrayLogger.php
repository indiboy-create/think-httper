<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper\Tests\Support;

use Psr\Log\AbstractLogger;

class ArrayLogger extends AbstractLogger
{
    public array $records = [];

    public array $channels = [];

    public function channel(string $name): static
    {
        $this->channels[] = $name;

        return $this;
    }

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}