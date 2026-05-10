<?php

namespace App\Services;

use App\Enums\SequenceType;
use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Generates concurrency-safe sequential document numbers per company.
 *
 * Uses SELECT ... FOR UPDATE on the sequences table to prevent two concurrent
 * requests from receiving the same number (race-condition-safe).
 *
 * Usage (must be called inside an existing DB::transaction):
 *   $number = SequenceService::make()->next(companyId: 1, type: 'purchase_invoice');
 *   // Returns: "PI-2024-00001"
 */
class SequenceService
{
    public static function make(): self
    {
        return app(static::class);
    }

    /**
     * Generate the next sequence number for a company and document type.
     *
     * This uses a pessimistic lock (lockForUpdate) on the sequence row
     * to ensure that concurrent requests do not get the same number.
     * This must be called inside a database transaction.
     */
    public function next(int $companyId, SequenceType $type): string
    {
        if (! DB::transactionLevel()) {
            throw new \RuntimeException('SequenceService::next must be called within a database transaction.');
        }

        // Lock the sequence row for this company + type to prevent concurrent reads
        $sequence = Sequence::query()
            ->lockForUpdate()
            ->firstOrCreate(
                ['company_id' => $companyId, 'type' => $type->value],
                ['last_number' => 0],
            );

        $sequence->increment('last_number');
        $sequence->refresh();

        return $this->format($type, $sequence->last_number);
    }

    /**
     * Format the number into a human-readable document reference.
     */
    private function format(SequenceType $type, int $number): string
    {
        $year = now()->year;
        $padded = str_pad((string) $number, 6, '0', STR_PAD_LEFT);
        $prefix = $type->getPrefix();

        return "{$prefix}-{$year}-{$padded}";
    }
}
