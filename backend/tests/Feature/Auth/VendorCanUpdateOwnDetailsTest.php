<?php

namespace Tests\Feature\Auth;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Everything a vendor gives at signup, they can change afterwards.
 *
 * The phone number is the one that mattered and the one that was missing.
 * Registration collects it, `ProfileUpdateRequest` validates it, the column
 * is fillable — and the form never rendered the field, so the only way to
 * correct it was a database edit. Mobile-money checkout reads that column,
 * which is how a payment failed against a placeholder number the owner had
 * no way to fix.
 */
class VendorCanUpdateOwnDetailsTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_an_owner_can_change_their_phone_number(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->patch(route('profile.update'), [
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => '0712345678',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('0712345678', $owner->fresh()->phone);
    }

    /**
     * The form sends every field on every save. A blank phone must mean
     * "cleared", not be rejected — but it must also not be silently
     * ignored, or a customer trying to remove a wrong number would appear
     * to succeed and change nothing.
     */
    public function test_clearing_the_phone_is_allowed(): void
    {
        [$owner] = $this->createOwnerWithBusiness();
        $owner->forceFill(['phone' => '0712345678'])->save();

        $this->actingAs($owner)
            ->patch(route('profile.update'), [
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($owner->fresh()->phone);
    }

    public function test_the_phone_is_shared_to_the_page(): void
    {
        [$owner] = $this->createOwnerWithBusiness();
        $owner->forceFill(['phone' => '0755000111'])->save();

        // If this prop were missing, the form would render an empty phone
        // box and the next save would wipe a number the owner never
        // touched.
        $this->actingAs($owner)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('auth.user.phone', '0755000111'));
    }

    public function test_business_details_remain_editable(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->patch(route('settings.business.update'), [
                'name' => 'Renamed Shop',
                'business_type' => 'retail',
                'phone' => '0788000222',
                'country' => 'TZ',
                'currency' => 'TZS',
                'timezone' => 'Africa/Dar_es_Salaam',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $business->fresh();

        $this->assertSame('Renamed Shop', $fresh->name);
        $this->assertSame('0788000222', $fresh->phone);
    }
}
