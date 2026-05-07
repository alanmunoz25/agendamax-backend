<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessLandingGroupingTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'slug' => 'grouping-salon',
            'status' => 'active',
        ]);
    }

    public function test_services_include_category_and_parent_relationships(): void
    {
        $parent = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Hair',
            'parent_id' => null,
        ]);

        $child = ServiceCategory::factory()->child($parent)->create([
            'name' => 'Cuts',
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $child->id,
            'is_active' => true,
        ]);

        $response = $this->get('/negocio/grouping-salon');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/BusinessLanding')
            ->has('services', 1)
            ->has('services.0.service_category')
            ->has('services.0.service_category.parent')
            ->where('services.0.service_category.name', 'Cuts')
            ->where('services.0.service_category.parent.name', 'Hair')
        );
    }

    public function test_landing_returns_all_18_services_across_3_parent_3_sub_3_services(): void
    {
        $parentNames = ['Hair', 'Nails', 'Skin'];

        foreach ($parentNames as $parentName) {
            $parent = ServiceCategory::factory()->create([
                'business_id' => $this->business->id,
                'name' => $parentName,
                'parent_id' => null,
            ]);

            $subNames = [$parentName.' A', $parentName.' B'];

            foreach ($subNames as $subName) {
                $child = ServiceCategory::factory()->child($parent)->create([
                    'name' => $subName,
                ]);

                Service::factory()->count(3)->create([
                    'business_id' => $this->business->id,
                    'service_category_id' => $child->id,
                    'is_active' => true,
                ]);
            }
        }

        $response = $this->get('/negocio/grouping-salon');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/BusinessLanding')
            ->has('services', 18)
        );
    }

    public function test_services_without_category_are_included(): void
    {
        Service::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'service_category_id' => null,
            'is_active' => true,
        ]);

        $response = $this->get('/negocio/grouping-salon');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/BusinessLanding')
            ->has('services', 2)
        );
    }

    public function test_category_parent_names_appear_in_services_payload(): void
    {
        $parent = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Wellness',
            'parent_id' => null,
        ]);

        $child = ServiceCategory::factory()->child($parent)->create([
            'name' => 'Massage',
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $child->id,
            'is_active' => true,
        ]);

        $response = $this->get('/negocio/grouping-salon');

        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString('Wellness', $content);
        $this->assertStringContainsString('Massage', $content);
    }

    public function test_cover_and_banner_urls_are_exposed_in_business_prop(): void
    {
        $this->business->update([
            'banner_url' => 'businesses/banners/test-banner.jpg',
            'cover_image_url' => 'businesses/covers/test-cover.jpg',
        ]);

        $response = $this->get('/negocio/grouping-salon');

        $response->assertOk();
        // The Business model accessor converts relative paths to full storage URLs.
        $response->assertInertia(fn ($page) => $page
            ->component('Public/BusinessLanding')
            ->where('business.banner_url', fn ($url) => str_contains($url, 'businesses/banners/test-banner.jpg'))
            ->where('business.cover_image_url', fn ($url) => str_contains($url, 'businesses/covers/test-cover.jpg'))
        );
    }
}
