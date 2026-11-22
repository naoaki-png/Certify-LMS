<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QaThreadPolicy
{
    /**
     * 受講生・コーチ向けの閲覧可否。管理者は `/admin/qa-board` 側を使うので、ここでは不可。
     *
     * 判定の順序が応答を決める: コーチの担当外(403)→ 公開停止中の資格(404)。
     */
    public function view(User $auth, QaThread $qaThread): Response|bool
    {
        if ($auth->role === UserRole::Admin) {
            return false;
        }

        if ($auth->role === UserRole::Coach
            && ! in_array($qaThread->certification_id, $auth->coachingCertificationIds(), true)) {
            return false;
        }

        if ($qaThread->certification->status !== CertificationStatus::Published) {
            return Response::denyAsNotFound();
        }

        return true;
    }

    /**
     * 新規投稿できるのは受講生のみ。
     */
    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Student;
    }

    /**
     * 編集は投稿者本人のみ。
     */
    public function update(User $auth, QaThread $qaThread): bool
    {
        return $auth->id === $qaThread->user_id;
    }

    /**
     * 削除: 管理者(モデレーション)は常に可。投稿者本人は回答が 0 件のときだけ可。
     */
    public function delete(User $auth, QaThread $qaThread): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        return $auth->id === $qaThread->user_id && ! $qaThread->replies()->exists();
    }

    /**
     * 解決済みにできるのは投稿者本人のみ。
     */
    public function resolve(User $auth, QaThread $qaThread): bool
    {
        return $auth->id === $qaThread->user_id;
    }

    /**
     * 未解決に戻せるのは投稿者本人のみ。
     */
    public function unresolve(User $auth, QaThread $qaThread): bool
    {
        return $auth->id === $qaThread->user_id;
    }
}
