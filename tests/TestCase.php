<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected bool $authenticateByDefault = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->authenticateByDefault && Schema::hasTable('users')) {
            $this->actingAs(User::factory()->create());
        }
    }
}
