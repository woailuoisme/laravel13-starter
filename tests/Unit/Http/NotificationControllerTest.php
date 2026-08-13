<?php

declare(strict_types=1);

use App\Http\Controllers\V1\NotificationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

it('retrieves paginated notifications for user', function (): void {
    /** @var User $user */
    $user = User::factory()->create([
        'email' => 'notify-test@example.com',
        'password' => Hash::make('password123'),
    ]);

    auth('api')->login($user);

    $controller = new NotificationController;
    $request = Request::create('/notifications', 'GET', ['per_page' => 10]);

    $response = $controller->index($request);

    expect($response->status())
        ->toBe(200)
        ->and($response->getData(true))
        ->toMatchArray([
            'success' => true,
        ]);
});

it('marks a specific notification as read and deletes it', function (): void {
    /** @var User $user */
    $user = User::factory()->create([
        'email' => 'notify-action@example.com',
        'password' => Hash::make('password123'),
    ]);

    $notificationId = (string) Str::uuid();
    $notification = DatabaseNotification::query()->create([
        'id' => $notificationId,
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->getKey(),
        'data' => ['title' => 'Test Notification'],
        'read_at' => null,
    ]);

    auth('api')->login($user);

    $controller = new NotificationController;

    $readResponse = $controller->markAsRead($notificationId);
    expect($readResponse->status())
        ->toBe(200)
        ->and($readResponse->getData(true))
        ->toMatchArray([
            'success' => true,
        ]);

    $fresh = $notification->fresh();
    expect($fresh instanceof DatabaseNotification ? $fresh->getAttribute('read_at') : null)->not->toBeNull();

    $allReadResponse = $controller->markAllAsRead();
    expect($allReadResponse->status())->toBe(200);

    $deleteResponse = $controller->destroy($notificationId);
    expect($deleteResponse->status())->toBe(200)->and(DatabaseNotification::query()->find($notificationId))->toBeNull();
});
