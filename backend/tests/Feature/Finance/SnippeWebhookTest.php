<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Support\SnippeSignatureVerifier;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * The webhook is the only thing that grants a paid subscription.
 *
 * It is public, unauthenticated and reachable by anyone who knows the URL,
 * so the signature is the entire access control. Every test here is about
 * one question: can someone who is not Snippe make this endpoint hand out
 * a subscription?
 */
class SnippeWebhookTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private string $secret = 'whsec_test_key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, SubscriptionPlanSeeder::class]);

        PaymentGateway::factory()->create([
            'provider' => PaymentGateway::PROVIDER_SNIPPE,
            'slug' => 'snippe',
            'webhook_secret' => $this->secret,
            'credentials' => ['api_key' => 'sk_test'],
            'is_enabled' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload, ?string $secret = null, ?int $timestamp = null): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret ?? $this->secret);

        return $this->call(
            'POST',
            route('webhooks.snippe'),
            [],
            [],
            [],
            [
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
                'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );
    }

    /**
     * @return array{0: Subscription, 1: array<string, mixed>}
     */
    private function pendingSubscription(string $slug = 'quarterly'): array
    {
        [, $business] = $this->createOwnerWithBusiness();
        $plan = SubscriptionPlan::query()->where('slug', $slug)->first();

        $subscription = $business->subscription;
        $subscription->forceFill([
            'subscription_plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_PENDING_PAYMENT,
        ])->save();

        return [$subscription, [
            'id' => 'evt_'.fake()->uuid(),
            'type' => 'payment.completed',
            'data' => [
                'reference' => 'pi_'.fake()->uuid(),
                'status' => 'completed',
                'amount' => ['value' => (float) $plan->price, 'currency' => 'TZS'],
                'metadata' => [
                    'reference' => 'BM-'.fake()->numerify('######'),
                    'business_id' => $business->getKey(),
                    'subscription_id' => $subscription->getKey(),
                ],
            ],
        ]];
    }

    public function test_a_signed_completed_payment_activates_the_subscription(): void
    {
        [$subscription, $payload] = $this->pendingSubscription();

        $this->send($payload)->assertOk();

        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->fresh()->status);
    }

    public function test_an_unsigned_request_is_refused(): void
    {
        [$subscription, $payload] = $this->pendingSubscription();

        $this->postJson(route('webhooks.snippe'), $payload)->assertStatus(400);

        $this->assertSame(Subscription::STATUS_PENDING_PAYMENT, $subscription->fresh()->status);
    }

    public function test_a_wrong_secret_is_refused(): void
    {
        [$subscription, $payload] = $this->pendingSubscription();

        $this->send($payload, secret: 'whsec_not_the_real_one')->assertStatus(400);

        $this->assertSame(Subscription::STATUS_PENDING_PAYMENT, $subscription->fresh()->status);
    }

    /**
     * The replay this implementation exists to stop.
     *
     * The integration this was ported from also accepted a signature over
     * the body alone, with no timestamp. Such a signature never expires, so
     * one captured webhook could be replayed forever — each time granting
     * another paid subscription.
     */
    public function test_a_stale_timestamp_is_refused(): void
    {
        [$subscription, $payload] = $this->pendingSubscription();

        $this->send($payload, timestamp: time() - (SnippeSignatureVerifier::MAX_AGE_SECONDS + 60))
            ->assertStatus(400);

        $this->assertSame(Subscription::STATUS_PENDING_PAYMENT, $subscription->fresh()->status);
    }

    public function test_the_same_event_delivered_twice_only_counts_once(): void
    {
        [$subscription, $payload] = $this->pendingSubscription();

        $this->send($payload)->assertOk();
        $firstEnd = $subscription->fresh()->current_period_end;

        $this->send($payload)->assertOk();

        $this->assertEquals(
            $firstEnd->timestamp,
            $subscription->fresh()->current_period_end->timestamp,
            'A duplicate delivery extended the subscription a second time.',
        );
    }

    /**
     * Paying the three-month price must not buy twelve months.
     */
    public function test_an_underpayment_does_not_activate(): void
    {
        [$subscription, $payload] = $this->pendingSubscription('yearly');

        $payload['data']['amount']['value'] = 90000; // the 3-month price

        $this->send($payload)->assertOk();

        $this->assertSame(Subscription::STATUS_PENDING_PAYMENT, $subscription->fresh()->status);
    }

    public function test_a_failed_payment_does_not_activate(): void
    {
        [$subscription, $payload] = $this->pendingSubscription();

        $payload['type'] = 'payment.failed';
        $payload['data']['status'] = 'failed';

        $this->send($payload)->assertOk();

        $this->assertSame(Subscription::STATUS_PENDING_PAYMENT, $subscription->fresh()->status);
    }

    /**
     * The term runs from the payment, not from when the plan was chosen.
     */
    public function test_the_term_length_comes_from_the_plan(): void
    {
        [$subscription, $payload] = $this->pendingSubscription('yearly');

        $this->send($payload)->assertOk();

        $fresh = $subscription->fresh();

        $this->assertSame(
            12,
            (int) round($fresh->current_period_start->diffInMonths($fresh->current_period_end)),
        );
    }
}
