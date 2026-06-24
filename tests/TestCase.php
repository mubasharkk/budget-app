<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests render Inertia pages but never build frontend assets,
        // so stub Vite to avoid "Vite manifest not found" failures (e.g. in CI).
        $this->withoutVite();
    }
}
