<style>
    .fi-global-search {
        display: none !important;
    }

    /* ── Sidebar: a crisp monochrome surface (white on the light-gray page, dark gray on the near-black page) ── */
    :root {
        --bg-rail: white;
        --bg-rail-edge: var(--gray-200);
        --bg-rail-text: var(--gray-700);
        --bg-rail-icon: var(--gray-500);
        --bg-rail-muted: var(--gray-500);
        --bg-rail-hover: var(--gray-100);
        --bg-rail-hover-text: var(--gray-950);
        --bg-rail-active: var(--gray-950);
        --bg-rail-active-text: white;
        --bg-ease-drawer: cubic-bezier(0.32, 0.72, 0, 1);
    }

    .dark {
        --bg-rail: var(--gray-900);
        --bg-rail-edge: rgb(255 255 255 / 0.08);
        --bg-rail-text: var(--gray-300);
        --bg-rail-icon: var(--gray-400);
        --bg-rail-muted: var(--gray-500);
        --bg-rail-hover: rgb(255 255 255 / 0.06);
        --bg-rail-hover-text: white;
        --bg-rail-active: white;
        --bg-rail-active-text: var(--gray-950);
    }

    .fi-sidebar {
        background: var(--bg-rail) !important;
        box-shadow: inset -1px 0 0 var(--bg-rail-edge);
    }

    /* Mobile drawer header (logo + close) sits on the same surface */
    .fi-sidebar .fi-sidebar-header {
        background: transparent !important;
        box-shadow: none !important;
    }

    .fi-sidebar .fi-sidebar-item-btn {
        transition: background-color 160ms ease, color 160ms ease;
    }

    .fi-sidebar .fi-sidebar-item-btn > .fi-sidebar-item-label {
        color: var(--bg-rail-text);
        font-weight: 500;
        white-space: nowrap;
    }

    .fi-sidebar .fi-sidebar-item-btn > .fi-icon {
        color: var(--bg-rail-icon);
    }

    @media (hover: hover) and (pointer: fine) {
        .fi-sidebar .fi-sidebar-item:not(.fi-active) > .fi-sidebar-item-btn:hover {
            background-color: var(--bg-rail-hover);
        }

        .fi-sidebar .fi-sidebar-item:not(.fi-active) > .fi-sidebar-item-btn:hover > .fi-sidebar-item-label,
        .fi-sidebar .fi-sidebar-item:not(.fi-active) > .fi-sidebar-item-btn:hover > .fi-icon {
            color: var(--bg-rail-hover-text);
        }
    }

    /* Current page: a solid black pill (white in dark mode) */
    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--bg-rail-active);
    }

    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label,
    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon {
        color: var(--bg-rail-active-text);
    }

    /* Dark mode's active pill is white, so its badge (e.g. the overdue count) uses the light-mode colors to stay readable */
    .dark .fi-sidebar .fi-sidebar-item.fi-active .fi-badge.fi-color {
        background-color: var(--color-50);
        color: var(--text);
        --tw-ring-color: var(--color-600);
    }

    .fi-sidebar .fi-sidebar-item-btn:focus-visible {
        outline: 2px solid var(--gray-950);
        outline-offset: 2px;
    }

    .dark .fi-sidebar .fi-sidebar-item-btn:focus-visible {
        outline-color: white;
    }

    /* Group headings ("User Management") */
    .fi-sidebar .fi-sidebar-group-label,
    .fi-sidebar .fi-sidebar-group-btn .fi-icon,
    .fi-sidebar .fi-sidebar-group-collapse-btn {
        color: var(--bg-rail-muted) !important;
    }

    .fi-sidebar .fi-sidebar-group-label {
        font-size: 0.75rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    /* Labels fade in just after the rail starts opening, so they don't squeeze mid-animation */
    .fi-sidebar .fi-transition-enter {
        transition: opacity 180ms ease 120ms;
    }

    .fi-sidebar .fi-transition-enter-start {
        opacity: 0;
    }

    .fi-sidebar .fi-transition-enter-end {
        opacity: 1;
    }

    @media (min-width: 64rem) {
        /* Collapse/expand: the rail and the brand block resize together */
        .fi-body.fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar {
            transition-property: width;
            transition-duration: 320ms;
            transition-timing-function: var(--bg-ease-drawer);
            overflow-x: hidden;
        }

        /* Filament leaves the collapsed rail at its content width, which can't be animated; pin it to the panel's collapsed width */
        .fi-body.fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) {
            width: var(--collapsed-sidebar-width);
        }

        .fi-body.fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar .fi-sidebar-nav {
            transition: padding 320ms var(--bg-ease-drawer);
        }

        .fi-body.fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav {
            padding-inline: 1rem;
        }

        /* Brand block: the top of the rail, with the burger at its top-right */
        .fi-topbar {
            padding-inline-start: 0;
        }

        .fi-topbar-start {
            align-self: stretch;
            box-sizing: border-box;
            width: var(--sidebar-width);
            flex-shrink: 0;
            margin-inline-end: 1.5rem;
            padding-inline: 1.5rem 0.75rem;
            background: var(--bg-rail);
            box-shadow: inset -1px 0 0 var(--bg-rail-edge);
            overflow: hidden;
            transition: width 320ms var(--bg-ease-drawer), padding 320ms var(--bg-ease-drawer);
        }

        .fi-topbar-start > a,
        .fi-topbar-start > .fi-logo {
            max-width: 14rem;
            overflow: hidden;
            opacity: 1;
            visibility: visible;
            transition: opacity 200ms ease 100ms, max-width 320ms var(--bg-ease-drawer), visibility 0s;
        }

        .fi-topbar-start .fi-logo {
            margin-inline-start: 0;
            color: var(--gray-950);
            white-space: nowrap;
            letter-spacing: -0.01em;
        }

        .dark .fi-topbar-start .fi-logo {
            color: white;
        }

        .fi-topbar-collapse-sidebar-btn-ctn {
            order: 2;
            display: flex;
            justify-content: flex-end;
            margin-inline-start: auto;
            /* Always above the logo, whatever it's doing */
            position: relative;
            z-index: 1;
        }

        .fi-topbar-collapse-sidebar-btn-ctn .fi-icon-btn {
            color: var(--gray-500);
            border-radius: 0.5rem;
            transition: background-color 160ms ease, color 160ms ease, transform 120ms ease-out;
        }

        .fi-topbar-collapse-sidebar-btn-ctn .fi-icon-btn:hover {
            color: var(--bg-rail-hover-text);
            background-color: var(--bg-rail-hover);
        }

        .fi-topbar-collapse-sidebar-btn-ctn .fi-icon-btn:active {
            transform: scale(0.94);
        }

        /* Collapsed: the block narrows to the icon rail, the burger centres.
           The logo is clipped, hidden and click-through so it can never cover the burger (that's what made it stick shut). */
        .fi-body:has(.fi-sidebar:not(.fi-sidebar-open)) .fi-topbar-start {
            width: var(--collapsed-sidebar-width);
            padding-inline: 0;
        }

        .fi-body:has(.fi-sidebar:not(.fi-sidebar-open)) .fi-topbar-start > a,
        .fi-body:has(.fi-sidebar:not(.fi-sidebar-open)) .fi-topbar-start > .fi-logo {
            max-width: 0;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 120ms ease, max-width 320ms var(--bg-ease-drawer), visibility 0s linear 120ms;
        }

        .fi-body:has(.fi-sidebar:not(.fi-sidebar-open)) .fi-topbar-collapse-sidebar-btn-ctn {
            justify-content: center;
            margin-inline: auto;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-sidebar,
        .fi-topbar-start,
        .fi-topbar-start > a,
        .fi-sidebar .fi-transition-enter {
            transition: none !important;
        }
    }

    /* Fine receipt in the "Verify Payment & Return" modal */
    .bg-receipt {
        display: grid;
        gap: 0.875rem;
    }

    .bg-receipt-facts {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin: 0;
    }

    .bg-receipt-facts dt {
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: var(--gray-500);
    }

    .bg-receipt-facts dd {
        margin: 0.125rem 0 0;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .bg-receipt-facts .bg-receipt-amount {
        font-size: 1.25rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .bg-receipt-image {
        display: block;
        max-height: 26rem;
        overflow: auto;
        border-radius: 0.75rem;
        background: var(--gray-100);
        box-shadow: inset 0 0 0 1px var(--gray-200);
    }

    .dark .bg-receipt-image {
        background: rgb(255 255 255 / 0.04);
        box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.08);
    }

    .bg-receipt-image img {
        display: block;
        width: 100%;
        height: auto;
    }

    .bg-receipt-hint {
        margin: 0;
        font-size: 0.8125rem;
        color: var(--gray-500);
    }

    .bg-receipt-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .bg-receipt-row > .fi-btn {
        flex-shrink: 0;
        white-space: nowrap;
    }

    /* Activity log: before/after details */
    .bg-activity {
        display: grid;
        gap: 0.75rem;
    }

    .bg-activity-meta {
        margin: 0;
        font-size: 0.8125rem;
        color: var(--gray-500);
    }

    .bg-activity-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .bg-activity-table th {
        padding: 0.5rem 0.625rem;
        text-align: start;
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: var(--gray-500);
        border-bottom: 1px solid var(--gray-200);
    }

    .bg-activity-table td {
        padding: 0.5rem 0.625rem;
        vertical-align: top;
        border-bottom: 1px solid var(--gray-100);
        overflow-wrap: anywhere;
    }

    .bg-activity-table .bg-activity-field {
        font-weight: 500;
        white-space: nowrap;
    }

    .bg-activity-table .bg-activity-old {
        color: var(--gray-500);
        text-decoration: line-through;
        text-decoration-color: var(--gray-300);
    }

    .dark .bg-activity-table th {
        border-bottom-color: rgb(255 255 255 / 0.1);
    }

    .dark .bg-activity-table td {
        border-bottom-color: rgb(255 255 255 / 0.05);
    }

    /* Overdue loans in the Transactions table */
    .fi-ta-row.bg-transaction-overdue {
        background-color: rgb(254 242 242);
    }

    .dark .fi-ta-row.bg-transaction-overdue {
        background-color: rgb(239 68 68 / 0.08);
    }

    .fi-ta-row.bg-transaction-overdue > td:first-child {
        box-shadow: inset 3px 0 0 rgb(239 68 68);
    }

    /* Requests waiting for a librarian */
    .fi-ta-row.bg-transaction-pending > td:first-child {
        box-shadow: inset 3px 0 0 rgb(245 158 11);
    }

    /* Dashboard welcome card */
    .bg-welcome {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .bg-welcome-who {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }

    .bg-welcome-text {
        display: grid;
        gap: 0.125rem;
        min-width: 0;
    }

    .bg-welcome-greeting {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        line-height: 1.3;
        letter-spacing: -0.01em;
    }

    .bg-welcome-sub {
        margin: 0;
        font-size: 0.875rem;
        color: var(--gray-500);
    }

    .dark .bg-welcome-sub {
        color: var(--gray-400);
    }

    .bg-welcome-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
</style>
