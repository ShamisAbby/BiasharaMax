{{--
    Settings dropdown + currency + language, positioned right before the
    user-menu avatar (USER_MENU_BEFORE) — i.e. right *after* the native
    notifications bell, which sits between this hook and
    GLOBAL_SEARCH_AFTER in Filament's own topbar.blade.php and can't be
    skipped over any other way. Left-to-right order across both hooks:
    search → date → status → bell → gear → currency → language → avatar.

    Theme switching is deliberately not here — Filament already provides
    it inside the user-menu dropdown, which is where this panel keeps
    it.

    Currency/language selection is a client-only display preference
    (localStorage), same scope as /admin's own switchers — see
    PlatformPanelProvider's render hook docblock for the honest scope
    note (currency list is real/DB-backed via the Currency model but
    isn't threaded through table amount columns yet; language covers
    only these two options, matching /admin's own ~39-string chrome-only
    i18n rather than real backend translation).
--}}
<div class="bos-topbar-extras">
    {{-- Settings --}}
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

    {{-- Currency (client-only display preference, same scope as /admin's own switcher) --}}
    <div
        x-data="{
            open: false,
            selected: localStorage.getItem('bos-currency') || '{{ $currencies->firstWhere('is_base', true)?->code ?? $currencies->first()?->code }}',
            select(code) { this.selected = code; localStorage.setItem('bos-currency', code); this.open = false; },
        }"
        class="bos-topbar-menu"
        @click.outside="open = false"
    >
        <button type="button" @click="open = !open" class="bos-topbar-icon-btn" aria-label="Currency">
            <x-filament::icon icon="heroicon-o-currency-dollar" />
        </button>

        <div x-show="open" x-transition x-cloak class="bos-topbar-menu__panel">
            @forelse ($currencies as $currency)
                <button
                    type="button"
                    @click="select('{{ $currency->code }}')"
                    class="bos-topbar-menu__item bos-topbar-menu__item--option"
                >
                    <span>{{ $currency->code }} — {{ $currency->name }} ({{ $currency->symbol }})</span>
                    <x-filament::icon
                        icon="heroicon-o-check"
                        class="bos-topbar-menu__check"
                        x-show="selected === '{{ $currency->code }}'"
                    />
                </button>
            @empty
                <span class="bos-topbar-menu__item bos-topbar-menu__item--empty">No active currencies</span>
            @endforelse
        </div>
    </div>

    {{-- Language (chrome-only, two options — matches /admin's own scope) --}}
    <div
        x-data="{
            open: false,
            selected: localStorage.getItem('bos-language') || 'en',
            select(code) { this.selected = code; localStorage.setItem('bos-language', code); this.open = false; },
        }"
        class="bos-topbar-menu"
        @click.outside="open = false"
    >
        <button type="button" @click="open = !open" class="bos-topbar-icon-btn" aria-label="Language">
            <x-filament::icon icon="heroicon-o-language" />
        </button>

        <div x-show="open" x-transition x-cloak class="bos-topbar-menu__panel">
            @foreach ($languages as $language)
                <button
                    type="button"
                    @click="select('{{ $language['code'] }}')"
                    class="bos-topbar-menu__item bos-topbar-menu__item--option"
                >
                    <span>{{ $language['label'] }}</span>
                    <x-filament::icon
                        icon="heroicon-o-check"
                        class="bos-topbar-menu__check"
                        x-show="selected === '{{ $language['code'] }}'"
                    />
                </button>
            @endforeach
        </div>
    </div>

    {{-- No theme switcher here: Filament renders its own inside the
         user-menu dropdown, and having one in both places put the same
         three light/dark/system buttons on screen twice, as two Alpine
         instances writing the same `theme` localStorage key. The
         dropdown one is the survivor. --}}
</div>

{{--
    /admin's topbar shows the signed-in user's name and a chevron next to
    the avatar; Filament's topbar user menu renders the avatar alone (the
    name+chevron markup exists, but only on its sidebar-position variant).
    Rather than publishing and pinning an override of Filament's whole
    user-menu component view to this exact version, this renders the name
    beside it and forwards clicks to the real trigger, so the entire
    avatar+name+chevron cluster opens the same menu. Deliberately a
    sibling of (not nested in) the icon row above so the CSS `order`
    rules in dashboard.css can place it after the avatar.

    Note it dispatches `mousedown`, not `.click()`: Filament's dropdown
    binds its toggle to mousedown/keyup.enter/keyup.space, never to
    `click`, so a plain `.click()` would be silently inert.
--}}
<button
    type="button"
    x-data="{}"
    @click="
        document
            .querySelector('.fi-topbar-end .fi-user-menu .fi-dropdown-trigger')
            ?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, button: 0 }))
    "
    aria-haspopup="menu"
    class="bos-topbar-user-name"
>
    <span>{{ filament()->getUserName(filament()->auth()->user()) }}</span>
    <x-filament::icon icon="heroicon-m-chevron-down" class="bos-topbar-user-name__chevron" />
</button>
