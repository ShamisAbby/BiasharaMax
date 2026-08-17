{{--
    Date + status badge only — positioned via GLOBAL_SEARCH_AFTER,
    right after the search field and before the native notifications
    bell. See PlatformPanelProvider's render hook comments for the
    settings/currency/language/theme group, which sits in a separate
    hook (right-extras.blade.php via USER_MENU_BEFORE) since the native
    bell can't be skipped over from this hook alone.
--}}
<div class="bos-topbar-extras bos-topbar-extras--left">
    <span class="bos-topbar-date">{{ $today->format('D, j M Y') }}</span>

    <span class="bos-topbar-status bos-topbar-status--{{ $statusColor }}" title="{{ $statusTitle }}">
        <span class="bos-topbar-status__dot"></span>
        {{ $statusLabel }}
    </span>
</div>
