<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドの更新ユースケース。更新できるのはタイトルと本文のみ(資格は変更不可)。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, body: string} $validated QaBoard/UpdateRequest::rules() で検証済
     */
    public function __invoke(QaThread $thread, array $validated): QaThread
    {
        return DB::transaction(function () use ($thread, $validated) {
            $thread->update([
                'title' => $validated['title'],
                'body' => $validated['body'],
            ]);

            return $thread;
        });
    }
}
