<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_primary_business_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'primary_business_id'));
        $this->assertFalse(Schema::hasColumn('users', 'business_id'));
    }
}
