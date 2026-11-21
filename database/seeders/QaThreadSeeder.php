<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 開発用の質問掲示板シーダー。
 *
 * 公開中の資格ごとに、状態の網羅を担保する:
 * - 解決済み / 未解決(回答 0 件 = 未回答 / 回答あり = 対応中)が混在する
 * - 作成日時を日単位でばらつかせ、新着順と 20 件ずつのページネーションを確認できる(全体で 21 件以上)
 * - 固定の受講生(student@certify-lms.test)の投稿を含む(編集・削除・解決の切替を動作確認できる)
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder`(担当コーチ割当を含む)→ `EnrollmentSeeder` → 本 Seeder。
 */
final class QaThreadSeeder extends Seeder
{
    private const MIN_THREADS = 21;

    private const THREADS_PER_CERTIFICATION = 4;

    public function run(): void
    {
        $certifications = Certification::query()->published()->with('coaches')->orderBy('name')->get();
        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->orderBy('email')
            ->get();

        if ($certifications->isEmpty() || $students->isEmpty()) {
            $this->command?->warn('QaThreadSeeder: 公開中の資格または受講中の受講生が存在しません。先に依存 Seeder を実行してください。');

            return;
        }

        $fixedStudent = $students->firstWhere('email', 'student@certify-lms.test') ?? $students->first();
        $total = max(self::MIN_THREADS, $certifications->count() * self::THREADS_PER_CERTIFICATION);

        for ($i = 0; $i < $total; $i++) {
            $certification = $certifications[$i % $certifications->count()];
            $author = $i % 3 === 0 ? $fixedStudent : $students[$i % $students->count()];

            $this->createThread($i, $certification, $author, $students);
        }
    }

    /**
     * $i の剰余で 3 パターン(解決済み・回答あり / 未回答 / 対応中)を回す。
     *
     * @param Collection<int, User> $students
     */
    private function createThread(int $i, Certification $certification, User $author, $students): void
    {
        $createdAt = now()->subDays($i + 1)->subMinutes($i * 7);

        $factory = QaThread::factory()
            ->forCertification($certification)
            ->byUser($author);

        if ($i % 3 === 0) {
            $factory = $factory->resolved();
        }

        $thread = $factory->create([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'resolved_at' => $i % 3 === 0 ? $createdAt->copy()->addHours(5) : null,
        ]);

        $replyCount = match ($i % 3) {
            0 => 2,
            1 => 0,
            default => 1 + ($i % 2),
        };

        $responders = $certification->coaches->concat($students->where('id', '!=', $author->id))->values();

        for ($n = 0; $n < $replyCount && $responders->isNotEmpty(); $n++) {
            $repliedAt = $createdAt->copy()->addHours($n + 1);

            QaReply::factory()
                ->forThread($thread)
                ->byUser($responders[$n % $responders->count()])
                ->create(['created_at' => $repliedAt, 'updated_at' => $repliedAt]);
        }
    }
}
