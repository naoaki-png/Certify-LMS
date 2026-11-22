<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * 質問への回答の投稿・編集・削除(受講生 / コーチ向け)の Feature テスト。
 */
class ReplyTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_student_can_reply_and_returns_to_the_same_detail(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => '回答です'])
            ->assertRedirectContains(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_thread_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '回答です',
        ]);
    }

    public function test_student_can_reply_to_resolved_thread(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->resolved()->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => '解決後の回答'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('qa_thread_replies', 1);
    }

    public function test_coach_can_reply_to_assigned_certification_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();
        $this->assignCoach($coach, $thread->certification);

        $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), ['body' => 'コーチの回答'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('qa_thread_replies', ['user_id' => $coach->id]);
    }

    public function test_coach_cannot_reply_to_unassigned_certification_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), ['body' => '担当外'])
            ->assertForbidden();

        $this->assertDatabaseCount('qa_thread_replies', 0);
    }

    public function test_admin_cannot_post_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($admin)
            ->post(route('qa-board.replies.store', $thread), ['body' => '管理者'])
            ->assertForbidden();

        $this->assertDatabaseCount('qa_thread_replies', 0);
    }

    public function test_reply_validation_messages_and_return_to_detail(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($student)
            ->from(route('qa-board.show', $thread))
            ->post(route('qa-board.replies.store', $thread), ['body' => ''])
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHasErrors(['body' => '回答を入力してください。']);

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => str_repeat('あ', 5001)])
            ->assertSessionHasErrors(['body' => '回答は5000文字以内で入力してください。']);

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => str_repeat('あ', 5000)])
            ->assertSessionHasNoErrors();
    }

    public function test_author_can_edit_and_update_own_reply(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();

        $this->actingAs($author)->get(route('qa-board.replies.edit', [$thread, $reply]))->assertOk();

        $this->actingAs($author)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => '更新後'])
            ->assertRedirectContains(route('qa-board.show', $thread));

        $this->assertSame('更新後', $reply->fresh()->body);
    }

    public function test_update_validation_error_returns_to_reply_edit_form(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();

        $this->actingAs($author)
            ->from(route('qa-board.replies.edit', [$thread, $reply]))
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => ''])
            ->assertRedirect(route('qa-board.replies.edit', [$thread, $reply]))
            ->assertSessionHasErrors(['body' => '回答を入力してください。']);
    }

    public function test_only_author_can_edit_update_or_delete_reply(): void
    {
        $other = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->create();

        $this->actingAs($other)->get(route('qa-board.replies.edit', [$thread, $reply]))->assertForbidden();
        $this->actingAs($other)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => 'x'])
            ->assertForbidden();
        $this->actingAs($other)->delete(route('qa-board.replies.destroy', [$thread, $reply]))->assertForbidden();

        $this->assertDatabaseHas('qa_thread_replies', ['id' => $reply->id]);
    }

    public function test_author_can_delete_own_reply(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();

        $this->actingAs($author)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]))
            ->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseMissing('qa_thread_replies', ['id' => $reply->id]);
    }

    public function test_reply_of_another_thread_is_not_found(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $otherThread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($otherThread)->byUser($author)->create();

        $this->actingAs($author)
            ->get(route('qa-board.replies.edit', [$thread, $reply]))
            ->assertNotFound();
    }

    public function test_coach_removed_from_certification_loses_access_but_reply_remains(): void
    {
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($coach)->create();
        // 担当に就いていないコーチ = 担当から外れたコーチ

        $this->actingAs($coach)->get(route('qa-board.show', $thread))->assertForbidden();
        $this->assertDatabaseHas('qa_thread_replies', ['id' => $reply->id]);
    }

    public function test_reply_to_unpublished_certification_thread_is_not_found(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->forCertification(Certification::factory()->archived()->create())->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => '公開停止中'])
            ->assertNotFound();
    }
}
