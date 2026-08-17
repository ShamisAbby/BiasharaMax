<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public contact details
    |--------------------------------------------------------------------------
    |
    | Shown on the landing page and used by the floating WhatsApp button.
    |
    | Config rather than hardcoded in the React page for the obvious reason
    | — a number changes and someone has to rebuild the front end to fix it
    | — and for a less obvious one: every value here is a promise. Anything
    | left empty is simply not rendered, so an address or opening hours that
    | are not true yet stay off the page instead of being invented to fill a
    | layout. A support channel advertised and not answered is worse than
    | one never advertised.
    |
    */

    /**
     * International format, digits only — this is what wa.me expects, and
     * it rejects spaces, plus signs and brackets.
     */
    'whatsapp' => env('CONTACT_WHATSAPP', '255772800257'),

    /**
     * The message the chat opens with.
     *
     * Prefilled so the first thing you receive says where the person came
     * from. "Hi" from an unknown number tells you nothing.
     */
    'whatsapp_message' => env(
        'CONTACT_WHATSAPP_MESSAGE',
        'Hello BiasharaMax, I would like to know more about the system.',
    ),

    'email' => env('CONTACT_EMAIL', 'info@biasharamax.com'),

    /** Human-readable. Unlike the WhatsApp value, this one is displayed. */
    'phone' => env('CONTACT_PHONE', '+255 772 800 257'),

    /**
     * Empty by default, and deliberately so.
     *
     * A physical address is a real-world fact nobody can guess. Set
     * CONTACT_ADDRESS when there is one to give; until then the page shows
     * three contact methods rather than four, which is accurate.
     */
    'address' => env('CONTACT_ADDRESS', ''),

    /**
     * Also empty by default. Publishing hours creates an expectation that
     * messages sent inside them get answered.
     */
    'hours' => env('CONTACT_HOURS', ''),

];
