<?php

namespace SpeechToTextPlugin\Policies;

use SpeechToTextPlugin\Models\Job as Model;
use User;

class Job
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(\User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(\User $user, Model $model): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(\User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(\User $user, Model $model): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(\User $user, Model $model): bool
    {
        return true;
    }
}
