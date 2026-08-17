@php
    use App\Domain\Platform\Support\AdminSurface;

    $missing = AdminSurface::missingFrom(AdminSurface::INERTIA);
@endphp

{{--
    Switches to the Inertia admin at /admin.

    A plain form post rather than a link, because it writes a preference
    before redirecting — a GET that changes state would be re-fired by
    every prefetch and back button.

    The confirm names what is only on this side rather than asking a
    generic "are you sure". Nothing here is destructive; the risk is
    purely that an admin does not realise the two surfaces differ, and a
    warning that does not say what changes prevents nothing.
--}}
<form
    method="POST"
    action="{{ route('platform.preferences.admin-surface') }}"
    class="bos-topbar-extras"
    @if ($missing !== [])
        onsubmit="return confirm('Switch to the Classic admin?\n\nThese screens are only available there:\n\n@foreach ($missing as $screen)&bull; {{ $screen }}\n@endforeach\nYou can switch back at any time.');"
    @endif
>
    @csrf
    <input type="hidden" name="surface" value="{{ AdminSurface::INERTIA }}">

    <button
        type="submit"
        class="bos-topbar-icon-btn"
        title="Switch to the Classic admin"
        aria-label="Switch to the Classic admin"
    >
        <x-filament::icon icon="heroicon-o-arrows-right-left" />
    </button>
</form>
