<?php

namespace App\Models;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\Concerns\HasUuid;
use App\Models\Scopes\BelongsToUserScope;
use Database\Factories\AnomalyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $account_id
 * @property int|null $statement_id
 * @property AnomalyType $type
 * @property AnomalySeverity $severity
 * @property string $title
 * @property string $description
 * @property string|null $amount
 * @property array<int, int>|null $transaction_ids
 * @property array<string, mixed>|null $metadata
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'account_id', 'statement_id', 'type', 'severity', 'title', 'description',
    'amount', 'transaction_ids', 'metadata', 'status',
])]
#[ScopedBy([BelongsToUserScope::class])]
class Anomaly extends Model
{
    /** @use HasFactory<AnomalyFactory> */
    use HasFactory;

    use HasUuid;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnomalyType::class,
            'severity' => AnomalySeverity::class,
            'amount' => 'decimal:2',
            'transaction_ids' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * The account this anomaly belongs to.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The statement this anomaly was detected in.
     *
     * @return BelongsTo<Statement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(Statement::class);
    }
}
