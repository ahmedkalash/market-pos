<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Validation\Validator;

/**
 * Standardized Operation Result Envelope for RPC calls (Livewire <-> Alpine / API).
 *
 * Provides a predictable, strictly-typed payload schema across the frontend/backend boundary:
 *
 * ### Success Response Example:
 * ```
 * {
 *   "success": true,
 *   "data": {
 *     "id": 15,
 *     "name": "Express Delivery",
 *     "cost": 35.00
 *   },
 *   "message": "Shipping destination created successfully.",
 *   "errors": {}
 * }
 * ```
 *
 * ### Error Response Example (Multi-field & multi-error granularity):
 * ```
 * {
 *   "success": false,
 *   "data": null,
 *   "message": "The destination name field is required.",
 *   "errors": {
 *     "name": [
 *       "The destination name field is required.",
 *       "The destination name must be at least 3 characters."
 *     ],
 *     "cost": [
 *       "The shipping cost must be a positive number."
 *     ]
 *   }
 * }
 * ```
 *
 * @phpstan-type RpcResponseArray array{
 *     success: bool,
 *     data: mixed,
 *     message: ?string,
 *     errors: array<string, array<int, string>>
 * }
 */
final readonly class RpcResponse implements Arrayable
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $message = null,
        public array $errors = [],
    ) {}

    /**
     * Build a success response envelope.
     *
     * @return array{success: true, data: mixed, message: ?string, errors: array<string, array<int, string>>}
     */
    public static function success(mixed $data = null, ?string $message = null): array
    {
        return (new self(
            success: true,
            data: $data,
            message: $message,
            errors: [],
        ))->toArray();
    }

    /**
     * Build an error response envelope.
     *
     * @param  array<string, array<int, string>|string>  $errors
     * @return array{success: false, data: mixed, message: ?string, errors: array<string, array<int, string>>}
     */
    public static function error(?string $message = null, array $errors = [], mixed $data = null): array
    {
        $normalizedErrors = self::normalizeErrors($errors);

        return (new self(
            success: false,
            data: $data,
            message: $message ?? ($normalizedErrors ? reset($normalizedErrors)[0] ?? null : null),
            errors: $normalizedErrors,
        ))->toArray();
    }

    /**
     * Normalize the errors dictionary into a consistent array of string lists.
     *
     * 1. Casts single string errors into arrays so the frontend schema is always { [field]: string[] }.
     * 2. Uses array_values() to guarantee 0-indexed consecutive keys, preventing PHP's json_encode()
     *    from converting non-consecutive arrays into JSON objects ({"1": "..."}) instead of JSON arrays.
     *
     * @param  array<string, array<int, string>|string>  $errors
     * @return array<string, array<int, string>>
     */
    private static function normalizeErrors(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $field => $messages) {
            $normalized[$field] = array_values((array) $messages);
        }

        return $normalized;
    }

    /**
     * Build an error response envelope directly from a Laravel Validator instance.
     *
     * @return array{success: false, data: mixed, message: ?string, errors: array<string, array<int, string>>}
     */
    public static function fromValidator(Validator $validator, ?string $customMessage = null): array
    {
        /** @var array<string, array<int, string>> $errors */
        $errors = $validator->errors()->toArray();

        return self::error(
            message: $customMessage ?? $validator->errors()->first(),
            errors: $errors,
        );
    }

    /**
     * Convert the response instance to an array.
     *
     * @return RpcResponseArray
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->normalizeData($this->data),
            'message' => $this->message,
            'errors' => $this->errors,
        ];
    }

    /**
     * Recursively normalize data into primitive PHP types and arrays.
     */
    private function normalizeData(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            return $this->normalizeData($value->toArray());
        }

        if ($value instanceof \JsonSerializable) {
            return $this->normalizeData($value->jsonSerialize());
        }

        if ($value instanceof \stdClass) {
            return $this->normalizeData((array) $value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalizeData($item), $value);
        }

        return $value;
    }
}
