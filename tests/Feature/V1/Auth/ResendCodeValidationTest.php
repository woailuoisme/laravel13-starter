<?php

declare(strict_types=1);

it('rejects invalid resend code payloads', function (): void {
    $this->postJson('/api/v1/auth/code/resend', [
        'email' => 'not-an-email',
        'action' => 'invalid',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'action']);
});
