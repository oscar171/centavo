<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Models\Concerns\HasUuid;
use App\Models\Scopes\OwnedByUserScope;
use Database\Factories\AccountFactory;
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
 * @property int $user_id
 * @property string $name
 * @property string $bank
 * @property AccountType|null $account_type
 * @property string|null $last_four
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'bank', 'account_type', 'last_four', 'currency'])]
#[ScopedBy([OwnedByUserScope::class])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
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
            'account_type' => AccountType::class,
        ];
    }

    /**
     * The user that owns the account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The statements uploaded to this account.
     *
     * @return HasMany<Statement, $this>
     */
    public function statements(): HasMany
    {
        return $this->hasMany(Statement::class);
    }

    /**
     * The transactions belonging to this account.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The anomalies detected for this account.
     *
     * @return HasMany<Anomaly, $this>
     */
    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomaly::class);
    }
}
