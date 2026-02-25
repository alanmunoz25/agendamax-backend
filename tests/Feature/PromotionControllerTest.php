<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private Promotion $promotion;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->business = Business::factory()->create([
            'name' => 'Test Business',
        ]);

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $this->promotion = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Summer Sale',
            'is_active' => true,
        ]);
    }

    public function test_business_admin_can_view_promotions_index(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/promotions');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Promotions/Index')
            ->has('promotions.data', 1)
            ->has('promotions.data.0', fn ($promo) => $promo
                ->where('id', $this->promotion->id)
                ->where('title', 'Summer Sale')
                ->etc()
            )
        );
    }

    public function test_promotions_index_can_be_searched(): void
    {
        Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Winter Special',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/promotions?search=Winter');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('promotions.data', 1)
            ->where('promotions.data.0.title', 'Winter Special')
        );
    }

    public function test_promotions_index_can_be_filtered_by_status(): void
    {
        Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Inactive Promo',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/promotions?is_active=0');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('promotions.data', 1)
            ->where('promotions.data.0.is_active', false)
        );
    }

    public function test_business_admin_can_view_create_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/promotions/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Promotions/Create')
        );
    }

    public function test_business_admin_can_create_promotion_with_image(): void
    {
        $image = UploadedFile::fake()->image('flyer.jpg', 800, 600);

        $response = $this->actingAs($this->businessAdmin)
            ->post('/promotions', [
                'title' => 'Grand Opening',
                'image' => $image,
                'url' => 'https://example.com/promo',
                'expires_at' => now()->addMonth()->toDateString(),
                'is_active' => true,
            ]);

        $response->assertRedirect('/promotions');

        $this->assertDatabaseHas('promotions', [
            'business_id' => $this->business->id,
            'title' => 'Grand Opening',
            'url' => 'https://example.com/promo',
            'is_active' => true,
        ]);

        $promotion = Promotion::where('title', 'Grand Opening')->first();
        Storage::disk('public')->assertExists($promotion->image_path);
    }

    public function test_create_promotion_requires_image(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/promotions', [
                'title' => 'No Image Promo',
            ]);

        $response->assertSessionHasErrors(['image']);
    }

    public function test_create_promotion_requires_title(): void
    {
        $image = UploadedFile::fake()->image('flyer.jpg');

        $response = $this->actingAs($this->businessAdmin)
            ->post('/promotions', [
                'title' => '',
                'image' => $image,
            ]);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_create_promotion_validates_image_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->businessAdmin)
            ->post('/promotions', [
                'title' => 'Bad Image',
                'image' => $file,
            ]);

        $response->assertSessionHasErrors(['image']);
    }

    public function test_create_promotion_validates_image_size(): void
    {
        $image = UploadedFile::fake()->image('huge.jpg')->size(3000);

        $response = $this->actingAs($this->businessAdmin)
            ->post('/promotions', [
                'title' => 'Big Image',
                'image' => $image,
            ]);

        $response->assertSessionHasErrors(['image']);
    }

    public function test_create_promotion_validates_url_format(): void
    {
        $image = UploadedFile::fake()->image('flyer.jpg');

        $response = $this->actingAs($this->businessAdmin)
            ->post('/promotions', [
                'title' => 'Bad URL',
                'image' => $image,
                'url' => 'not-a-url',
            ]);

        $response->assertSessionHasErrors(['url']);
    }

    public function test_create_promotion_validates_expires_at_not_in_past(): void
    {
        $image = UploadedFile::fake()->image('flyer.jpg');

        $response = $this->actingAs($this->businessAdmin)
            ->post('/promotions', [
                'title' => 'Past Expiry',
                'image' => $image,
                'expires_at' => now()->subDay()->toDateString(),
            ]);

        $response->assertSessionHasErrors(['expires_at']);
    }

    public function test_business_admin_can_view_edit_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/promotions/{$this->promotion->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Promotions/Edit')
            ->has('promotion')
        );
    }

    public function test_business_admin_can_update_promotion_without_new_image(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put("/promotions/{$this->promotion->id}", [
                'title' => 'Updated Summer Sale',
                'is_active' => false,
            ]);

        $response->assertRedirect('/promotions');
        $response->assertSessionHas('success');

        $this->promotion->refresh();
        $this->assertEquals('Updated Summer Sale', $this->promotion->title);
        $this->assertFalse($this->promotion->is_active);
    }

    public function test_business_admin_can_update_promotion_with_new_image(): void
    {
        $oldImagePath = $this->promotion->image_path;
        Storage::disk('public')->put($oldImagePath, 'old-image-content');

        $newImage = UploadedFile::fake()->image('new-flyer.jpg', 800, 600);

        $response = $this->actingAs($this->businessAdmin)
            ->put("/promotions/{$this->promotion->id}", [
                'title' => 'Updated Sale',
                'image' => $newImage,
            ]);

        $response->assertRedirect('/promotions');

        $this->promotion->refresh();
        $this->assertEquals('Updated Sale', $this->promotion->title);
        $this->assertNotEquals($oldImagePath, $this->promotion->image_path);
        Storage::disk('public')->assertExists($this->promotion->image_path);
        Storage::disk('public')->assertMissing($oldImagePath);
    }

    public function test_business_admin_can_delete_promotion(): void
    {
        Storage::disk('public')->put($this->promotion->image_path, 'image-content');

        $response = $this->actingAs($this->businessAdmin)
            ->delete("/promotions/{$this->promotion->id}");

        $response->assertRedirect('/promotions');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('promotions', [
            'id' => $this->promotion->id,
        ]);

        Storage::disk('public')->assertMissing($this->promotion->image_path);
    }

    public function test_employee_cannot_create_promotion(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->get('/promotions/create');

        $response->assertForbidden();

        $image = UploadedFile::fake()->image('flyer.jpg');

        $response = $this->actingAs($employee)
            ->post('/promotions', [
                'title' => 'Hacked Promo',
                'image' => $image,
            ]);

        $response->assertForbidden();
    }

    public function test_employee_cannot_update_promotion(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->put("/promotions/{$this->promotion->id}", [
                'title' => 'Hacked',
            ]);

        $response->assertForbidden();

        $this->promotion->refresh();
        $this->assertEquals('Summer Sale', $this->promotion->title);
    }

    public function test_employee_cannot_delete_promotion(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->delete("/promotions/{$this->promotion->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('promotions', [
            'id' => $this->promotion->id,
        ]);
    }

    public function test_user_from_different_business_cannot_access_promotion(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
        ]);

        $response = $this->actingAs($otherAdmin)
            ->get('/promotions');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('promotions.data', 0)
        );

        $response = $this->actingAs($otherAdmin)
            ->get("/promotions/{$this->promotion->id}/edit");

        $response->assertNotFound();
    }

    public function test_guest_cannot_access_promotion_routes(): void
    {
        $this->get('/promotions')->assertRedirect('/login');
        $this->get('/promotions/create')->assertRedirect('/login');
        $this->post('/promotions', [])->assertRedirect('/login');
        $this->get("/promotions/{$this->promotion->id}/edit")->assertRedirect('/login');
        $this->put("/promotions/{$this->promotion->id}", [])->assertRedirect('/login');
        $this->delete("/promotions/{$this->promotion->id}")->assertRedirect('/login');
    }

    public function test_user_without_business_cannot_access_promotion_routes(): void
    {
        $userWithoutBusiness = User::factory()->create([
            'business_id' => null,
            'role' => 'client',
        ]);

        $response = $this->actingAs($userWithoutBusiness)
            ->get('/promotions');

        $response->assertForbidden();
    }
}
