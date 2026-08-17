<x-filament-panels::page>
    @unless ($this->canManageSettings())
        <x-filament::section>
            <div class="bos-banner">
                <x-filament::icon icon="heroicon-o-lock-closed" />
                You have read-only access to platform settings. Saving requires the
                <strong>platform_settings.manage</strong> permission.
            </div>
        </x-filament::section>
    @endunless

    <x-filament::section>
        <div class="bos-banner bos-banner--warning">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" />
            The <strong>Application</strong>, <strong>Mail Server</strong> and <strong>Database</strong>
            tabs write directly to the server's <code>.env</code> file. Changing database credentials
            disconnects the application from the current database — apply only if the new connection
            details are known to be correct.
        </div>
    </x-filament::section>

    <form wire:submit="save">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
