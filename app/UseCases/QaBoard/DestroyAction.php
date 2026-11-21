<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドの物理削除ユースケース。配下の回答は外部キーの cascade で連動して消える。
 *
 * 「回答が 1 件以上あるスレッドは投稿者本人が削除できない」判定は呼び出し側(Controller)で行う。
 * 管理者のモデレーション削除は回答の有無に関係なく、この Action を呼ぶ。
 */
final class DestroyAction
{
    public function __invoke(QaThread $thread): void
    {
        DB::transaction(fn () => $thread->delete());
    }
}
