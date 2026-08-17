export type BusinessWebsiteStatus = 'draft' | 'published';

export type WebsitePageType =
    | 'homepage'
    | 'about'
    | 'products'
    | 'categories'
    | 'services'
    | 'gallery'
    | 'testimonials'
    | 'blog'
    | 'contact'
    | 'faq'
    | 'privacy_policy'
    | 'terms'
    | 'booking_form';

export interface HomepageContent {
    hero?: {
        eyebrow?: string;
        headline?: string;
        subheadline?: string;
        primary_cta?: string;
        secondary_cta?: string;
    };
    features?: { title: string; description: string }[];
}

export interface HeadingBodyContent {
    heading?: string;
    body?: string;
}

export interface HeadingIntroItemsContent {
    heading?: string;
    intro?: string;
    items?: { name: string; description: string }[];
}

export interface HeadingIntroContent {
    heading?: string;
    intro?: string;
}

export interface TestimonialsContent {
    heading?: string;
    items?: { quote: string; author: string }[];
}

export interface FaqContent {
    heading?: string;
    items?: { question: string; answer: string }[];
}

export interface BusinessWebsitePage {
    id: string;
    type: WebsitePageType;
    title: string;
    slug: string;
    content: Record<string, unknown> | null;
    seo_title: string | null;
    seo_description: string | null;
    is_enabled: boolean;
    sort_order: number;
}

export interface BusinessWebsite {
    id: string;
    status: BusinessWebsiteStatus;
    seo_title: string | null;
    seo_description: string | null;
    published_at: string | null;
    template_name: string | null;
    pages: BusinessWebsitePage[];
}
