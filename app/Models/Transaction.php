<?php

namespace App\Models;

use App\Enums\TransactionDirection;
use App\Models\Concerns\HasUuid;
use App\Models\Scopes\BelongsToUserScope;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $statement_id
 * @property int $account_id
 * @property Carbon $date
 * @property string $description
 * @property string $amount
 * @property TransactionDirection $direction
 * @property string|null $running_balance
 * @property string|null $reference
 * @property string|null $merchant
 * @property string|null $category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'statement_id', 'account_id', 'date', 'description', 'amount', 'direction',
    'running_balance', 'reference', 'merchant', 'category',
])]
#[ScopedBy([BelongsToUserScope::class])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
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
            'date' => 'date',
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'direction' => TransactionDirection::class,
        ];
    }

    /**
     * The statement this transaction belongs to.
     *
     * @return BelongsTo<Statement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(Statement::class);
    }

    /**
     * The account this transaction belongs to.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
