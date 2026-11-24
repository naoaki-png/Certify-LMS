<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Section;

use App\Models\Certification;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * コーチによる Section 管理の認可を検証する Feature テスト。
 * 担当コーチは資格・教材の状態を問わず管理者と同様に操作でき、担当外は 403、公開中の削除制限はコーチにも効く。
 */
class CoachAccessTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public static function certificationStatusProvider(): array
    {
        return [
            '資格が公開中' => ['published'],
            '資格が下書き' => ['draft'],
            '資格が公開停止' => ['archived'],
        ];
    }

    #[DataProvider('certificationStatusProvider')]
    public function test_assigned_coach_can_view_section_regardless_of_certification_status(string $certStatus): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->{$certStatus}()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert, 'draft');

        $this->actingAs($coach)
            ->get(route('admin.sections.show', $section))
            ->assertOk();
    }

    public function test_assigned_coach_can_view_published_section(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert, 'published');

        $this->actingAs($coach)
            ->get(route('admin.sections.show', $section))
            ->assertOk();
    }

    public function test_assigned_coach_can_create_section(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, $chapter] = $this->makePartChain($cert, 'draft');

        $this->actingAs($coach)
            ->post(route('admin.chapters.sections.store', $chapter), [
                'title' => 'はじめに',
                'body' => '本文',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sections', ['chapter_id' => $chapter->id, 'title' => 'はじめに']);
    }

    public function test_assigned_coach_can_update_and_preview_section(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert, 'draft');

        $this->actingAs($coach)
            ->patch(route('admin.sections.update', $section), [
                'title' => '更新後のタイトル',
                'body' => 'updated body',
            ])
            ->assertRedirect();
        $this->assertSame('更新後のタイトル', $section->fresh()->title);

        $this->actingAs($coach)
            ->postJson(route('admin.sections.preview', $section), ['body' => '# 見出し'])
            ->assertOk();
    }

    public function test_assigned_coach_can_reorder_sections(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, $chapter, $a] = $this->makePartChain($cert, 'draft');
        $a->update(['order' => 1]);
        $b = Section::factory()->forChapter($chapter)->state(['order' => 2])->create();

        $this->actingAs($coach)
            ->patch(route('admin.chapters.sections.reorder', $chapter), ['ids' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertSame(1, $b->fresh()->order);
        $this->assertSame(2, $a->fresh()->order);
    }

    public function test_assigned_coach_can_publish_and_unpublish_section(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert, 'draft');

        $this->actingAs($coach)
            ->post(route('admin.sections.publish', $section))
            ->assertRedirect();
        $this->assertSame('published', $section->fresh()->status->value);

        $this->actingAs($coach)
            ->post(route('admin.sections.unpublish', $section))
            ->assertRedirect();
        $this->assertSame('draft', $section->fresh()->status->value);
    }

    public function test_assigned_coach_can_delete_draft_section(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert, 'draft');

        $this->actingAs($coach)
            ->delete(route('admin.sections.destroy', $section))
            ->assertRedirect();

        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
    }

    public function test_assigned_coach_cannot_delete_published_section(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert, 'published');

        $this->actingAs($coach)
            ->deleteJson(route('admin.sections.destroy', $section))
            ->assertStatus(409);

        $this->assertDatabaseHas('sections', ['id' => $section->id]);
    }

    public function test_non_assigned_coach_cannot_access_section_management(): void
    {
        $coach = User::factory()->coach()->create();
        $assignedCert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $assignedCert);
        [, , $otherSection] = $this->makePartChain(Certification::factory()->published()->create(), 'draft');

        $this->actingAs($coach)
            ->get(route('admin.sections.show', $otherSection))
            ->assertForbidden();

        $this->actingAs($coach)
            ->patch(route('admin.sections.update', $otherSection), ['title' => 'X', 'body' => 'X'])
            ->assertForbidden();

        $this->actingAs($coach)
            ->delete(route('admin.sections.destroy', $otherSection))
            ->assertForbidden();
    }
}
