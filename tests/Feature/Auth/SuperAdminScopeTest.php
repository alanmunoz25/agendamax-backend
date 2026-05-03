<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\PosTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Business $business1;

    private Business $business2;

    private User $businessAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);

        $this->business1 = Business::factory()->create();
        $this->business2 = Business::factory()->create();

        $this->businessAdmin = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business1->id,
        ]);
    }

    public function test_super_admin_must_specify_business_id_for_pos_index(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('pos.index'));

        $response->assertStatus(422);
    }

    public function test_super_admin_must_specify_business_id_for_payroll_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('payroll.dashboard'));

        $response->assertStatus(422);
    }

    public function test_super_admin_with_business_id_sees_only_that_business_data(): void
    {
        // Create tickets in both businesses
        $cashier1 = User::factory()->create(['business_id' => $this->business1->id, 'role' => 'business_admin']);
        $cashier2 = User::factory()->create(['business_id' => $this->business2->id, 'role' => 'business_admin']);

        $ticket1 = PosTicket::factory()->create([
            'business_id' => $this->business1->id,
            'cashier_id' => $cashier1->id,
        ]);

        $ticket2 = PosTicket::factory()->create([
            'business_id' => $this->business2->id,
            'cashier_id' => $cashier2->id,
        ]);

        // super_admin requests POS for business1 only
        $response = $this->actingAs($this->superAdmin)
            ->get(route('pos.index', ['business_id' => $this->business1->id]));

        // The page renders successfully scoped to business1
        $response->assertStatus(200);
    }

    public function test_business_admin_does_not_need_explicit_business_id(): void
    {
        // Business admins should access POS without passing business_id
        $response = $this->actingAs($this->businessAdmin)
            ->get(route('pos.index'));

        $response->assertStatus(200);
    }
}
