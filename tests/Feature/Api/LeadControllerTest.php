<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();
    }

    public function test_can_create_lead_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'lead@example.com',
            'phone' => '+1234567890',
            'business_id' => $this->business->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'phone', 'role', 'business_id'],
            ])
            ->assertJsonPath('data.role', 'lead')
            ->assertJsonPath('data.business_id', $this->business->id);

        $this->assertDatabaseHas('users', [
            'email' => 'lead@example.com',
            'business_id' => $this->business->id,
            'role' => 'lead',
        ]);
    }

    public function test_lead_creation_requires_name_and_email(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'business_id' => $this->business->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_lead_creation_requires_business_id(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'lead@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['business_id']);
    }

    public function test_can_create_multiple_leads_with_same_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'business_id' => $this->business->id,
            'role' => 'lead',
        ]);

        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'existing@example.com',
            'business_id' => $this->business->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_lead_can_duplicate_email_across_businesses(): void
    {
        $otherBusiness = Business::factory()->create();

        User::factory()->create([
            'email' => 'shared@example.com',
            'business_id' => $this->business->id,
        ]);

        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'shared@example.com',
            'business_id' => $otherBusiness->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_lead_is_created_with_lead_role(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'lead@example.com',
            'business_id' => $this->business->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.role', 'lead');
    }

    public function test_lead_can_include_interested_service_id(): void
    {
        $service = Service::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'lead@example.com',
            'business_id' => $this->business->id,
            'interested_service_id' => $service->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'lead@example.com',
            'interested_service_id' => $service->id,
        ]);
    }

    public function test_lead_with_invalid_service_id_fails(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'lead@example.com',
            'business_id' => $this->business->id,
            'interested_service_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['interested_service_id']);
    }

    public function test_lead_can_include_notes(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'lead@example.com',
            'business_id' => $this->business->id,
            'notes' => 'Interested in weekend appointments for a special event.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.notes', 'Interested in weekend appointments for a special event.');

        $this->assertDatabaseHas('users', [
            'email' => 'lead@example.com',
            'notes' => 'Interested in weekend appointments for a special event.',
        ]);
    }

    public function test_lead_can_include_source(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'lead@example.com',
            'business_id' => $this->business->id,
            'source' => 'appointment_form',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.source', 'appointment_form');

        $this->assertDatabaseHas('users', [
            'email' => 'lead@example.com',
            'source' => 'appointment_form',
        ]);
    }

    public function test_lead_with_invalid_source_fails(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'lead@example.com',
            'business_id' => $this->business->id,
            'source' => 'invalid_source',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source']);
    }

    public function test_can_create_multiple_leads_with_same_phone(): void
    {
        User::factory()->create([
            'phone' => '+1234567890',
            'business_id' => $this->business->id,
            'role' => 'lead',
        ]);

        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Lead User',
            'email' => 'unique-email@example.com',
            'phone' => '+1234567890',
            'business_id' => $this->business->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_lead_with_all_fields(): void
    {
        $service = Service::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Full Lead',
            'email' => 'full@example.com',
            'phone' => '+5551234567',
            'business_id' => $this->business->id,
            'interested_service_id' => $service->id,
            'notes' => 'Wants a quote for an event.',
            'source' => 'event_quote',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Full Lead')
            ->assertJsonPath('data.email', 'full@example.com')
            ->assertJsonPath('data.phone', '+5551234567')
            ->assertJsonPath('data.notes', 'Wants a quote for an event.')
            ->assertJsonPath('data.source', 'event_quote')
            ->assertJsonPath('data.interested_service_id', $service->id);
    }
}
