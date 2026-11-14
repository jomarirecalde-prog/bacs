<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_shows_landing_page_for_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('Welcome Back');
    }

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Welcome Back');
    }
}
