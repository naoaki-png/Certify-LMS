<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SectionQuestion;

use App\Models\Certification;
use App\Models\SectionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * コーチによる演習問題管理の認可を検証する Feature テスト。
 * 担当コーチは管理者と同様に操作でき、担当外は 403。
 */
class CoachAccessTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_assigned_coach_can_view_section_questions(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->draft()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert, 'draft');
        $question = SectionQuestion::factory()
            ->forSection($section)
            ->forCategory($this->makeCategory($cert))
            ->withOptions(4)
            ->draft()
            ->create();

        $this->actingAs($coach)
            ->get(route('admin.sections.questions.index', $section))
            ->assertOk();

        $this->actingAs($coach)
            ->get(route('admin.section-questions.show', $question))
            ->assertOk();
    }

    public function test_assigned_coach_can_create_section_question(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert);
        $category = $this->makeCategory($cert);

        $this->actingAs($coach)
            ->post(route('admin.sections.questions.store', $section), [
                'body' => 'Q?',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => true, 'order' => 0],
                    ['body' => 'B', 'is_correct' => false, 'order' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('section_questions', ['section_id' => $section->id]);
    }

    public function test_assigned_coach_can_update_publish_and_delete_section_question(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert);
        $category = $this->makeCategory($cert);
        $question = SectionQuestion::factory()
            ->forSection($section)
            ->forCategory($category)
            ->withOptions(4)
            ->draft()
            ->create();

        $this->actingAs($coach)
            ->patch(route('admin.section-questions.update', $question), [
                'body' => 'updated',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => true, 'order' => 0],
                    ['body' => 'B', 'is_correct' => false, 'order' => 1],
                ],
            ])
            ->assertRedirect();
        $this->assertSame('updated', $question->fresh()->body);

        $this->actingAs($coach)
            ->post(route('admin.section-questions.publish', $question))
            ->assertRedirect();
        $this->assertSame('published', $question->fresh()->status->value);

        $this->actingAs($coach)
            ->post(route('admin.section-questions.unpublish', $question))
            ->assertRedirect();
        $this->assertSame('draft', $question->fresh()->status->value);

        $this->actingAs($coach)
            ->delete(route('admin.section-questions.destroy', $question))
            ->assertRedirect();
        $this->assertDatabaseMissing('section_questions', ['id' => $question->id]);
    }

    public function test_non_assigned_coach_cannot_manage_section_question(): void
    {
        $coach = User::factory()->coach()->create();
        $assignedCert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $assignedCert);
        $otherCert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($otherCert);
        $question = SectionQuestion::factory()
            ->forSection($section)
            ->forCategory($this->makeCategory($otherCert))
            ->withOptions(4)
            ->draft()
            ->create();

        $this->actingAs($coach)
            ->get(route('admin.section-questions.show', $question))
            ->assertForbidden();

        $this->actingAs($coach)
            ->delete(route('admin.section-questions.destroy', $question))
            ->assertForbidden();
    }
}
