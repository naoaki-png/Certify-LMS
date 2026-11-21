<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * 質問掲示板の一覧(並び順・ページネーション・絞り込み・検索・閲覧範囲・N+1)の Feature テスト。
 */
class IndexTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_threads_are_listed_newest_first_and_paginated_by_20(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        foreach (range(1, 21) as $i) {
            QaThread::factory()->forCertification($cert)->create([
                'title' => sprintf('スレッド%02d', $i),
                'created_at' => now()->subMinutes(30 - $i),
            ]);
        }

        $page1 = $this->actingAs($student)->get(route('qa-board.index'));
        $page1->assertOk()->assertSeeInOrder(['スレッド21', 'スレッド20', 'スレッド02'])->assertDontSee('スレッド01');
        $this->assertCount(20, $page1->viewData('threads')->items());

        $page2 = $this->actingAs($student)->get(route('qa-board.index', ['page' => 2]));
        $page2->assertOk()->assertSee('スレッド01');
        $this->assertCount(1, $page2->viewData('threads')->items());
    }

    public function test_default_listing_includes_resolved_and_unresolved(): void
    {
        $student = User::factory()->student()->create();
        QaThread::factory()->create(['title' => '未解決のスレッド']);
        QaThread::factory()->resolved()->create(['title' => '解決済みのスレッド']);

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertSee('未解決のスレッド')
            ->assertSee('解決済みのスレッド');
    }

    public function test_filters_combine_with_and(): void
    {
        $student = User::factory()->student()->create();
        $certA = Certification::factory()->published()->create();
        $certB = Certification::factory()->published()->create();
        QaThread::factory()->forCertification($certA)->create(['title' => 'A未解決 Laravel']);
        QaThread::factory()->forCertification($certA)->resolved()->create(['title' => 'A解決済 Laravel']);
        QaThread::factory()->forCertification($certB)->create(['title' => 'B未解決 Laravel']);
        QaThread::factory()->forCertification($certA)->create(['title' => 'A未解決 別件']);

        $this->actingAs($student)
            ->get(route('qa-board.index', [
                'certification_id' => $certA->id,
                'status' => 'unresolved',
                'keyword' => 'Laravel',
            ]))
            ->assertOk()
            ->assertSee('A未解決 Laravel')
            ->assertDontSee('A解決済 Laravel')
            ->assertDontSee('B未解決 Laravel')
            ->assertDontSee('A未解決 別件');
    }

    public function test_resolved_filter_shows_only_resolved(): void
    {
        $student = User::factory()->student()->create();
        QaThread::factory()->create(['title' => '未解決のスレッド']);
        QaThread::factory()->resolved()->create(['title' => '解決済みのスレッド']);

        $this->actingAs($student)
            ->get(route('qa-board.index', ['status' => 'resolved']))
            ->assertSee('解決済みのスレッド')
            ->assertDontSee('未解決のスレッド');
    }

    public function test_keyword_matches_title_body_or_reply_body(): void
    {
        $student = User::factory()->student()->create();
        QaThread::factory()->create(['title' => 'タイトルに合言葉', 'body' => 'ふつう']);
        QaThread::factory()->create(['title' => '本文の件', 'body' => '本文に合言葉あり']);
        $byReply = QaThread::factory()->create(['title' => '回答の件', 'body' => 'ふつう']);
        QaReply::factory()->forThread($byReply)->create(['body' => '回答に合言葉あり']);
        QaThread::factory()->create(['title' => '無関係のスレッド', 'body' => 'ふつう']);

        $this->actingAs($student)
            ->get(route('qa-board.index', ['keyword' => '合言葉']))
            ->assertSee('タイトルに合言葉')
            ->assertSee('本文の件')
            ->assertSee('回答の件')
            ->assertDontSee('無関係のスレッド');
    }

    public function test_keyword_wildcards_are_matched_literally(): void
    {
        $student = User::factory()->student()->create();
        QaThread::factory()->create(['title' => '百パーセント 100%']);
        QaThread::factory()->create(['title' => '別のスレッド']);

        $this->actingAs($student)
            ->get(route('qa-board.index', ['keyword' => '%']))
            ->assertSee('百パーセント')
            ->assertDontSee('別のスレッド');
    }

    public function test_filters_are_kept_in_pagination_links(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        QaThread::factory()->count(21)->forCertification($cert)->create();

        $response = $this->actingAs($student)->get(route('qa-board.index', ['certification_id' => $cert->id]));

        $this->assertStringContainsString(
            'certification_id='.$cert->id,
            $response->viewData('threads')->nextPageUrl(),
        );
    }

    public function test_empty_result_shows_empty_state(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertOk()
            ->assertSee('該当する質問はまだありません');
    }

    public function test_unpublished_certification_threads_are_hidden_from_students(): void
    {
        $student = User::factory()->student()->create();
        QaThread::factory()->create(['title' => '公開中の資格のスレッド']);
        QaThread::factory()
            ->forCertification(Certification::factory()->archived()->create())
            ->create(['title' => '公開停止の資格のスレッド']);
        QaThread::factory()
            ->forCertification(Certification::factory()->draft()->create())
            ->create(['title' => '下書きの資格のスレッド']);

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertSee('公開中の資格のスレッド')
            ->assertDontSee('公開停止の資格のスレッド')
            ->assertDontSee('下書きの資格のスレッド');
    }

    public function test_coach_sees_only_assigned_published_certifications(): void
    {
        $coach = User::factory()->coach()->create();
        $assigned = Certification::factory()->published()->create(['name' => '担当の資格']);
        $unassigned = Certification::factory()->published()->create(['name' => '担当外の資格']);
        $assignedArchived = Certification::factory()->archived()->create(['name' => '担当だが公開停止の資格']);
        $this->assignCoach($coach, $assigned);
        $this->assignCoach($coach, $assignedArchived);
        QaThread::factory()->forCertification($assigned)->create(['title' => '担当資格のスレッド']);
        QaThread::factory()->forCertification($unassigned)->create(['title' => '担当外のスレッド']);
        QaThread::factory()->forCertification($assignedArchived)->create(['title' => '公開停止のスレッド']);

        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        $response->assertOk()
            ->assertSee('担当資格のスレッド')
            ->assertDontSee('担当外のスレッド')
            ->assertDontSee('公開停止のスレッド');
        $this->assertSame([$assigned->id], $response->viewData('certifications')->pluck('id')->all());
    }

    public function test_query_count_does_not_grow_with_thread_count(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $this->makeThreads($cert, 2);

        $baseline = $this->countQueriesFor(fn () => $this->actingAs($student)->get(route('qa-board.index')));
        $this->makeThreads($cert, 15);
        $scaled = $this->countQueriesFor(fn () => $this->actingAs($student)->get(route('qa-board.index')));

        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "質問掲示板の一覧で N+1 が再発している(基準 {$baseline} → 増加後 {$scaled})",
        );
    }

    public function test_detail_query_count_does_not_grow_with_reply_count(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->count(2)->forThread($thread)->create();

        $baseline = $this->countQueriesFor(fn () => $this->actingAs($student)->get(route('qa-board.show', $thread)));
        QaReply::factory()->count(15)->forThread($thread)->create();
        $scaled = $this->countQueriesFor(fn () => $this->actingAs($student)->get(route('qa-board.show', $thread)));

        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "質問掲示板の詳細で N+1 が再発している(基準 {$baseline} → 増加後 {$scaled})",
        );
    }

    private function makeThreads(Certification $cert, int $count): void
    {
        QaThread::factory()->count($count)->forCertification($cert)->create()
            ->each(fn (QaThread $thread) => QaReply::factory()->count(2)->forThread($thread)->create());
    }

    private function countQueriesFor(\Closure $closure): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $closure();

        return $count;
    }
}
