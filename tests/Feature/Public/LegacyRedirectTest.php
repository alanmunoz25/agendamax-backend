<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Tests\TestCase;

class LegacyRedirectTest extends TestCase
{
    public function test_legacy_courses_redirect(): void
    {
        $response = $this->get('/paomakeup-beauty-salon/courses');
        $response->assertStatus(301);
        $response->assertRedirect('/negocio/paomakeup-beauty-salon/courses');
    }

    public function test_legacy_courses_with_path_redirect(): void
    {
        $response = $this->get('/paomakeup-beauty-salon/courses/curso-x');
        $response->assertStatus(301);
        $response->assertRedirect('/negocio/paomakeup-beauty-salon/courses/curso-x');
    }

    public function test_login_not_redirected(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }
}
