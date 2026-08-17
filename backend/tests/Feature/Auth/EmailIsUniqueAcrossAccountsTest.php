<?php

namespace Tests\Feature\Auth;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use App\Domain\RBAC\Models\PlatformRole;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * One email address, one account, across both user tables.
 *
 * `users` and `platform_users` each carry their own unique index, so
 * duplicates *within* a table were already impossible. Nothing spanned
 * the two: an address could hold a vendor account and a platform admin
 * account simultaneously, and then `/login` and `/admin/login` would each
 * accept it with a different password while a reset link silently fixed
 * only one of them.
 *
 * These tests exist because that guarantee now lives in application code
 * rather than in the schema. A database constraint cannot be deleted by
 * accident; a validation rule dropped from a `rules()` array can, and the
 * result is invisible until two people are locked out of one address.
 */
class EmailIsUniqueAcrossAccountsTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            PlatformRoleSeeder::class,
            SubscriptionPlanSeeder::class,
            BusinessTypeSeeder::class,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(string $email): array
    {
        return [
            'owner_name' => 'Jane Doe',
            'owner_email' => $email,
            'owner_phone' => '+255700000000',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'business_name' => 'Jane General Store '.fake()->unique()->numerify('###'),
            'business_type' => 'retail',
            'business_phone' => null,
            'country' => 'TZ',
            'currency' => 'TZS',
            'subscription_plan_id' => SubscriptionPlan::query()->where('slug', 'starter')->value('id'),
        ];
    }

    public function test_a_platform_admin_email_cannot_register_a_business(): void
    {
        PlatformUser::factory()->create(['email' => 'admin@biasharamax.com']);

        $this->post('/register', $this->registrationPayload('admin@biasharamax.com'))
            ->assertSessionHasErrors('owner_email');

        $this->assertSame(0, User::query()->where('email', 'admin@biasharamax.com')->count());
    }

    /**
     * Capitalisation must not be a way around it.
     *
     * MySQL and MariaDB compare these columns case-insensitively, so on
     * production this would be caught even without the rule. The rule
     * lower-cases both sides itself rather than depending on that: the
     * collation is a setting, and a guarantee that quietly evaporates
     * when a setting changes is not a guarantee.
     */
    public function test_case_does_not_defeat_the_check(): void
    {
        PlatformUser::factory()->create(['email' => 'admin@biasharamax.com']);

        $this->post('/register', $this->registrationPayload('Admin@BiasharaMax.com'))
            ->assertSessionHasErrors('owner_email');
    }

    public function test_a_vendor_email_cannot_be_invited_as_a_platform_admin(): void
    {
        Notification::fake();

        [$owner] = $this->createOwnerWithBusiness();

        $inviter = PlatformUser::factory()->create();
        $role = PlatformRole::query()->where('slug', PlatformRole::PLATFORM_ADMIN)->first();

        $this->actingAs($inviter, 'platform')
            ->post(route('platform.staff.store'), [
                'name' => 'New Admin',
                'email' => $owner->email,
                'platform_role_id' => $role->id,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, PlatformUser::query()->where('email', $owner->email)->count());
    }

    /**
     * The error must not say which kind of account holds the address.
     *
     * Anyone can reach the signup form. A message distinguishing "that
     * belongs to a vendor" from "that belongs to an administrator" turns
     * it into a free tool for discovering who runs the platform.
     */
    public function test_the_message_does_not_reveal_which_table_matched(): void
    {
        PlatformUser::factory()->create(['email' => 'admin@biasharamax.com']);

        $response = $this->post('/register', $this->registrationPayload('admin@biasharamax.com'));

        $message = session('errors')->first('owner_email');

        $this->assertStringNotContainsStringIgnoringCase('admin', $message);
        $this->assertStringNotContainsStringIgnoringCase('platform', $message);
        $this->assertStringNotContainsStringIgnoringCase('staff', $message);
    }

    /**
     * The rule must not fire on the account's own address, or nobody
     * could ever save their profile without also changing their email.
     */
    public function test_a_user_may_keep_their_own_address_when_editing_their_profile(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->patch(route('profile.update'), [
                'name' => 'Renamed Owner',
                'email' => $owner->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed Owner', $owner->fresh()->name);
    }

    public function test_a_profile_edit_cannot_take_a_platform_admin_address(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        PlatformUser::factory()->create(['email' => 'admin@biasharamax.com']);

        $this->actingAs($owner)
            ->patch(route('profile.update'), [
                'name' => $owner->name,
                'email' => 'admin@biasharamax.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertNotSame('admin@biasharamax.com', $owner->fresh()->email);
    }
}
