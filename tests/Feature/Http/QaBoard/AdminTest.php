<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 質問掲示板の管理者モデレーション(`/admin/qa-board`)の Feature テスト。
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_index_includes_unpublished_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        QaThread::factory()->create(['title' => '公開中のスレッド']);
        QaThread::factory()
            ->forCertification(Certification::factory()->archived()->create(['name' => '公開停止の資格']))
            ->create(['title' => '公開停止のスレッド']);

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk()->assertSee('公開中のスレッド')->assertSee('公開停止のスレッド');
        $this->assertTrue($response->viewData('certifications')->contains('name', '公開停止の資格'));
    }

    public function test_admin_index_supports_filters(): void
    {
        $admin = User::factory()->admin()->create();
        QaThread::factory()->create(['title' => '未解決のスレッド']);
        QaThread::factory()->resolved()->create(['title' => '解決済みのスレッド']);

        $this->actingAs($admin)
            ->get(route('admin.qa-board.index', ['status' => 'resolved']))
            ->assertSee('解決済みのスレッド')
            ->assertDontSee('未解決のスレッド');
    }

    public function test_admin_can_view_unpublished_certification_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()
            ->forCertification(Certification::factory()->archived()->create())
            ->create(['title' => '公開停止のスレッド']);

        $this->actingAs($admin)
            ->get(route('admin.qa-board.show', $thread))
            ->assertOk()
            ->assertSee('公開停止のスレッド');
    }

    public function test_admin_detail_has_no_edit_resolve_or_reply_form(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.qa-board.show', $thread))
            ->assertOk()
            ->assertDontSee(route('qa-board.edit', $thread))
            ->assertDontSee(route('qa-board.resolve', $thread))
            ->assertDontSee(route('qa-board.replies.store', $thread))
            ->assertSee(route('admin.qa-board.destroy', $thread));
    }

    public function test_admin_can_delete_thread_with_its_replies(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->count(2)->forThread($thread)->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.destroy', $thread))
            ->assertRedirect(route('admin.qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
        $this->assertDatabaseCount('qa_thread_replies', 0);
    }

    public function test_admin_can_delete_reply_and_returns_to_admin_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [$thread, $reply]))
            ->assertRedirect(route('admin.qa-board.show', $thread));

        $this->assertDatabaseMissing('qa_thread_replies', ['id' => $reply->id]);
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_non_admin_gets_403_and_missing_thread_gets_404(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();

        foreach ([$student, $coach] as $user) {
            $this->actingAs($user)->get(route('admin.qa-board.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.qa-board.show', $thread))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.qa-board.destroy', $thread))->assertForbidden();
        }

        $this->actingAs($admin)->get('/admin/qa-board/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_admin_cannot_delete_through_student_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($admin)->delete(route('qa-board.destroy', $thread))->assertForbidden();
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }
}
