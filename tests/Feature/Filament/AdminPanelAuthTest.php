<?php

declare(strict_types=1);

it('redirects guests from the admin panel to the login page', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('keeps api guest requests as unauthorized responses', function (): void {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});
