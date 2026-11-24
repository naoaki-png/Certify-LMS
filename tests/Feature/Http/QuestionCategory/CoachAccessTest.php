<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QuestionCategory;

use App\Models\Certification;
use App\Models\SectionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * コーチによる出題分野マスタ管理の認可を検証する Feature テスト。
 * 担当コーチは管理者と同様に操作でき、担当外は 403、使用中の分野の削除制限はコーチにも効く。
 */
class CoachAccessTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_assigned_coach_can_view_and_create_question_category(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->archived()->create();
        $this->assignCoach($coach, $cert);

        $this->actingAs($coach)
            ->get(route('admin.certifications.question-categories.index', $cert))
            ->assertOk();

        $this->actingAs($coach)
            ->post(route('admin.certifications.question-categories.store', $cert), [
                'name' => 'テクノロジー系',
                'slug' => 'technology',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('question_categories', ['certification_id' => $cert->id, 'slug' => 'technology']);
    }

    public function test_assigned_coach_can_update_and_delete_question_category(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $category = $this->makeCategory($cert, ['slug' => 'tech']);

        $this->actingAs($coach)
            ->patch(route('admin.question-categories.update', $category), [
                'name' => '更新後の分野名',
                'slug' => 'tech',
            ])
            ->assertRedirect();
        $this->assertSame('更新後の分野名', $category->fresh()->name);

        $this->actingAs($coach)
            ->delete(route('admin.question-categories.destroy', $category))
            ->assertRedirect();
        $this->assertDatabaseMissing('question_categories', ['id' => $category->id]);
    }

    public function test_assigned_coach_cannot_delete_category_in_use(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert);
        $category = $this->makeCategory($cert);
        SectionQuestion::factory()->forSection($section)->forCategory($category)->create();

        $this->actingAs($coach)
            ->deleteJson(route('admin.question-categories.destroy', $category))
            ->assertStatus(409);

        $this->assertDatabaseHas('question_categories', ['id' => $category->id]);
    }

    public function test_non_assigned_coach_cannot_manage_question_category(): void
    {
        $coach = User::factory()->coach()->create();
        $assignedCert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $assignedCert);
        $otherCert = Certification::factory()->published()->create();
        $otherCategory = $this->makeCategory($otherCert);

        $this->actingAs($coach)
            ->post(route('admin.certifications.question-categories.store', $otherCert), [
                'name' => 'X',
                'slug' => 'x',
            ])
            ->assertForbidden();

        $this->actingAs($coach)
            ->delete(route('admin.question-categories.destroy', $otherCategory))
            ->assertForbidden();
    }
}
