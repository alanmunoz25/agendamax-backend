<?php

namespace Tests\Feature\Policies;

use Tests\TestCase;

class EmployeePolicyTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
