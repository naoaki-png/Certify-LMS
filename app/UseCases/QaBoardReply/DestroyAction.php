<?php

declare(strict_types=1);

namespace App\UseCases\QaBoardReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 回答の物理削除ユースケース。投稿者本人の削除と、管理者のモデレーション削除で共通。
 */
final class DestroyAction
{
    public function __invoke(QaReply $reply): void
    {
        DB::transaction(fn () => $reply->delete());
    }
}
