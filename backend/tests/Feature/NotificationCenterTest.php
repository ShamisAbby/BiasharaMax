<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_user_can_list_their_notifications_with_unread_count(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Domain\\Inventory\\Notifications\\LowStockAlert',
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => $owner->getKey(),
            'data' => [
                'title' => 'Low stock alert',
                'message' => '"Widget" is running low.',
                'url' => '/inventory/products/123',
                'icon' => 'exclamation-triangle',
            ],
        ]);

        // getJson() (not get()) — NotificationController::index() branches
        // on $request->wantsJson()/ajax(), which a plain get() never
        // satisfies (no Accept/X-Requested-With header), so it would
        // render the Inertia HTML page instead of the JSON payload these
        // assertions expect.
        $response = $this->actingAs($owner)->getJson(route('notifications.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'notifications');
        $response->assertJsonPath('unread_count', 1);
        $response->assertJsonPath('notifications.0.title', 'Low stock alert');
    }

    public function test_user_can_mark_a_single_notification_as_read(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $notification = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Domain\\Inventory\\Notifications\\LowStockAlert',
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => $owner->getKey(),
            'data' => ['title' => 'x', 'message' => 'y', 'url' => null, 'icon' => null],
        ]);

        $this->actingAs($owner)
            ->post(route('notifications.read', $notification->id))
            ->assertNoContent();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        DatabaseNotification::insert([
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Domain\\Inventory\\Notifications\\LowStockAlert',
                'notifiable_type' => $owner->getMorphClass(),
                'notifiable_id' => $owner->getKey(),
                'data' => json_encode(['title' => 'x', 'message' => 'y', 'url' => null, 'icon' => null]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Domain\\Inventory\\Notifications\\LowStockAlert',
                'notifiable_type' => $owner->getMorphClass(),
                'notifiable_id' => $owner->getKey(),
                'data' => json_encode(['title' => 'x', 'message' => 'y', 'url' => null, 'icon' => null]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($owner)
            ->post(route('notifications.read-all'))
            ->assertNoContent();

        $this->assertSame(0, $owner->unreadNotifications()->count());
    }

    public function test_user_cannot_see_another_users_notifications(): void
    {
        [$owner] = $this->createOwnerWithBusiness();
        [$otherOwner] = $this->createOwnerWithBusiness();

        DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Domain\\Inventory\\Notifications\\LowStockAlert',
            'notifiable_type' => $otherOwner->getMorphClass(),
            'notifiable_id' => $otherOwner->getKey(),
            'data' => ['title' => 'x', 'message' => 'y', 'url' => null, 'icon' => null],
        ]);

        // getJson() (not get()) — NotificationController::index() branches
        // on $request->wantsJson()/ajax(), which a plain get() never
        // satisfies (no Accept/X-Requested-With header), so it would
        // render the Inertia HTML page instead of the JSON payload these
        // assertions expect.
        $response = $this->actingAs($owner)->getJson(route('notifications.index'));

        $response->assertJsonCount(0, 'notifications');
        $response->assertJsonPath('unread_count', 0);
    }
}
