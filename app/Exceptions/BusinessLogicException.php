<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

abstract class BusinessLogicException extends RuntimeException
{
    public function __construct(
        protected string $title = '',
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getTitle(): string
    {
        return $this->title ?: __('app.operation_failed');
    }
}
