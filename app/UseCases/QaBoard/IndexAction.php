<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * 質問掲示板の一覧(受講生 / コーチ / 管理者モデレーション共通)のデータを準備する Action。
 *
 * 閲覧できるスレッドは、絞り込みの選択肢にも使う「閲覧できる資格」のスレッドだけ:
 * - 受講生: 公開中の全資格 / コーチ: 公開中かつ担当の資格 / 管理者: 公開停止中を含む全資格。
 * 絞り込み(資格・解決状態・キーワード)は AND。キーワードはタイトル・本文・回答本文の OR 部分一致。
 * 並び順は新着順(`created_at` 降順、同時刻は `id` 順)で 20 件ずつ。
 */
final class IndexAction
{
    public const PER_PAGE = 20;

    /**
     * @param array{certification_id?: ?string, status?: ?string, keyword?: ?string} $filters
     *
     * @return array{
     *     threads: LengthAwarePaginator<int, QaThread>,
     *     certifications: Collection<int, Certification>,
     *     filters: array{certification_id: string, status: string, keyword: string},
     *     publishedStatus: CertificationStatus,
     * }
     */
    public function __invoke(User $viewer, array $filters): array
    {
        $normalized = [
            'certification_id' => (string) ($filters['certification_id'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'keyword' => trim((string) ($filters['keyword'] ?? '')),
        ];

        $certifications = $this->viewableCertifications($viewer);

        $threads = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->when(
                $viewer->role !== UserRole::Admin,
                fn (Builder $query) => $query->whereIn('certification_id', $certifications->modelKeys()),
            )
            ->when(
                $normalized['certification_id'] !== '',
                fn (Builder $query) => $query->where('certification_id', $normalized['certification_id']),
            )
            ->when(
                $this->statusOf($normalized['status']) !== null,
                fn (Builder $query) => $query->where('status', $this->statusOf($normalized['status'])?->value),
            )
            ->when(
                $normalized['keyword'] !== '',
                fn (Builder $query) => $this->whereKeyword($query, $normalized['keyword']),
            )
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'threads' => $threads,
            'certifications' => $certifications,
            'filters' => $normalized,
            'publishedStatus' => CertificationStatus::Published,
        ];
    }

    /**
     * ロールごとに「閲覧できる資格」(絞り込みの選択肢 = 閲覧できるスレッドの範囲)を返す。
     *
     * @return Collection<int, Certification>
     */
    private function viewableCertifications(User $viewer): Collection
    {
        return Certification::query()
            ->when(
                $viewer->role !== UserRole::Admin,
                fn (Builder $query) => $query->published(),
            )
            ->when(
                $viewer->role === UserRole::Coach,
                fn (Builder $query) => $query->assignedTo($viewer),
            )
            ->orderBy('name')
            ->get();
    }

    private function statusOf(string $status): ?QaThreadStatus
    {
        return match ($status) {
            'unresolved' => QaThreadStatus::Open,
            'resolved' => QaThreadStatus::Resolved,
            default => null,
        };
    }

    /**
     * タイトル・本文・回答本文のいずれかに部分一致(OR)。LIKE のワイルドカードはエスケープする。
     *
     * @param Builder<QaThread> $query
     *
     * @return Builder<QaThread>
     */
    private function whereKeyword(Builder $query, string $keyword): Builder
    {
        $like = '%'.addcslashes($keyword, '\\%_').'%';

        return $query->where(
            fn (Builder $q) => $q
                ->where('title', 'like', $like)
                ->orWhere('body', 'like', $like)
                ->orWhereHas('replies', fn (Builder $r) => $r->where('body', 'like', $like)),
        );
    }
}
