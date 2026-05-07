<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessUploadTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->business = Business::factory()->create([
            'name' => 'Upload Test Business',
            'slug' => 'upload-test',
        ]);

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);
    }

    public function test_business_admin_can_upload_logo(): void
    {
        $logo = UploadedFile::fake()->image('logo.jpg', 200, 200);

        $response = $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => $this->business->name,
                'logo' => $logo,
            ]);

        $response->assertRedirect('/business');

        $this->business->refresh();

        // logo_url accessor returns a full URL; the raw DB value is the storage-relative path.
        $rawPath = $this->business->getRawOriginal('logo_url');
        $this->assertNotNull($rawPath);
        Storage::disk('public')->assertExists($rawPath);
    }

    public function test_business_admin_can_upload_banner(): void
    {
        $banner = UploadedFile::fake()->image('banner.png', 1200, 400);

        $response = $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => $this->business->name,
                'banner' => $banner,
            ]);

        $response->assertRedirect('/business');

        $this->business->refresh();

        $rawPath = $this->business->getRawOriginal('banner_url');
        $this->assertNotNull($rawPath);
        Storage::disk('public')->assertExists($rawPath);
    }

    public function test_business_admin_can_upload_cover(): void
    {
        $cover = UploadedFile::fake()->image('cover.jpg', 1920, 600);

        $response = $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => $this->business->name,
                'cover' => $cover,
            ]);

        $response->assertRedirect('/business');

        $this->business->refresh();

        $rawPath = $this->business->getRawOriginal('cover_image_url');
        $this->assertNotNull($rawPath);
        Storage::disk('public')->assertExists($rawPath);
    }

    public function test_non_image_file_is_rejected_for_logo(): void
    {
        $document = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => $this->business->name,
                'logo' => $document,
            ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_oversized_logo_is_rejected(): void
    {
        $bigImage = UploadedFile::fake()->image('huge.jpg')->size(3000);

        $response = $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => $this->business->name,
                'logo' => $bigImage,
            ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_oversized_banner_is_rejected(): void
    {
        $bigImage = UploadedFile::fake()->image('huge-banner.jpg')->size(6000);

        $response = $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => $this->business->name,
                'banner' => $bigImage,
            ]);

        $response->assertSessionHasErrors('banner');
    }

    public function test_logo_url_stored_in_correct_directory(): void
    {
        $logo = UploadedFile::fake()->image('logo.png', 100, 100);

        $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => $this->business->name,
                'logo' => $logo,
            ]);

        $this->business->refresh();

        // Verify raw DB path (not the accessor-resolved URL) starts with the correct directory.
        $this->assertStringStartsWith('businesses/logos/', $this->business->getRawOriginal('logo_url'));
        // Verify the accessor returns a full URL pointing to the correct directory.
        $this->assertStringContainsString('businesses/logos/', $this->business->logo_url);
    }

    public function test_banner_url_stored_in_correct_directory(): void
    {
        $banner = UploadedFile::fake()->image('banner.jpg', 800, 200);

        $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => $this->business->name,
                'banner' => $banner,
            ]);

        $this->business->refresh();

        $this->assertStringStartsWith('businesses/banners/', $this->business->getRawOriginal('banner_url'));
        $this->assertStringContainsString('businesses/banners/', $this->business->banner_url);
    }

    public function test_cover_url_stored_in_correct_directory(): void
    {
        $cover = UploadedFile::fake()->image('cover.jpg', 1200, 400);

        $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => $this->business->name,
                'cover' => $cover,
            ]);

        $this->business->refresh();

        $this->assertStringStartsWith('businesses/covers/', $this->business->getRawOriginal('cover_image_url'));
        $this->assertStringContainsString('businesses/covers/', $this->business->cover_image_url);
    }

    public function test_unauthenticated_user_cannot_upload(): void
    {
        $logo = UploadedFile::fake()->image('logo.jpg');

        $response = $this->put('/business', [
            'name' => 'Test',
            'logo' => $logo,
        ]);

        $response->assertRedirect('/login');
    }
}
