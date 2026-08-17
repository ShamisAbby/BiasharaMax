<?php

namespace Database\Seeders;

use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Illuminate\Database\Seeder;

/**
 * "Aurora Luxe" — a premium storefront template.
 *
 * Deliberately different from the per-business-type templates in
 * WebsiteTemplateSeeder in three ways:
 *
 *  - `business_type_id` is null, so it belongs to no single trade and can
 *    be picked by any business.
 *  - `is_default` is false, so seeding it never silently replaces the
 *    template a business type already points at. An owner opts in.
 *  - `header_config.style` is `luxe`, which is the flag Show.tsx reads to
 *    switch on the presentation layer in
 *    resources/css/public-website-luxe.css — scroll-reveal, the drifting
 *    hero gradient, hover elevation and the metallic wordmark. Any other
 *    template setting that flag gets the same treatment.
 *
 * Every field maps to the same renderer the other templates use, so
 * there is no bespoke page or route behind it: it is real, editable
 * template data, not a mock-up. All copy is generic placeholder text an
 * owner is expected to replace — no claims are made about any real
 * business.
 */
class LuxeWebsiteTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = WebsiteTemplate::query()->updateOrCreate(
            ['slug' => 'aurora-luxe'],
            [
                'name' => 'Aurora Luxe',
                'business_type_id' => null,
                'description' => 'A premium, animated storefront with a full-bleed gradient hero, scroll-reveal sections and generous editorial spacing. Suits studios, boutiques, salons and any business selling on presentation.',
                'status' => WebsiteTemplate::STATUS_PUBLISHED,
                'is_default' => false,
                'version' => '1.0.0',
                // `sort_order` is an UNSIGNED smallint, so a negative
                // value to sort it first would fail on insert. The
                // per-type templates all seed at 0; 1 keeps this one
                // adjacent to them without pretending to outrank them.
                'sort_order' => 1,
                'theme_colors' => [
                    // Deep plum and champagne — the palette carries the
                    // "luxury" read as much as the layout does. All seven
                    // keys are required; the renderer maps each to a
                    // --brand-* custom property.
                    'primary' => '#6D28D9',
                    'secondary' => '#2E1065',
                    'accent' => '#D4AF37',
                    'background' => '#FBFAFF',
                    'surface' => '#F4F1FB',
                    'text' => '#1B1035',
                    'muted' => '#6B6385',
                ],
                'typography' => [
                    // A high-contrast serif for headings against a neutral
                    // sans for body copy — the pairing does most of the
                    // work. Both are on Bunny Fonts, which the renderer
                    // already loads from.
                    'heading_font' => 'Playfair Display',
                    'body_font' => 'Inter',
                ],
                'header_config' => [
                    'style' => 'luxe',
                    'show_cta' => true,
                    'cta_label' => 'Book a consultation',
                ],
                'footer_config' => [
                    'tagline' => 'Considered work, made to last.',
                    'show_social' => true,
                ],
                'navigation_config' => [
                    'cta_label' => 'Book a consultation',
                ],
                'seo_settings' => [
                    'title_suffix' => '| Studio',
                    'meta_description' => 'A considered, appointment-led studio. See our work, read what clients say, and book a consultation.',
                ],
                'social_media' => [],
            ],
        );

        foreach ($this->pages() as $index => $page) {
            $template->pages()->updateOrCreate(
                ['slug' => $page['slug']],
                [
                    'type' => $page['type'],
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'is_enabled' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * @return list<array{type: string, title: string, slug: string, content: array<string, mixed>}>
     */
    private function pages(): array
    {
        return [
            [
                'type' => 'homepage',
                'title' => 'Home',
                'slug' => 'home',
                'content' => [
                    'hero' => [
                        'eyebrow' => 'By appointment',
                        'headline' => 'Work worth waiting for',
                        'subheadline' => 'A small studio taking a limited number of clients at a time, so every piece gets the attention it deserves.',
                        'primary_cta' => 'Book a consultation',
                        'secondary_cta' => 'See the work',
                    ],
                    'features' => [
                        ['title' => 'Considered process', 'description' => 'We start with a conversation, not a quote. You will know exactly what we are making and why before anything begins.'],
                        ['title' => 'Made to last', 'description' => 'Materials chosen to age well rather than photograph well. Everything we make is built to be used.'],
                        ['title' => 'One at a time', 'description' => 'We keep the books deliberately short. Fewer projects, each with room to breathe.'],
                    ],
                ],
            ],
            [
                'type' => 'about',
                'title' => 'About',
                'slug' => 'about',
                'content' => [
                    'heading' => 'A studio built around fewer, better projects',
                    'body' => 'We began with a simple preference: do less work, and do it properly. That shapes everything — how many clients we take, how long a piece takes, and why we would rather turn work away than rush it. Replace this with your own story; it is the part visitors read most closely.',
                ],
            ],
            [
                'type' => 'services',
                'title' => 'Services',
                'slug' => 'services',
                'content' => [
                    'heading' => 'What we do',
                    'intro' => 'Three ways to work with us. Every project starts with the same consultation.',
                    'items' => [
                        ['name' => 'Consultation', 'description' => 'An unhurried conversation about what you need, what it will take, and whether we are the right studio for it.'],
                        ['name' => 'Bespoke commission', 'description' => 'A piece designed and made for you from scratch, with sign-off at each stage.'],
                        ['name' => 'Restoration', 'description' => 'Careful repair of something you already own, keeping as much of the original as we can.'],
                    ],
                ],
            ],
            [
                'type' => 'gallery',
                'title' => 'Gallery',
                'slug' => 'gallery',
                'content' => [
                    'heading' => 'Selected work',
                    // Only `heading` and `intro` are read here — the
                    // gallery section renders a fixed six-tile grid of
                    // placeholders until the owner uploads images, so an
                    // `items` array would be silently ignored.
                    'intro' => 'A small sample. Replace these with photographs of your own work — this section is what most visitors scroll to first.',
                ],
            ],
            [
                'type' => 'testimonials',
                'title' => 'Testimonials',
                'slug' => 'testimonials',
                'content' => [
                    'heading' => 'What clients say',
                    'items' => [
                        ['quote' => 'They talked me out of my first idea and were right to. What we ended up with is better than what I asked for.', 'author' => 'Client name'],
                        ['quote' => 'Slow in the best sense. Nothing was rushed and nothing needed redoing.', 'author' => 'Client name'],
                        ['quote' => 'Three years on it looks better than the day it arrived.', 'author' => 'Client name'],
                    ],
                ],
            ],
            [
                'type' => 'faq',
                'title' => 'FAQ',
                'slug' => 'faq',
                'content' => [
                    'heading' => 'Before you book',
                    'items' => [
                        ['question' => 'How long is the wait?', 'answer' => 'It depends on the piece and how full the books are. We will give you a real date at the consultation rather than an optimistic one.'],
                        ['question' => 'What does a consultation cost?', 'answer' => 'Replace this with your own answer — visitors look for it before anything else on the page.'],
                        ['question' => 'Do you work remotely?', 'answer' => 'Set out here where you work and whether you take clients outside your area.'],
                    ],
                ],
            ],
            [
                'type' => 'booking_form',
                'title' => 'Book',
                'slug' => 'booking',
                'content' => [
                    'heading' => 'Book a consultation',
                    'intro' => 'Tell us roughly what you have in mind and we will come back to you with times.',
                ],
            ],
            [
                'type' => 'contact',
                'title' => 'Contact',
                'slug' => 'contact',
                'content' => [
                    'heading' => 'Get in touch',
                    'intro' => 'The studio is open by appointment. Use the details below or send a message and we will reply within two working days.',
                ],
            ],
        ];
    }
}
