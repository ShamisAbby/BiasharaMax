{{--
    Matches the old /admin dashboard's topbar chrome (date display +
    "Operational" status badge + settings dropdown), injected via the
    GLOBAL_SEARCH_AFTER render hook so it sits in the topbar's
    right-hand row, before notifications/theme-toggle/user-menu — same
    left-to-right position as PlatformLayout.tsx's equivalent elements.

    Two differences from /admin, both deliberate:
    - The status badge here is wired to real (60s-cached) system health
      data (PlatformAnalyticsService::overview() + PlatformPulseService
      health score) rather than /admin's hardcoded-always-"Operational"
      span — this panel doesn't fake a status it hasn't checked.
    - /admin's currency and language dropdowns are intentionally NOT
      reproduced: the currency switcher is a client-only display
      preference that isn't wired into any table in this rebuild yet,
      and the language switcher only covers ~39 nav strings (not real
      backend i18n) even in the original — adding either here would be
      a decorative control that doesn't actually do anything useful in
      this panel, which this project has avoided everywhere else.
--}}
<div class="bos-topbar-extras">
    <span class="bos-topbar-date">{{ $today->format('D, j M Y') }}</span>

    <span class="bos-topbar-status bos-topbar-status--{{ $statusColor }}" title="{{ $statusTitle }}">
        <span class="bos-topbar-status__dot"></span>
        {{ $statusLabel }}
    </span>

    <div x-data="{ open: false }" class="bos-topbar-menu" @click.outside="open = false">
        <button type="button" @click="open = !open" class="bos-topbar-icon-btn" aria-label="Settings">
            <x-filament::icon icon="heroicon-o-cog-6-tooth" />
        </button>

        <div x-show="open" x-transition x-cloak class="bos-topbar-menu__panel">
            @foreach ($settingsLinks as $link)
                <a href="{{ $link['url'] }}" class="bos-topbar-menu__item">
                    <x-filament::icon :icon="$link['icon']" />
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</div>
