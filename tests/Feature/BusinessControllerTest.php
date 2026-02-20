<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'name' => 'Test Business',
            'slug' => 'test-business',
            'email' => 'test@business.com',
            'loyalty_stamps_required' => 10,
            'loyalty_reward_description' => 'Free service',
        ]);

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $this->superAdmin = User::factory()->create([
            'business_id' => null,
            'role' => 'super_admin',
        ]);
    }

    // ──────────────────────────────────────────────────
    // Own business routes (/business) - business_admin
    // ──────────────────────────────────────────────────

    public function test_business_admin_can_view_their_business(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/business');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Business/Show')
            ->has('business', fn ($business) => $business
                ->where('id', $this->business->id)
                ->where('name', 'Test Business')
                ->where('loyalty_stamps_required', 10)
                ->etc()
            )
        );
    }

    public function test_business_admin_can_view_edit_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/business/edit');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Business/Edit')
            ->has('business')
        );
    }

    public function test_business_admin_can_update_business(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => 'Updated Business Name',
                'description' => 'Updated description',
                'email' => 'updated@business.com',
                'phone' => '555-1234',
                'address' => '123 Main St',
                'loyalty_stamps_required' => 15,
                'loyalty_reward_description' => 'Updated reward',
            ]);

        $response->assertRedirect('/business');
        $response->assertSessionHas('success');

        $this->business->refresh();
        $this->assertEquals('Updated Business Name', $this->business->name);
        $this->assertEquals('Updated description', $this->business->description);
        $this->assertEquals('updated@business.com', $this->business->email);
        $this->assertEquals('555-1234', $this->business->phone);
        $this->assertEquals('123 Main St', $this->business->address);
        $this->assertEquals(15, $this->business->loyalty_stamps_required);
        $this->assertEquals('Updated reward', $this->business->loyalty_reward_description);
    }

    public function test_business_admin_cannot_update_with_invalid_data(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put('/business', [
                'name' => '',  // Invalid: required
                'email' => 'not-an-email',  // Invalid: not an email
                'loyalty_stamps_required' => 100,  // Invalid: max 50
            ]);

        $response->assertSessionHasErrors(['name', 'email', 'loyalty_stamps_required']);
    }

    public function test_user_without_business_cannot_access_business_routes(): void
    {
        $userWithoutBusiness = User::factory()->create([
            'business_id' => null,
            'role' => 'client',
        ]);

        $response = $this->actingAs($userWithoutBusiness)
            ->get('/business');

        $response->assertForbidden();
    }

    public function test_employee_cannot_update_business(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->put('/business', [
                'name' => 'Hacked Name',
            ]);

        $response->assertForbidden();

        $this->business->refresh();
        $this->assertEquals('Test Business', $this->business->name);
    }

    public function test_employee_can_view_but_not_update_business(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        // Can view
        $response = $this->actingAs($employee)
            ->get('/business');

        $response->assertOk();

        // Cannot access edit form
        $response = $this->actingAs($employee)
            ->get('/business/edit');

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_business_routes(): void
    {
        $this->get('/business')->assertRedirect('/login');
        $this->get('/business/edit')->assertRedirect('/login');
        $this->put('/business', [])->assertRedirect('/login');
    }

    public function test_user_from_different_business_cannot_access_another_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
        ]);

        $response = $this->actingAs($otherAdmin)
            ->get('/business');

        // Should see their own business, not the test business
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('business.id', $otherBusiness->id)
        );
    }

    // ──────────────────────────────────────────────────
    // Resource routes (/businesses) - super_admin CRUD
    // ──────────────────────────────────────────────────

    public function test_super_admin_can_list_all_businesses(): void
    {
        Business::factory()->count(3)->create();

        $response = $this->actingAs($this->superAdmin)
            ->get('/businesses');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Businesses/Index')
            ->has('businesses.data', 4) // 3 + 1 from setUp
        );
    }

    public function test_super_admin_can_create_business(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/businesses', [
                'name' => 'New Business',
                'slug' => 'new-business',
                'email' => 'new@business.com',
                'phone' => '+1234567890',
                'status' => 'active',
                'timezone' => 'America/New_York',
                'loyalty_stamps_required' => 8,
                'loyalty_reward_description' => 'Free service',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('businesses', [
            'name' => 'New Business',
            'slug' => 'new-business',
            'email' => 'new@business.com',
        ]);
    }

    public function test_super_admin_can_view_any_business(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get("/businesses/{$this->business->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Businesses/Show')
            ->where('business.id', $this->business->id)
        );
    }

    public function test_super_admin_can_update_any_business(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->put("/businesses/{$this->business->id}", [
                'name' => 'Super Updated',
                'email' => 'super@updated.com',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->business->refresh();
        $this->assertEquals('Super Updated', $this->business->name);
    }

    public function test_super_admin_can_delete_business(): void
    {
        $businessToDelete = Business::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->delete("/businesses/{$businessToDelete->id}");

        $response->assertRedirect('/businesses');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('businesses', [
            'id' => $businessToDelete->id,
        ]);
    }

    public function test_business_admin_cannot_list_all_businesses(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/businesses');

        $response->assertForbidden();
    }

    public function test_business_admin_cannot_create_business(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/businesses', [
                'name' => 'Unauthorized Business',
                'slug' => 'unauthorized',
                'email' => 'unauth@business.com',
            ]);

        $response->assertForbidden();
    }

    public function test_business_admin_cannot_delete_business(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->delete("/businesses/{$this->business->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('businesses', [
            'id' => $this->business->id,
        ]);
    }

    public function test_business_admin_can_still_view_own_business(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/business');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Business/Show')
            ->where('business.id', $this->business->id)
        );
    }

    public function test_super_admin_create_generates_invitation_code(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/businesses', [
                'name' => 'Auto Code Business',
                'slug' => 'auto-code',
                'email' => 'autocode@business.com',
                'status' => 'active',
                'timezone' => 'UTC',
            ]);

        $response->assertRedirect();

        $business = Business::where('slug', 'auto-code')->first();
        $this->assertNotNull($business);
        $this->assertNotEmpty($business->invitation_code);
    }
}
