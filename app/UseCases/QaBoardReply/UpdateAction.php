<?php

declare(strict_types=1);

namespace App\UseCases\QaBoardReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 回答の更新ユースケース。更新できるのは本文のみ。
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated QaBoardReply/UpdateRequest::rules() で検証済
     */
    public function __invoke(QaReply $reply, array $validated): QaReply
    {
        return DB::transaction(function () use ($reply, $validated) {
            $reply->update(['body' => $validated['body']]);

            return $reply;
        });
    }
}
