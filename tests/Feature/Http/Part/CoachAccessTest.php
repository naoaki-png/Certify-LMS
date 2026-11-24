<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Part;

use App\Models\Certification;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * コーチによる Part 管理の認可を検証する Feature テスト。
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
    public function test_assigned_coach_can_view_parts_regardless_of_certification_status(string $certStatus): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->{$certStatus}()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($coach)
            ->get(route('admin.certifications.parts.index', $cert))
            ->assertOk();

        $this->actingAs($coach)
            ->get(route('admin.parts.show', $part))
            ->assertOk();
    }

    public function test_assigned_coach_can_create_part(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);

        $this->actingAs($coach)
            ->post(route('admin.certifications.parts.store', $cert), ['title' => '第1部 基礎'])
            ->assertRedirect();

        $this->assertDatabaseHas('parts', ['certification_id' => $cert->id, 'title' => '第1部 基礎']);
    }

    public function test_assigned_coach_can_update_part(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($coach)
            ->patch(route('admin.parts.update', $part), ['title' => '更新後のタイトル'])
            ->assertRedirect();

        $this->assertSame('更新後のタイトル', $part->fresh()->title);
    }

    public function test_assigned_coach_can_reorder_parts(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $a = Part::factory()->forCertification($cert)->state(['order' => 1])->create();
        $b = Part::factory()->forCertification($cert)->state(['order' => 2])->create();

        $this->actingAs($coach)
            ->patch(route('admin.certifications.parts.reorder', $cert), ['ids' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertSame(1, $b->fresh()->order);
        $this->assertSame(2, $a->fresh()->order);
    }

    public function test_assigned_coach_can_publish_and_unpublish_part(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($coach)
            ->post(route('admin.parts.publish', $part))
            ->assertRedirect();
        $this->assertSame('published', $part->fresh()->status->value);

        $this->actingAs($coach)
            ->post(route('admin.parts.unpublish', $part))
            ->assertRedirect();
        $this->assertSame('draft', $part->fresh()->status->value);
    }

    public function test_assigned_coach_can_delete_draft_part(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($coach)
            ->delete(route('admin.parts.destroy', $part))
            ->assertRedirect();

        $this->assertDatabaseMissing('parts', ['id' => $part->id]);
    }

    public function test_assigned_coach_cannot_delete_published_part(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->published()->create();

        $this->actingAs($coach)
            ->deleteJson(route('admin.parts.destroy', $part))
            ->assertStatus(409);

        $this->assertDatabaseHas('parts', ['id' => $part->id]);
    }

    public function test_non_assigned_coach_cannot_access_part_management(): void
    {
        $coach = User::factory()->coach()->create();
        $assignedCert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $assignedCert);
        $otherCert = Certification::factory()->published()->create();
        $otherPart = Part::factory()->forCertification($otherCert)->draft()->create();

        $this->actingAs($coach)
            ->get(route('admin.parts.show', $otherPart))
            ->assertForbidden();

        $this->actingAs($coach)
            ->patch(route('admin.parts.update', $otherPart), ['title' => 'X'])
            ->assertForbidden();

        $this->actingAs($coach)
            ->delete(route('admin.parts.destroy', $otherPart))
            ->assertForbidden();
    }
}
