<?php

namespace Tests\Feature\Support;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Notifications\SupportTicketRepliedNotification;
use App\Domain\Support\Services\SupportTicketService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Businesses raising tickets with the platform team.
 *
 * The isolation tests carry the weight here. **SupportTicket has no
 * `BelongsToTenant` trait** — correctly, because platform admins must
 * see every business's tickets — which means nothing scopes these
 * queries automatically. Every guard is hand-written, and a hand-written
 * guard is one someone can forget on the next endpoint added to this
 * controller.
 *
 * A leak here is worse than most: support threads are where customers
 * paste invoice numbers, account details and screenshots of their own
 * data.
 */
class BusinessSupportTicketTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_a_business_can_open_a_ticket(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->post(route('support.store'), [
                'subject' => 'Cannot complete a sale',
                'description' => 'The POS screen hangs when I press checkout on any product.',
                'category' => 'technical',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_tickets', [
            'business_id' => $business->id,
            'opened_by_type' => 'user',
            'opened_by_id' => $owner->id,
            'status' => SupportTicket::STATUS_OPEN,
        ]);
    }

    public function test_a_thin_description_is_rejected(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->from(route('support.index'))
            ->post(route('support.store'), [
                'subject' => 'Broken',
                'description' => 'help',
                'category' => 'technical',
                'priority' => 'high',
            ])
            ->assertSessionHasErrors(['subject', 'description']);

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_a_business_only_sees_its_own_tickets(): void
    {
        [$ownerA, $businessA] = $this->createOwnerWithBusiness();
        [$ownerB] = $this->createOwnerWithBusiness();

        $mine = $this->openTicket($businessA->id, $ownerA->id, 'Mine');

        $this->actingAs($ownerB)
            ->get(route('support.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Support/Index')
                ->where('tickets.data', [])
            );

        $this->assertNotNull($mine->fresh());
    }

    /**
     * The one that matters most.
     *
     * A ticket id is a UUID, so this is not a guessing attack — it is
     * what happens when a link is pasted into the wrong chat, or an
     * endpoint is added later without the scope.
     */
    public function test_another_business_cannot_read_a_ticket_by_id(): void
    {
        [$ownerA, $businessA] = $this->createOwnerWithBusiness();
        [$ownerB] = $this->createOwnerWithBusiness();

        $ticket = $this->openTicket($businessA->id, $ownerA->id, 'Private matter');

        // 404 rather than 403: being told a ticket exists but is
        // forbidden is itself a disclosure.
        $this->actingAs($ownerB)
            ->get(route('support.show', $ticket->id))
            ->assertNotFound();

        $this->actingAs($ownerB)
            ->post(route('support.reply', $ticket->id), ['body' => 'Injecting a reply'])
            ->assertNotFound();

        $this->actingAs($ownerB)
            ->post(route('support.close', $ticket->id))
            ->assertNotFound();

        $this->assertSame(SupportTicket::STATUS_OPEN, $ticket->fresh()->status);
    }

    public function test_internal_notes_are_never_shown_to_the_business(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $admin = PlatformUser::factory()->create();

        $ticket = $this->openTicket($business->id, $owner->id, 'Billing question');
        $service = app(SupportTicketService::class);

        $service->reply($ticket, 'platform_user', $admin->id, 'Looking into it now.');
        $service->reply($ticket, 'platform_user', $admin->id, 'This customer is late paying — check finance first.', true);

        $this->actingAs($owner)
            ->get(route('support.show', $ticket->id))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Support/Show')
                ->has('messages', 1)
                ->where('messages.0.body', 'Looking into it now.')
            );
    }

    /**
     * A customer replying to a "resolved" ticket is disagreeing that it
     * is resolved. If it stayed resolved, that reply would sit in a
     * queue nobody reviews because the ticket reads as done.
     */
    public function test_replying_to_a_resolved_ticket_reopens_it(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $ticket = $this->openTicket($business->id, $owner->id, 'Still broken');
        app(SupportTicketService::class)->resolve($ticket);

        $this->actingAs($owner)
            ->post(route('support.reply', $ticket->id), ['body' => 'This is still happening today.'])
            ->assertRedirect();

        $this->assertSame(SupportTicket::STATUS_REOPENED, $ticket->fresh()->status);
    }

    public function test_a_closed_ticket_cannot_be_replied_to(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $ticket = $this->openTicket($business->id, $owner->id, 'Done with this');
        app(SupportTicketService::class)->close($ticket);

        $this->actingAs($owner)
            ->post(route('support.reply', $ticket->id), ['body' => 'One more thing'])
            ->assertForbidden();
    }

    public function test_a_support_reply_notifies_the_business_but_an_internal_note_does_not(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();
        $admin = PlatformUser::factory()->create();

        $ticket = $this->openTicket($business->id, $owner->id, 'Question');
        $service = app(SupportTicketService::class);

        $service->reply($ticket, 'platform_user', $admin->id, 'Here is the answer.');
        Notification::assertSentTo($owner, SupportTicketRepliedNotification::class);

        Notification::fake();

        // Agents talking to each other. Notifying would announce a
        // conversation the customer is not meant to see.
        $service->reply($ticket, 'platform_user', $admin->id, 'Internal: escalate to billing.', true);
        Notification::assertNothingSent();
    }

    public function test_a_business_reply_does_not_notify_the_business_about_itself(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();
        $ticket = $this->openTicket($business->id, $owner->id, 'Question');

        app(SupportTicketService::class)->reply($ticket, 'user', $owner->id, 'Any update?');

        Notification::assertNothingSent();
    }

    public function test_an_open_ticket_reaches_the_platform_alert_feed(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $admin = PlatformUser::factory()->create();

        $ticket = $this->openTicket($business->id, $owner->id, 'Urgent problem', SupportTicket::PRIORITY_URGENT);

        $items = $this->actingAs($admin, 'platform')
            ->getJson(route('platform.notifications.live'))
            ->json('items');

        $entry = collect($items)->firstWhere('id', 'support-ticket-'.$ticket->id);

        $this->assertNotNull($entry, 'An open ticket should surface to platform admins.');
        // The customer's own priority decides severity — they are the
        // only party who knows whether their business has stopped.
        $this->assertSame('critical', $entry['severity']);
    }

    public function test_a_guest_cannot_reach_support(): void
    {
        $this->get(route('support.index'))->assertRedirect(route('login'));
    }

    private function openTicket(
        string $businessId,
        string $userId,
        string $subject,
        string $priority = SupportTicket::PRIORITY_MEDIUM,
    ): SupportTicket {
        return app(SupportTicketService::class)->open([
            'business_id' => $businessId,
            'opened_by_type' => 'user',
            'opened_by_id' => $userId,
            'category' => 'technical',
            'priority' => $priority,
            'subject' => $subject,
            'description' => 'A description long enough to pass validation comfortably.',
        ]);
    }
}
