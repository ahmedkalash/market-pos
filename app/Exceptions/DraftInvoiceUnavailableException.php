<?php

namespace App\Exceptions;

use Throwable;

class DraftInvoiceUnavailableException extends BusinessLogicException
{
    public function __construct(
        string $title = '',
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $title = $title ?: __('pos.draft_already_settled');
        $message = $message ?: __('pos.draft_already_settled_hint');

        parent::__construct($title, $message, $code, $previous);
    }

    public static function alreadySettled(): self
    {
        return new self(__('pos.draft_already_settled'), __('pos.draft_already_settled_hint'));
    }

    public static function notFound(): self
    {
        return new self(__('pos.draft_not_found'), __('pos.draft_not_found_hint'));
    }
}
