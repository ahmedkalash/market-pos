<?php

namespace App\DTOs\Checkout;

use App\Enums\ExtraItemActionType;
use InvalidArgumentException;
use ValueError;

readonly class ExtraItemDTO
{
    public function __construct(
        public string $name,
        public ExtraItemActionType $actionType,
        public float $amount,
        public ?string $notes = null,
    ) {}

    /**
     * Create an ExtraItemDTO from an array.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     * @throws ValueError
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['name']) || blank($data['name'])) {
            throw new InvalidArgumentException('name is required.');
        }

        if (! isset($data['action_type']) || blank($data['action_type'])) {
            throw new InvalidArgumentException('action_type is required.');
        }

        if (! isset($data['amount']) || ! is_numeric($data['amount'])) {
            throw new InvalidArgumentException('amount is required.');
        }

        $actionType = match (true) {
            $data['action_type'] instanceof ExtraItemActionType => $data['action_type'],
            default => ExtraItemActionType::from((string) $data['action_type']),
        };

        return new self(
            name: trim((string) $data['name']),
            actionType: $actionType,
            amount: (float) $data['amount'],
            notes: isset($data['notes']) && filled($data['notes']) ? (string) $data['notes'] : null,
        );
    }
}
