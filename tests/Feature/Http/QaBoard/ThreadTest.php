<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * 質問スレッドの投稿・詳細・編集・削除・解決の切替(受講生向け)の Feature テスト。
 */
class ThreadTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_student_can_post_thread_and_is_redirected_to_its_detail(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => '質問のタイトル',
            'body' => '質問の本文',
        ]);

        $thread = QaThread::query()->firstOrFail();
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertSame($student->id, $thread->user_id);
        $this->assertSame($cert->id, $thread->certification_id);
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }

    public function test_created_thread_has_open_status_before_reload(): void
    {
        $thread = QaThread::create([
            'certification_id' => Certification::factory()->published()->create()->id,
            'title' => 't',
            'body' => 'b',
            'user_id' => User::factory()->student()->create()->id,
        ]);

        $this->assertSame(QaThreadStatus::Open, $thread->status);
    }

    public function test_store_validation_messages(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->from(route('qa-board.create'))
            ->post(route('qa-board.store'), ['certification_id' => '', 'title' => '', 'body' => ''])
            ->assertRedirect(route('qa-board.create'))
            ->assertSessionHasErrors([
                'certification_id' => '資格を選択してください。',
                'title' => 'タイトルを入力してください。',
                'body' => '本文を入力してください。',
            ]);

        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_store_rejects_too_long_title_and_body(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $cert->id,
                'title' => str_repeat('あ', 201),
                'body' => str_repeat('あ', 5001),
            ])
            ->assertSessionHasErrors([
                'title' => 'タイトルは200文字以内で入力してください。',
                'body' => '本文は5000文字以内で入力してください。',
            ]);
    }

    public function test_store_accepts_boundary_lengths(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $cert->id,
                'title' => str_repeat('あ', 200),
                'body' => str_repeat('あ', 5000),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('qa_threads', 1);
    }

    public function test_store_rejects_unpublished_certification(): void
    {
        $student = User::factory()->student()->create();

        foreach ([Certification::factory()->draft()->create(), Certification::factory()->archived()->create()] as $cert) {
            $this->actingAs($student)
                ->post(route('qa-board.store'), [
                    'certification_id' => $cert->id,
                    'title' => 't',
                    'body' => 'b',
                ])
                ->assertSessionHasErrors(['certification_id' => '選択した資格は指定できません。']);
        }

        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_create_form_lists_only_published_certifications(): void
    {
        $student = User::factory()->student()->create();
        $published = Certification::factory()->published()->create(['name' => '公開中の資格']);
        Certification::factory()->archived()->create(['name' => '公開停止の資格']);

        $this->actingAs($student)
            ->get(route('qa-board.create'))
            ->assertOk()
            ->assertSee($published->name)
            ->assertDontSee('公開停止の資格');
    }

    public function test_detail_shows_replies_oldest_first(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->forThread($thread)->create(['body' => '二番目の回答', 'created_at' => now()->subHour()]);
        QaReply::factory()->forThread($thread)->create(['body' => '一番目の回答', 'created_at' => now()->subHours(2)]);

        $this->actingAs($student)
            ->get(route('qa-board.show', $thread))
            ->assertOk()
            ->assertSeeInOrder(['一番目の回答', '二番目の回答']);
    }

    public function test_author_can_edit_and_update_thread_but_not_its_certification(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($author)->create();
        $originalCertificationId = $thread->certification_id;
        $other = Certification::factory()->published()->create();

        $this->actingAs($author)->get(route('qa-board.edit', $thread))->assertOk();

        $this->actingAs($author)
            ->patch(route('qa-board.update', $thread), [
                'title' => '新しいタイトル',
                'body' => '新しい本文',
                'certification_id' => $other->id,
            ])
            ->assertRedirect(route('qa-board.show', $thread));

        $thread->refresh();
        $this->assertSame('新しいタイトル', $thread->title);
        $this->assertSame('新しい本文', $thread->body);
        $this->assertSame($originalCertificationId, $thread->certification_id);
    }

    public function test_update_validation_error_returns_to_edit_form_with_input(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($author)->create();

        $this->actingAs($author)
            ->from(route('qa-board.edit', $thread))
            ->patch(route('qa-board.update', $thread), ['title' => '', 'body' => '残る本文'])
            ->assertRedirect(route('qa-board.edit', $thread))
            ->assertSessionHasErrors(['title' => 'タイトルを入力してください。'])
            ->assertSessionHasInput('body', '残る本文');
    }

    public function test_resolved_thread_can_still_be_edited(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($author)->resolved()->create();

        $this->actingAs($author)->get(route('qa-board.edit', $thread))->assertOk();
        $this->actingAs($author)
            ->patch(route('qa-board.update', $thread), ['title' => '解決後の編集', 'body' => '本文'])
            ->assertRedirect(route('qa-board.show', $thread));

        $this->assertSame('解決後の編集', $thread->fresh()->title);
    }

    public function test_other_student_cannot_edit_update_delete_or_toggle_resolution(): void
    {
        $other = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($other)->get(route('qa-board.edit', $thread))->assertForbidden();
        $this->actingAs($other)
            ->patch(route('qa-board.update', $thread), ['title' => 'x', 'body' => 'y'])
            ->assertForbidden();
        $this->actingAs($other)->delete(route('qa-board.destroy', $thread))->assertForbidden();
        $this->actingAs($other)->post(route('qa-board.resolve', $thread))->assertForbidden();
        $this->actingAs($other)->post(route('qa-board.unresolve', $thread))->assertForbidden();

        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_author_can_delete_thread_without_replies(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($author)->create();

        $this->actingAs($author)
            ->delete(route('qa-board.destroy', $thread))
            ->assertRedirect(route('qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_author_cannot_delete_thread_with_replies(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($author)->create();
        QaReply::factory()->forThread($thread)->create();

        $this->actingAs($author)
            ->delete(route('qa-board.destroy', $thread))
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('error', 'このスレッドは削除できません。');

        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_author_can_resolve_and_unresolve_thread(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($author)->create();

        $this->actingAs($author)
            ->post(route('qa-board.resolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread));

        $thread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $thread->status);
        $this->assertNotNull($thread->resolved_at);

        $this->actingAs($author)
            ->post(route('qa-board.unresolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread));

        $thread->refresh();
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }
}
