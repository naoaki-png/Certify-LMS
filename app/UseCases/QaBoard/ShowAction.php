<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaThread;

/**
 * スレッド詳細(受講生 / コーチ / 管理者モデレーション共通)の表示データを準備する Action。
 *
 * 回答は全件を古い順(`created_at` 昇順、同時刻は `id` 順)で投稿者ごと eager load する(N+1 回避)。
 */
final class ShowAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return $thread
            ->loadMissing(['user', 'certification'])
            ->load([
                'replies' => fn ($query) => $query
                    ->with('user')
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
            ->loadCount('replies');
    }
}
