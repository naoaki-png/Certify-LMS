<?php

declare(strict_types=1);

namespace App\UseCases\QaBoardReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドへの回答投稿ユースケース。解決済みのスレッドにも投稿できる。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated QaBoardReply/StoreRequest::rules() で検証済
     */
    public function __invoke(QaThread $thread, User $author, array $validated): QaReply
    {
        return DB::transaction(fn () => $thread->replies()->create([
            'body' => $validated['body'],
            'user_id' => $author->id,
        ]));
    }
}
