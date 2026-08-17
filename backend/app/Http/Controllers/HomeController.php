<?php

namespace App\Http\Controllers;

use App\Domain\Business\Models\BusinessType;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Display the public platform landing page.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
            'desktopApp' => $this->desktopApp(),
            'contact' => $this->contact(),
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            // Read from the admin-managed table rather than a hardcoded
            // list in the page, so the trades advertised here can't drift
            // from the ones a visitor is actually offered at signup.
            // `other` is excluded — it's the catch-all fallback on the
            // registration form, not a trade worth advertising.
            'businessTypes' => BusinessType::query()
                ->where('status', BusinessType::STATUS_ACTIVE)
                ->where('slug', '!=', 'other')
                ->orderBy('sort_order')
                ->get(['name', 'slug', 'icon', 'color'])
                // Type names are stored singular ("Pharmacy") because
                // that is how they read when assigned to one business.
                // The landing page lists trades, which reads better
                // plural — Str::plural handles the irregular ones
                // ("Pharmacy" → "Pharmacies") that appending an "s"
                // would get wrong.
                ->map(fn (BusinessType $type): array => [
                    'name' => Str::plural($type->name),
                    'slug' => $type->slug,
                    'icon' => $type->icon,
                    'color' => $type->color,
                ]),
        ]);
    }

    /**
     * Public contact details, shaped for the landing page.
     *
     * Empty values are normalised to null rather than passed through as
     * empty strings, so the page can test one thing — "is this present?" —
     * instead of every consumer having to remember that `''` is falsy but
     * still renders as a blank row with an icon beside it.
     *
     * The WhatsApp link is assembled here too. Building a wa.me URL in the
     * component would put the same string-munging in front of every place
     * that needs it, and the number's formatting rules (digits only, no
     * plus, no spaces) are not obvious enough to repeat.
     *
     * @return array<string, mixed>
     */
    private function contact(): array
    {
        $whatsapp = preg_replace('/\D/', '', (string) config('contact.whatsapp'));

        return [
            'whatsapp' => $whatsapp ?: null,
            'whatsappUrl' => $whatsapp
                ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode((string) config('contact.whatsapp_message'))
                : null,
            'email' => config('contact.email') ?: null,
            'phone' => config('contact.phone') ?: null,
            'address' => config('contact.address') ?: null,
            'hours' => config('contact.hours') ?: null,
        ];
    }

    /**
     * Desktop till downloads, shaped for the landing page.
     *
     * Read from config/desktop_app.php so a new build is published by
     * changing env, not by editing a React component.
     *
     * A platform with no URL is deliberately still returned rather than
     * filtered out. Three cards where one says "Coming soon" tells a
     * visitor on that OS that support is planned; silently rendering two
     * cards tells them their OS isn't supported at all, which is a
     * different and wrong message. The page renders those as disabled, so
     * nothing links to a 404 either way.
     *
     * @return array<string, mixed>
     */
    private function desktopApp(): array
    {
        $platforms = collect(config('desktop_app.platforms', []))
            ->map(fn (array $platform): array => [
                'key' => $platform['key'],
                'name' => $platform['name'],
                'requirement' => $platform['requirement'],
                'format' => $platform['format'],
                'url' => $platform['url'] ?: null,
                'size' => $platform['size'] ?: null,
                'checksum' => $platform['checksum'] ?: null,
            ])
            ->values()
            ->all();

        return [
            'version' => config('desktop_app.version') ?: null,
            'releasedAt' => config('desktop_app.released_at') ?: null,
            'releaseNotesUrl' => config('desktop_app.release_notes_url') ?: null,
            'platforms' => $platforms,
            // Whether *any* build exists yet. The section always renders —
            // this only changes what it says. Before the first release it
            // reads as an honest "coming soon" rather than three dead
            // buttons; afterwards it stops apologising.
            'isAvailable' => collect($platforms)->contains(fn (array $p): bool => $p['url'] !== null),
        ];
    }
}
