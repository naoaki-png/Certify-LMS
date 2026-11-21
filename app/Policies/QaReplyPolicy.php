<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

class QaReplyPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $auth, QaThread $qaThread): bool
    {
        return match ($auth->role) {
            UserRole::Admin => false,
            UserRole::Coach => in_array($qaThread->certification_id, $auth->coachingCertificationIds(), true),
            UserRole::Student => true,
        };
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $auth, QaReply $qaReply): bool
    {
        return $auth->id === $qaReply->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $auth, QaReply $qaReply): bool
    {
        return $auth->id === $qaReply->user_id || $auth->role === UserRole::Admin;
    }
}
