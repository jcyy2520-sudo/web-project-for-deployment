<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Mock time to a Monday to avoid weekend booking restrictions in tests
        \Carbon\Carbon::setTestNow('2026-05-04 12:00:00');
    }
}
