<?php

namespace App\Models;

use App\Enums\SequenceType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * A single sequence counter row per (company_id, type).
 *
 * Used by SequenceService to generate concurrency-safe document numbers.
 * Records are locked with SELECT ... FOR UPDATE before incrementing.
 */
class Sequence extends Model
{
    use BelongsToCompany;

    /** @var bool No timestamps needed on a counter table. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'type',
        'last_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SequenceType::class,
        ];
    }
}
