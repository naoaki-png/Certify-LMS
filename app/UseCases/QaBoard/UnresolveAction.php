<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドを「未解決」に戻すユースケース。解決日時は NULL に戻す。
 */
final class UnresolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return DB::transaction(function () use ($thread) {
            $thread->status = QaThreadStatus::Open;
            $thread->resolved_at = null;
            $thread->save();

            return $thread;
        });
    }
}
