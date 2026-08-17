{{--
    "Search navigation…" filter, matching /admin's BiSidebar search box.
    Like BiSidebar's own, this filters the already-rendered nav items
    client-side (it is NOT the panel's global search — that lives in the
    topbar with its own ⌘K binding). Groups with no surviving items are
    hidden too, same as BiSidebar's `filteredGroups` behavior.

    Hidden while the sidebar is collapsed, mirroring BiSidebar's
    `{!collapsed && ...}` guard around its own search box. Collapsing
    also clears the term first, so an active filter can't strand nav
    items with an inline `display: none` that nothing left on screen can
    undo.
--}}
<div
    class="bos-nav-search"
    x-show="$store.sidebar.isOpen"
    x-effect="if (! $store.sidebar.isOpen && term !== '') { term = ''; filter(); }"
    x-data="{
        term: '',
        filter() {
            const query = this.term.trim().toLowerCase();

            this.$root.parentElement.querySelectorAll('.fi-sidebar-group').forEach((group) => {
                let visible = 0;

                group.querySelectorAll('.fi-sidebar-item').forEach((item) => {
                    const matches = query === '' || item.textContent.toLowerCase().includes(query);
                    item.style.display = matches ? '' : 'none';

                    if (matches) {
                        visible++;
                    }
                });

                group.style.display = visible > 0 ? '' : 'none';
            });
        },
    }"
>
    <x-filament::icon icon="heroicon-o-magnifying-glass" class="bos-nav-search__icon" />

    <input
        type="search"
        x-model="term"
        @input="filter()"
        placeholder="Search navigation…"
        aria-label="Search navigation"
        class="bos-nav-search__input"
    />
</div>
