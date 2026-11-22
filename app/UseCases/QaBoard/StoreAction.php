<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドの新規投稿ユースケース。解決状態は DB の初期値(未解決)で作成する。
 */
final class StoreAction
{
    /**
     * @param array{certification_id: string, title: string, body: string} $validated QaBoard/StoreRequest::rules() で検証済
     */
    public function __invoke(User $author, array $validated): QaThread
    {
        return DB::transaction(fn () => QaThread::create([
            'certification_id' => $validated['certification_id'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'user_id' => $author->id,
        ]));
    }
}
