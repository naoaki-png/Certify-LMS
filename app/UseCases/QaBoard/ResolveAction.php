<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドを「解決済み」にするユースケース。解決日時を記録する。
 */
final class ResolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return DB::transaction(function () use ($thread) {
            $thread->status = QaThreadStatus::Resolved;
            $thread->resolved_at = now();
            $thread->save();

            return $thread;
        });
    }
}
