<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QaThread>
 */
class QaThreadFactory extends Factory
{
    protected $model = QaThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'certification_id' => Certification::factory()->published(),
            'user_id' => User::factory()->student(),
            'title' => fake()->randomElement([
                '2 分探索木の平均比較回数がイメージできません',
                '過去問の解説の意味が分かりません',
                '学習計画の立て方を教えてください',
                '用語の違いがなかなか覚えられません',
                '計算問題でいつも時間が足りなくなります',
            ]).' '.fake()->numberBetween(1, 999),
            'body' => fake()->realText(200),
            'status' => QaThreadStatus::Open->value,
            'resolved_at' => null,
        ];
    }

    public function forCertification(Certification $certification): static
    {
        return $this->state(fn () => ['certification_id' => $certification->id]);
    }

    public function byUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    /**
     * 解決済みのスレッド(解決日時つき)。
     */
    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => QaThreadStatus::Resolved->value,
            'resolved_at' => now(),
        ]);
    }
}
