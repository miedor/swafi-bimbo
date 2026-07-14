<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_login_page_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('SWAFI');
    }
}
