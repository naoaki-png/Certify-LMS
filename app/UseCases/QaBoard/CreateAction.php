<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\Certification;
use Illuminate\Database\Eloquent\Collection;

/**
 * 質問投稿フォームの資格セレクトに出す、公開中の資格一覧を返す Action。
 */
final class CreateAction
{
    /**
     * @return Collection<int, Certification>
     */
    public function __invoke(): Collection
    {
        return Certification::query()->published()->orderBy('name')->get();
    }
}
