<?php

namespace App\Policies;

use App\Models\Statement;
use App\Models\User;

class StatementPolicy
{
    /**
     * Determine whether the user can view the statement.
     */
    public function view(User $user, Statement $statement): bool
    {
        return $statement->account->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the statement.
     */
    public function delete(User $user, Statement $statement): bool
    {
        return $statement->account->user_id === $user->id;
    }
}
