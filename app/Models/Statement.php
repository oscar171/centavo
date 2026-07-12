<?php

namespace App\Models;

use App\Enums\StatementStatus;
use App\Models\Concerns\HasUuid;
use App\Models\Scopes\BelongsToUserScope;
use Database\Factories\StatementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $account_id
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property string|null $beginning_balance
 * @property string|null $ending_balance
 * @property string|null $total_deposits
 * @property string|null $total_withdrawals
 * @property string $original_filename
 * @property string|null $file_path
 * @property StatementStatus $status
 * @property bool $is_reconciled
 * @property string|null $reconciliation_diff
 * @property string|null $failure_reason
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'account_id', 'period_start', 'period_end', 'beginning_balance', 'ending_balance',
    'total_deposits', 'total_withdrawals', 'original_filename', 'file_path', 'status',
    'is_reconciled', 'reconciliation_diff', 'failure_reason', 'processed_at',
])]
#[ScopedBy([BelongsToUserScope::class])]
class Statement extends Model
{
    /** @use HasFactory<StatementFactory> */
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
            'period_start' => 'date',
            'period_end' => 'date',
            'beginning_balance' => 'decimal:2',
            'ending_balance' => 'decimal:2',
            'total_deposits' => 'decimal:2',
            'total_withdrawals' => 'decimal:2',
            'reconciliation_diff' => 'decimal:2',
            'is_reconciled' => 'boolean',
            'status' => StatementStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The account this statement belongs to.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The transactions extracted from this statement.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The anomalies detected for this statement.
     *
     * @return HasMany<Anomaly, $this>
     */
    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomaly::class);
    }
}
