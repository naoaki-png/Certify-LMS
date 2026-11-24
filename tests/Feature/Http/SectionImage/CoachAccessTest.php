<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SectionImage;

use App\Models\Certification;
use App\Models\SectionImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * コーチによる教材内画像のアップロード / 削除の認可を検証する Feature テスト。
 */
class CoachAccessTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_assigned_coach_can_upload_and_delete_image(): void
    {
        Storage::fake('public');
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [, , $section] = $this->makePartChain($cert, 'draft');

        $this->actingAs($coach)
            ->postJson(route('admin.sections.images.store', $section), [
                'file' => UploadedFile::fake()->image('cover.png', 800, 600),
            ])
            ->assertCreated();

        $image = SectionImage::where('section_id', $section->id)->firstOrFail();

        $this->actingAs($coach)
            ->deleteJson(route('admin.section-images.destroy', $image))
            ->assertNoContent();

        $this->assertDatabaseMissing('section_images', ['id' => $image->id]);
    }

    public function test_non_assigned_coach_cannot_upload_or_delete_image(): void
    {
        Storage::fake('public');
        $coach = User::factory()->coach()->create();
        $assignedCert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $assignedCert);
        [, , $otherSection] = $this->makePartChain(Certification::factory()->published()->create(), 'draft');
        $otherImage = SectionImage::factory()->forSection($otherSection)->create();

        $this->actingAs($coach)
            ->postJson(route('admin.sections.images.store', $otherSection), [
                'file' => UploadedFile::fake()->image('cover.png', 800, 600),
            ])
            ->assertForbidden();

        $this->actingAs($coach)
            ->deleteJson(route('admin.section-images.destroy', $otherImage))
            ->assertForbidden();

        $this->assertDatabaseHas('section_images', ['id' => $otherImage->id]);
    }
}
