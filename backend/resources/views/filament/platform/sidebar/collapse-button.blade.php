{{--
    "← Collapse" toggle pinned to the sidebar's bottom edge, matching
    /admin's PlatformLayout.tsx button (chevron rotates 180° when
    collapsed, label hidden while collapsed). Drives Filament's own
    `$store.sidebar` state, so it stays in sync with every other
    collapse affordance in the panel and persists the same way.
--}}
<button
    type="button"
    x-data="{}"
    @click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()"
    x-bind:aria-expanded="$store.sidebar.isOpen"
    aria-controls="fi-main-sidebar"
    class="bos-sidebar-collapse"
>
    <x-filament::icon
        icon="heroicon-o-chevron-left"
        class="bos-sidebar-collapse__icon"
        x-bind:class="$store.sidebar.isOpen ? '' : 'bos-rotate-180'"
    />

    <span x-show="$store.sidebar.isOpen">Collapse</span>
</button>
