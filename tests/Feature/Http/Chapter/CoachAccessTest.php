<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Chapter;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * コーチによる Chapter 管理の認可を検証する Feature テスト。
 * 担当コーチは資格の状態を問わず管理者と同様に操作でき、担当外は 403、公開中の削除制限はコーチにも効く。
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
    public function test_assigned_coach_can_view_chapter_regardless_of_certification_status(string $certStatus): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->{$certStatus}()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();
        $chapter = Chapter::factory()->forPart($part)->draft()->create();

        $this->actingAs($coach)
            ->get(route('admin.chapters.show', $chapter))
            ->assertOk();
    }

    public function test_assigned_coach_can_create_chapter(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($coach)
            ->post(route('admin.parts.chapters.store', $part), ['title' => '第1章'])
            ->assertRedirect();

        $this->assertDatabaseHas('chapters', ['part_id' => $part->id, 'title' => '第1章']);
    }

    public function test_assigned_coach_can_update_chapter(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();
        $chapter = Chapter::factory()->forPart($part)->draft()->create();

        $this->actingAs($coach)
            ->patch(route('admin.chapters.update', $chapter), ['title' => '更新後のタイトル'])
            ->assertRedirect();

        $this->assertSame('更新後のタイトル', $chapter->fresh()->title);
    }

    public function test_assigned_coach_can_reorder_chapters(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();
        $a = Chapter::factory()->forPart($part)->state(['order' => 1])->create();
        $b = Chapter::factory()->forPart($part)->state(['order' => 2])->create();

        $this->actingAs($coach)
            ->patch(route('admin.parts.chapters.reorder', $part), ['ids' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertSame(1, $b->fresh()->order);
        $this->assertSame(2, $a->fresh()->order);
    }

    public function test_assigned_coach_can_publish_and_unpublish_chapter(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();
        $chapter = Chapter::factory()->forPart($part)->draft()->create();

        $this->actingAs($coach)
            ->post(route('admin.chapters.publish', $chapter))
            ->assertRedirect();
        $this->assertSame('published', $chapter->fresh()->status->value);

        $this->actingAs($coach)
            ->post(route('admin.chapters.unpublish', $chapter))
            ->assertRedirect();
        $this->assertSame('draft', $chapter->fresh()->status->value);
    }

    public function test_assigned_coach_can_delete_draft_chapter(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();
        $chapter = Chapter::factory()->forPart($part)->draft()->create();

        $this->actingAs($coach)
            ->delete(route('admin.chapters.destroy', $chapter))
            ->assertRedirect();

        $this->assertDatabaseMissing('chapters', ['id' => $chapter->id]);
    }

    public function test_assigned_coach_cannot_delete_published_chapter(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();

        $this->actingAs($coach)
            ->deleteJson(route('admin.chapters.destroy', $chapter))
            ->assertStatus(409);

        $this->assertDatabaseHas('chapters', ['id' => $chapter->id]);
    }

    public function test_non_assigned_coach_cannot_access_chapter_management(): void
    {
        $coach = User::factory()->coach()->create();
        $assignedCert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $assignedCert);
        $otherCert = Certification::factory()->published()->create();
        $otherPart = Part::factory()->forCertification($otherCert)->draft()->create();
        $otherChapter = Chapter::factory()->forPart($otherPart)->draft()->create();

        $this->actingAs($coach)
            ->get(route('admin.chapters.show', $otherChapter))
            ->assertForbidden();

        $this->actingAs($coach)
            ->post(route('admin.parts.chapters.store', $otherPart), ['title' => 'X'])
            ->assertForbidden();

        $this->actingAs($coach)
            ->delete(route('admin.chapters.destroy', $otherChapter))
            ->assertForbidden();
    }
}
