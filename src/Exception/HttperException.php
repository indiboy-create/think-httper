<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper\Exception;

use RuntimeException;
use Throwable;
use Youvision\ThinkHttper\Response;

class HttperException extends RuntimeException
{
    protected ?Response $response;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Response $response = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}