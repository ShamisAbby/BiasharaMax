<?php

namespace Tests\Feature\WebsiteTemplates;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessType;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\WebsiteTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BusinessTypeSeeder::class, WebsiteTemplateSeeder::class]);
    }

    public function test_it_renders_the_default_template_for_a_businesss_type(): void
    {
        $businessType = BusinessType::query()->where('slug', 'restaurant')->firstOrFail();
        $business = $this->makeBusiness($businessType);

        $response = $this->get("/site/{$business->slug}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PublicWebsite/Show')
            ->where('business.name', $business->name)
            ->where('template.name', 'Restaurant Modern')
            ->has('template.pages', 6)
        );
    }

    public function test_each_business_type_has_a_published_default_template_with_pages(): void
    {
        $businessTypes = BusinessType::query()->whereNotNull('website_template_id')->get();

        $this->assertSame(11, $businessTypes->count());

        foreach ($businessTypes as $businessType) {
            $template = $businessType->websiteTemplate;

            $this->assertNotNull($template, "Business type {$businessType->slug} has no template.");
            $this->assertSame('published', $template->status);
            $this->assertTrue($template->is_default);
            $this->assertGreaterThan(0, $template->pages()->count());
        }
    }

    public function test_it_shows_a_graceful_state_when_business_type_has_no_template(): void
    {
        $businessType = BusinessType::query()->create([
            'name' => 'Untemplated Type',
            'slug' => 'untemplated-type',
            'status' => BusinessType::STATUS_ACTIVE,
        ]);
        $business = $this->makeBusiness($businessType);

        $response = $this->get("/site/{$business->slug}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PublicWebsite/Show')
            ->where('template', null)
        );
    }

    public function test_it_404s_for_an_unknown_business_slug(): void
    {
        $this->get('/site/does-not-exist')->assertNotFound();
    }

    private function makeBusiness(BusinessType $businessType): Business
    {
        $owner = User::factory()->create();

        return Business::query()->create([
            'name' => 'Test Business '.fake()->unique()->numerify('###'),
            'slug' => 'test-business-'.fake()->unique()->numerify('######'),
            'business_type' => $businessType->slug,
            'business_type_id' => $businessType->id,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+255700000000',
            'address' => '123 Main Street',
            'city' => 'Dar es Salaam',
            'owner_id' => $owner->id,
            'status' => Business::STATUS_TRIAL,
        ]);
    }
}
