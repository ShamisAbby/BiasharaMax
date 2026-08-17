<?php

namespace Database\Seeders;

use App\Domain\Business\Models\BusinessType;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Illuminate\Database\Seeder;

/**
 * One modern, published, is_default=true template per business type, so a
 * freshly registered business has a real, fully designed public site from
 * day one — never a blank page. Pages ship with genuine demo content
 * (clearly generic placeholder copy, not fabricated facts about any real
 * business) that the owner is expected to replace from their dashboard.
 */
class WebsiteTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $slug => $definition) {
            $businessType = BusinessType::query()->where('slug', $slug)->first();

            if (! $businessType) {
                continue;
            }

            $template = WebsiteTemplate::query()->updateOrCreate(
                ['slug' => "{$slug}-modern"],
                [
                    'name' => $definition['template_name'],
                    'business_type_id' => $businessType->id,
                    'description' => $definition['description'],
                    'status' => WebsiteTemplate::STATUS_PUBLISHED,
                    'is_default' => true,
                    'theme_colors' => $definition['theme_colors'],
                    'typography' => $definition['typography'],
                    'header_config' => $definition['header_config'],
                    'footer_config' => $definition['footer_config'],
                    'navigation_config' => $definition['navigation_config'],
                    'seo_settings' => $definition['seo_settings'],
                    'social_media' => [],
                    'sort_order' => 0,
                ],
            );

            foreach ($definition['pages'] as $index => $page) {
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

            $businessType->update(['website_template_id' => $template->id]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            'retail' => [
                'template_name' => 'Retail Modern',
                'description' => 'A clean, conversion-focused storefront for retail shops.',
                'theme_colors' => $this->colors('#4F46E5', '#312E81', '#F59E0B'),
                'typography' => $this->fonts('Sora'),
                'header_config' => $this->header('Shop Now'),
                'footer_config' => $this->footer('Quality goods, honest prices.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('shop'),
                'pages' => [
                    $this->homepage(
                        'Everything you need, all in one place',
                        'Browse our latest stock and visit us in-store or order online.',
                        [
                            ['title' => 'Wide selection', 'description' => 'A curated range of products updated regularly.'],
                            ['title' => 'Fair prices', 'description' => 'Transparent pricing with no hidden costs.'],
                            ['title' => 'Friendly service', 'description' => 'Our team is here to help you find what you need.'],
                        ],
                    ),
                    $this->about('About us', 'We\'re a local retail shop committed to bringing quality products to our community at honest prices.'),
                    $this->itemsPage('products', 'Our Products', 'A look at what we stock — ask in-store for full availability.', [
                        ['name' => 'New Arrivals', 'description' => 'Freshly stocked items, updated every week.'],
                        ['name' => 'Best Sellers', 'description' => 'Customer favourites that keep selling out.'],
                        ['name' => 'On Offer', 'description' => 'Special prices on selected items.'],
                    ]),
                    $this->gallery(),
                    $this->testimonials(),
                    $this->contact(),
                ],
            ],

            'supermarket' => [
                'template_name' => 'Supermarket Modern',
                'description' => 'A bright, grocery-first layout built for supermarkets.',
                'theme_colors' => $this->colors('#16A34A', '#14532D', '#F97316'),
                'typography' => $this->fonts('Poppins'),
                'header_config' => $this->header('Find a Store'),
                'footer_config' => $this->footer('Fresh groceries, every day.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('supermarket'),
                'pages' => [
                    $this->homepage(
                        'Fresh groceries, everyday low prices',
                        'From fresh produce to household essentials, we\'ve got your shopping list covered.',
                        [
                            ['title' => 'Fresh produce', 'description' => 'Sourced daily from trusted local suppliers.'],
                            ['title' => 'Everyday essentials', 'description' => 'Household and grocery staples always in stock.'],
                            ['title' => 'Loyalty rewards', 'description' => 'Earn rewards every time you shop with us.'],
                        ],
                    ),
                    $this->about('About us', 'We\'re your neighbourhood supermarket, stocking fresh produce and everyday essentials under one roof.'),
                    $this->itemsPage('categories', 'Shop by Category', 'A quick look at our aisles.', [
                        ['name' => 'Fresh Produce', 'description' => 'Fruits and vegetables, stocked daily.'],
                        ['name' => 'Bakery & Dairy', 'description' => 'Fresh bread, milk, and dairy products.'],
                        ['name' => 'Household', 'description' => 'Cleaning and everyday home essentials.'],
                    ]),
                    $this->contact(),
                ],
            ],

            'restaurant' => [
                'template_name' => 'Restaurant Modern',
                'description' => 'A warm, appetite-driven design for restaurants and eateries.',
                'theme_colors' => $this->colors('#DC2626', '#7C2D12', '#FACC15'),
                'typography' => $this->fonts('Playfair Display'),
                'header_config' => $this->header('Reserve a Table'),
                'footer_config' => $this->footer('Good food, good company.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('restaurant'),
                'pages' => [
                    $this->homepage(
                        'A table is always waiting for you',
                        'Fresh ingredients, bold flavours, and a warm welcome every time you visit.',
                        [
                            ['title' => 'Made fresh daily', 'description' => 'Every dish is prepared fresh, never frozen.'],
                            ['title' => 'Dine-in or takeaway', 'description' => 'Enjoy our space or take your favourites to go.'],
                            ['title' => 'Loved locally', 'description' => 'A community favourite for every occasion.'],
                        ],
                    ),
                    $this->about('Our story', 'We started with a simple idea: serve honest, delicious food made with care, in a place that feels like home.'),
                    $this->itemsPage('services', 'Our Menu', 'A taste of what we serve — ask our staff for the full menu.', [
                        ['name' => 'Starters', 'description' => 'Light bites to begin your meal.'],
                        ['name' => 'Main Courses', 'description' => 'Hearty, flavour-packed dishes.'],
                        ['name' => 'Desserts & Drinks', 'description' => 'A sweet finish and refreshing drinks.'],
                    ]),
                    $this->gallery(),
                    $this->testimonials(),
                    $this->contact(),
                ],
            ],

            'pharmacy' => [
                'template_name' => 'Pharmacy Modern',
                'description' => 'A clean, trustworthy design for pharmacies and health stores.',
                'theme_colors' => $this->colors('#0EA5E9', '#075985', '#10B981'),
                'typography' => $this->fonts('Plus Jakarta Sans'),
                'header_config' => $this->header('Contact Us'),
                'footer_config' => $this->footer('Your health, our priority.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('pharmacy'),
                'pages' => [
                    $this->homepage(
                        'Trusted care for you and your family',
                        'Genuine medication, expert advice, and a team that genuinely cares about your wellbeing.',
                        [
                            ['title' => 'Licensed pharmacists', 'description' => 'Qualified staff ready to advise on your health.'],
                            ['title' => 'Genuine medication', 'description' => 'Sourced only from approved, trusted suppliers.'],
                            ['title' => 'Confidential service', 'description' => 'Your privacy and care always come first.'],
                        ],
                    ),
                    $this->about('About us', 'We\'re a community pharmacy dedicated to providing reliable medication and trustworthy health advice.'),
                    $this->itemsPage('products', 'What We Stock', 'A general look at our range — speak to our pharmacist for specific products.', [
                        ['name' => 'Prescription Medicine', 'description' => 'Dispensed by our licensed pharmacists.'],
                        ['name' => 'Over-the-Counter', 'description' => 'Everyday health and wellness products.'],
                        ['name' => 'Personal Care', 'description' => 'Health, hygiene, and baby care essentials.'],
                    ]),
                    $this->faq([
                        ['question' => 'Do you accept prescriptions from any doctor?', 'answer' => 'Yes, our pharmacists can dispense valid prescriptions from any licensed practitioner.'],
                        ['question' => 'Can I get health advice without an appointment?', 'answer' => 'Yes, our pharmacists are available for walk-in consultations during opening hours.'],
                    ]),
                    $this->contact(),
                ],
            ],

            'hardware' => [
                'template_name' => 'Hardware Modern',
                'description' => 'A rugged, practical layout for hardware and building supply stores.',
                'theme_colors' => $this->colors('#475569', '#1E293B', '#F59E0B'),
                'typography' => $this->fonts('Barlow'),
                'header_config' => $this->header('Get a Quote'),
                'footer_config' => $this->footer('Built for builders.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('hardware store'),
                'pages' => [
                    $this->homepage(
                        'Everything for your next build',
                        'Quality tools, materials, and expert advice for tradespeople and homeowners alike.',
                        [
                            ['title' => 'Trade-grade tools', 'description' => 'Durable tools and equipment for every job.'],
                            ['title' => 'Bulk supply', 'description' => 'Competitive pricing on bulk material orders.'],
                            ['title' => 'Expert advice', 'description' => 'Staff who know the trade and can help you choose right.'],
                        ],
                    ),
                    $this->about('About us', 'We supply quality tools, hardware, and building materials to tradespeople and homeowners across the area.'),
                    $this->itemsPage('categories', 'Shop by Category', 'A snapshot of what we stock.', [
                        ['name' => 'Tools & Equipment', 'description' => 'Hand tools, power tools, and accessories.'],
                        ['name' => 'Building Materials', 'description' => 'Cement, timber, and construction supplies.'],
                        ['name' => 'Plumbing & Electrical', 'description' => 'Fittings and supplies for every project.'],
                    ]),
                    $this->contact(),
                ],
            ],

            'electronics' => [
                'template_name' => 'Electronics Modern',
                'description' => 'A sleek, tech-forward layout for electronics retailers.',
                'theme_colors' => $this->colors('#6366F1', '#312E81', '#22D3EE'),
                'typography' => $this->fonts('Space Grotesk'),
                'header_config' => $this->header('Shop Now'),
                'footer_config' => $this->footer('Tech that works for you.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('electronics shop'),
                'pages' => [
                    $this->homepage(
                        'The latest tech, in your hands',
                        'Genuine devices, honest prices, and after-sales support you can rely on.',
                        [
                            ['title' => 'Genuine products', 'description' => 'Authentic devices with manufacturer warranty.'],
                            ['title' => 'After-sales support', 'description' => 'Repairs and support after your purchase.'],
                            ['title' => 'Trade-in friendly', 'description' => 'Trade in your old device towards a new one.'],
                        ],
                    ),
                    $this->about('About us', 'We bring genuine electronics and dependable after-sales support to our customers.'),
                    $this->itemsPage('products', 'Our Range', 'A look at what we sell — visit us for current stock and prices.', [
                        ['name' => 'Phones & Tablets', 'description' => 'The latest smartphones and tablets.'],
                        ['name' => 'Computing', 'description' => 'Laptops, accessories, and peripherals.'],
                        ['name' => 'Home Electronics', 'description' => 'TVs, audio, and smart home devices.'],
                    ]),
                    $this->testimonials(),
                    $this->contact(),
                ],
            ],

            'fashion' => [
                'template_name' => 'Fashion Modern',
                'description' => 'An elegant, editorial-style layout for fashion stores.',
                'theme_colors' => $this->colors('#DB2777', '#831843', '#F59E0B'),
                'typography' => $this->fonts('Cormorant Garamond'),
                'header_config' => $this->header('Shop the Collection'),
                'footer_config' => $this->footer('Style, made personal.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('fashion store'),
                'pages' => [
                    $this->homepage(
                        'Style that speaks for you',
                        'Curated collections for every occasion, fitted to your taste.',
                        [
                            ['title' => 'Curated collections', 'description' => 'New styles added every season.'],
                            ['title' => 'Quality fabrics', 'description' => 'Pieces made to last, not just to trend.'],
                            ['title' => 'Personal styling', 'description' => 'Friendly advice to help you find your fit.'],
                        ],
                    ),
                    $this->about('Our story', 'We curate fashion that blends comfort, quality, and personal style — for every body, every occasion.'),
                    $this->itemsPage('products', 'Our Collections', 'A glimpse of what\'s in store.', [
                        ['name' => 'New In', 'description' => 'The latest pieces, just arrived.'],
                        ['name' => 'Everyday Essentials', 'description' => 'Timeless pieces for everyday wear.'],
                        ['name' => 'Occasion Wear', 'description' => 'Statement pieces for special moments.'],
                    ]),
                    $this->gallery(),
                    $this->testimonials(),
                    $this->contact(),
                ],
            ],

            'beauty' => [
                'template_name' => 'Beauty Modern',
                'description' => 'A soft, elegant layout for salons and beauty businesses.',
                'theme_colors' => $this->colors('#D946EF', '#701A75', '#FDE68A'),
                'typography' => $this->fonts('Marcellus'),
                'header_config' => $this->header('Book Now'),
                'footer_config' => $this->footer('Look good, feel good.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('beauty salon'),
                'pages' => [
                    $this->homepage(
                        'Your beauty, our passion',
                        'Professional treatments in a relaxing space, tailored just for you.',
                        [
                            ['title' => 'Skilled professionals', 'description' => 'Trained stylists and therapists you can trust.'],
                            ['title' => 'Premium products', 'description' => 'Quality products for lasting results.'],
                            ['title' => 'Relaxing space', 'description' => 'A calm space designed for your comfort.'],
                        ],
                    ),
                    $this->about('About us', 'We\'re a beauty salon dedicated to helping you look and feel your best, one appointment at a time.'),
                    $this->itemsPage('services', 'Our Services', 'A look at what we offer — book an appointment for full pricing.', [
                        ['name' => 'Hair Styling', 'description' => 'Cuts, colour, and styling for every look.'],
                        ['name' => 'Skin Care', 'description' => 'Facials and treatments tailored to your skin.'],
                        ['name' => 'Nails & Beauty', 'description' => 'Manicures, pedicures, and finishing touches.'],
                    ]),
                    $this->gallery(),
                    $this->testimonials(),
                    $this->booking(),
                    $this->contact(),
                ],
            ],

            'wholesale' => [
                'template_name' => 'Wholesale Modern',
                'description' => 'A no-nonsense, B2B-focused layout for wholesale businesses.',
                'theme_colors' => $this->colors('#0F766E', '#134E4A', '#F59E0B'),
                'typography' => $this->fonts('Sora'),
                'header_config' => $this->header('Request a Quote'),
                'footer_config' => $this->footer('Bulk supply, reliable service.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('wholesale business'),
                'pages' => [
                    $this->homepage(
                        'Reliable bulk supply, every time',
                        'Competitive wholesale pricing and consistent stock for businesses that depend on us.',
                        [
                            ['title' => 'Bulk pricing', 'description' => 'Competitive rates for volume orders.'],
                            ['title' => 'Consistent supply', 'description' => 'Reliable stock so your business never stalls.'],
                            ['title' => 'Dedicated accounts', 'description' => 'Account support for repeat business customers.'],
                        ],
                    ),
                    $this->about('About us', 'We supply businesses with reliable, bulk-quantity goods at competitive wholesale rates.'),
                    $this->itemsPage('products', 'What We Supply', 'A general look at our supply range.', [
                        ['name' => 'Fast-Moving Goods', 'description' => 'High-turnover products for retail businesses.'],
                        ['name' => 'Bulk Packs', 'description' => 'Volume packaging for cost-effective ordering.'],
                        ['name' => 'Custom Orders', 'description' => 'Tailored supply arrangements for regular clients.'],
                    ]),
                    $this->faq([
                        ['question' => 'What is your minimum order quantity?', 'answer' => 'Minimum order quantities vary by product — contact us for details on the items you need.'],
                        ['question' => 'Do you offer credit terms for regular customers?', 'answer' => 'Yes, credit terms are available for established business accounts — get in touch to discuss.'],
                    ]),
                    $this->contact(),
                ],
            ],

            'service' => [
                'template_name' => 'Service Business Modern',
                'description' => 'A professional, trust-building layout for service businesses.',
                'theme_colors' => $this->colors('#2563EB', '#1E3A8A', '#F59E0B'),
                'typography' => $this->fonts('Manrope'),
                'header_config' => $this->header('Get a Quote'),
                'footer_config' => $this->footer('Service you can trust.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('service business'),
                'pages' => [
                    $this->homepage(
                        'Professional service, done right',
                        'Reliable, skilled, and ready when you need us.',
                        [
                            ['title' => 'Skilled professionals', 'description' => 'Experienced team for every job, big or small.'],
                            ['title' => 'Transparent pricing', 'description' => 'Clear quotes with no surprises.'],
                            ['title' => 'On-time delivery', 'description' => 'We respect your time and deliver as promised.'],
                        ],
                    ),
                    $this->about('About us', 'We\'re a service business built on reliability, fair pricing, and getting the job done right the first time.'),
                    $this->itemsPage('services', 'Our Services', 'A look at what we offer — contact us for a tailored quote.', [
                        ['name' => 'Consultations', 'description' => 'Expert advice to plan your next step.'],
                        ['name' => 'On-site Service', 'description' => 'We come to you for hands-on work.'],
                        ['name' => 'Ongoing Support', 'description' => 'Continued support after the job is done.'],
                    ]),
                    $this->testimonials(),
                    $this->contact(),
                ],
            ],

            'other' => [
                'template_name' => 'Business Modern',
                'description' => 'A flexible, modern layout that fits almost any business.',
                'theme_colors' => $this->colors('#4338CA', '#312E81', '#F59E0B'),
                'typography' => $this->fonts('Inter'),
                'header_config' => $this->header('Contact Us'),
                'footer_config' => $this->footer('Proudly serving our community.'),
                'navigation_config' => $this->nav(),
                'seo_settings' => $this->seo('business'),
                'pages' => [
                    $this->homepage(
                        'Welcome to our business',
                        'We\'re glad you\'re here — here\'s a little about what we do and how to reach us.',
                        [
                            ['title' => 'Quality first', 'description' => 'We take pride in everything we deliver.'],
                            ['title' => 'Customer focused', 'description' => 'Your satisfaction drives how we work.'],
                            ['title' => 'Always improving', 'description' => 'We\'re constantly refining how we serve you.'],
                        ],
                    ),
                    $this->about('About us', 'We\'re a growing business focused on delivering real value to our customers, every single day.'),
                    $this->itemsPage('services', 'What We Offer', 'A general overview — contact us to learn more.', [
                        ['name' => 'Our Offering', 'description' => 'Tell your customers what makes this special.'],
                        ['name' => 'Why Choose Us', 'description' => 'Highlight what sets your business apart.'],
                        ['name' => 'Get Started', 'description' => 'Explain how customers can begin working with you.'],
                    ]),
                    $this->contact(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function colors(string $primary, string $secondary, string $accent): array
    {
        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'accent' => $accent,
            'background' => '#FFFFFF',
            'surface' => '#F8FAFC',
            'text' => '#0F172A',
            'muted' => '#64748B',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fonts(string $heading): array
    {
        return [
            'heading_font' => $heading,
            'body_font' => 'Inter',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function header(string $ctaLabel): array
    {
        return [
            'style' => 'split',
            'show_cta' => true,
            'cta_label' => $ctaLabel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function footer(string $tagline): array
    {
        return [
            'tagline' => $tagline,
            'show_social' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nav(): array
    {
        return [
            'cta_label' => 'Contact us',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function seo(string $kind): array
    {
        return [
            'title_suffix' => '| '.ucfirst($kind),
            'meta_description' => "A modern {$kind}, proudly serving our community.",
        ];
    }

    /**
     * @param  array<int, array{title: string, description: string}>  $features
     * @return array{type: string, title: string, slug: string, content: array<string, mixed>}
     */
    private function homepage(string $headline, string $subheadline, array $features): array
    {
        return [
            'type' => 'homepage',
            'title' => 'Home',
            'slug' => 'home',
            'content' => [
                'hero' => [
                    'eyebrow' => 'Welcome',
                    'headline' => $headline,
                    'subheadline' => $subheadline,
                    'primary_cta' => 'Get in touch',
                    'secondary_cta' => 'Learn more',
                ],
                'features' => $features,
            ],
        ];
    }

    /**
     * @return array{type: string, title: string, slug: string, content: array<string, mixed>}
     */
    private function about(string $heading, string $body): array
    {
        return [
            'type' => 'about',
            'title' => 'About',
            'slug' => 'about',
            'content' => [
                'heading' => $heading,
                'body' => $body,
            ],
        ];
    }

    /**
     * @param  array<int, array{name: string, description: string}>  $items
     * @return array{type: string, title: string, slug: string, content: array<string, mixed>}
     */
    private function itemsPage(string $type, string $title, string $intro, array $items): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'slug' => $type,
            'content' => [
                'heading' => $title,
                'intro' => $intro,
                'items' => $items,
            ],
        ];
    }

    /**
     * @return array{type: string, title: string, slug: string, content: array<string, mixed>}
     */
    private function gallery(): array
    {
        return [
            'type' => 'gallery',
            'title' => 'Gallery',
            'slug' => 'gallery',
            'content' => [
                'heading' => 'Gallery',
                'intro' => 'A look at our space and our work — photos coming soon.',
            ],
        ];
    }

    /**
     * @return array{type: string, title: string, slug: string, content: array<string, mixed>}
     */
    private function testimonials(): array
    {
        return [
            'type' => 'testimonials',
            'title' => 'Testimonials',
            'slug' => 'testimonials',
            'content' => [
                'heading' => 'What our customers say',
                'items' => [
                    ['quote' => 'Great service and even better products. I keep coming back.', 'author' => 'Verified Customer'],
                    ['quote' => 'Friendly staff and exactly what I needed, every time.', 'author' => 'Loyal Customer'],
                    ['quote' => 'Highly recommend — reliable and easy to deal with.', 'author' => 'Happy Customer'],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{question: string, answer: string}>  $items
     * @return array{type: string, title: string, slug: string, content: array<string, mixed>}
     */
    private function faq(array $items): array
    {
        return [
            'type' => 'faq',
            'title' => 'FAQ',
            'slug' => 'faq',
            'content' => [
                'heading' => 'Frequently asked questions',
                'items' => $items,
            ],
        ];
    }

    /**
     * @return array{type: string, title: string, slug: string, content: array<string, mixed>}
     */
    private function booking(): array
    {
        return [
            'type' => 'booking_form',
            'title' => 'Book Now',
            'slug' => 'book',
            'content' => [
                'heading' => 'Book your appointment',
                'intro' => 'Get in touch to find a time that works for you.',
            ],
        ];
    }

    /**
     * @return array{type: string, title: string, slug: string, content: array<string, mixed>}
     */
    private function contact(): array
    {
        return [
            'type' => 'contact',
            'title' => 'Contact',
            'slug' => 'contact',
            'content' => [
                'heading' => 'Get in touch',
                'intro' => 'We\'d love to hear from you — reach out using the details below.',
            ],
        ];
    }
}
