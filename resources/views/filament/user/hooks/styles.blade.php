<style>
    /* Top navigation */
    .fi-topbar {
        display: flex;
        align-items: center;
    }

    .fi-topbar-start {
        flex: 1;
    }

    .fi-topbar nav > ul {
        margin-left: auto !important;
        padding-right: 1rem !important;
    }

    .fi-topbar-nav-groups {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: 0;
    }

    .fi-topbar-end {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-left: 0;
    }

    .fi-topbar-item-btn {
        display: flex;
        flex-direction: row;
        align-items: center;
        border-radius: 6px;
        padding: 6px 12px;
        color: #6b7280;
        transition: background-color 0.2s, color 0.2s;
    }

    .fi-topbar-item-icon {
        order: 2;
        margin-left: 6px;
        margin-right: 0 !important;
    }

    .fi-topbar-item-label {
        order: 1;
    }

    .fi-topbar-item.fi-active .fi-topbar-item-btn {
        background-color: rgb(16 185 129);
        color: #000000 !important;
    }

    .fi-topbar-item.fi-active .fi-topbar-item-btn .fi-topbar-item-label,
    .fi-topbar-item.fi-active .fi-topbar-item-btn .fi-topbar-item-icon {
        color: #000000 !important;
    }

    .fi-topbar-item:not(.fi-active) .fi-topbar-item-btn:hover {
        background-color: rgb(209 250 229);
        color: #000000;
    }

    .bg-books .fi-ta-content-ctn .fi-ta-content.fi-ta-content-grid {
        gap: 1rem;
        padding: 1rem 1.25rem 1.25rem;
    }

    .bg-books .fi-ta-content-ctn .fi-ta-content.fi-ta-content-grid .fi-ta-record {
        padding: 0.5rem 0.5rem 0.625rem;
        overflow: hidden;
    }

    .bg-books .fi-ta-content-ctn .fi-ta-content .fi-ta-record .fi-ta-record-content-ctn {
        row-gap: 0.625rem;
        padding: 0;
    }

    .bg-books .fi-ta-record-content,
    .bg-books .fi-ta-record-content-ctn > div {
        padding: 0 !important;
        width: 100%;
    }

    .bg-books .fi-ta-stack {
        gap: 0.125rem;
    }

    .bg-books .fi-ta-stack > * {
        margin: 0 !important;
        width: 100%;
    }

    .bg-books .fi-ta-col,
    .bg-books .fi-ta-text,
    .bg-books .fi-ta-image {
        padding-inline: 0.125rem;
    }

    .bg-books .fi-ta-image {
        padding: 0 0 0.5rem;
    }

    .bg-book-cover {
        display: block;
        width: 100%;
        height: auto;
        aspect-ratio: 2 / 3;
        object-fit: cover;
        border-radius: 0.5rem;
        background: var(--gray-100);
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.08), 0 4px 12px -4px rgb(0 0 0 / 0.18);
    }

    .dark .bg-book-cover {
        background: rgb(255 255 255 / 0.05);
    }

    .bg-books .fi-ta-text.fi-size-lg {
        font-size: 0.9375rem;
        line-height: 1.3;
        letter-spacing: -0.01em;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .bg-books .fi-ta-text.fi-size-sm {
        font-size: 0.8125rem;
        line-height: 1.35;
    }

    .bg-books .fi-ta-text-has-badges {
        padding-top: 0.25rem;
    }

    .bg-books .fi-ta-actions {
        width: 100%;
        padding: 0 0.125rem !important;
    }

    .bg-books .fi-ta-actions > *,
    .bg-books .fi-ta-actions .fi-btn {
        width: 100%;
        justify-content: center;
    }

    /* Overdue fine modal: amount + GCash QR */
    .bg-fine {
        display: grid;
        gap: 1.25rem;
        align-items: start;
    }

    @media (min-width: 40rem) {
        .bg-fine {
            grid-template-columns: 11.5rem 1fr;
        }
    }

    .bg-fine-qr {
        justify-self: center;
        width: 11.5rem;
        border-radius: 0.875rem;
        overflow: hidden;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.08), 0 8px 24px -8px rgb(0 0 0 / 0.25);
    }

    .bg-fine-qr img {
        display: block;
        width: 100%;
        height: auto;
    }

    .bg-fine-info {
        display: grid;
        gap: 1.25rem;
    }

    .bg-fine-amount-block {
        padding: 1rem 1.125rem;
        border-radius: 0.75rem;
        background: rgb(239 68 68 / 0.06);
        box-shadow: inset 0 0 0 1px rgb(239 68 68 / 0.2);
    }

    .bg-fine-label {
        margin: 0;
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--danger-600);
    }

    .dark .bg-fine-label {
        color: var(--danger-400);
    }

    .bg-fine-amount {
        margin: 0.125rem 0 0;
        font-size: 2.25rem;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
        color: var(--gray-950);
    }

    .dark .bg-fine-amount {
        color: white;
    }

    .bg-fine-breakdown {
        margin: 0.25rem 0 0;
        font-size: 0.8125rem;
        color: var(--gray-500);
    }

    .bg-fine-steps {
        display: grid;
        gap: 0.625rem;
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: 0.875rem;
        line-height: 1.45;
        color: var(--gray-700);
    }

    .dark .bg-fine-steps {
        color: var(--gray-300);
    }

    .bg-fine-steps li {
        display: flex;
        gap: 0.625rem;
    }

    .bg-fine-steps li > p {
        margin: 0;
    }

    .bg-fine-steps li > span {
        display: grid;
        place-items: center;
        flex-shrink: 0;
        width: 1.375rem;
        height: 1.375rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        color: white;
        background: var(--gray-950);
    }

    .dark .bg-fine-steps li > span {
        color: var(--gray-950);
        background: white;
    }

    /* Requests awaiting approval: the book isn't theirs yet */
    .bg-books .fi-ta-record.bg-book-pending .bg-book-cover {
        opacity: 0.6;
        filter: saturate(0.5);
    }

    /* Due-date states on borrowed books */
    .bg-books .fi-ta-content-ctn .fi-ta-content.fi-ta-content-grid .fi-ta-record.bg-book-overdue {
        --tw-ring-color: rgb(239 68 68 / 0.55);
        background-color: rgb(254 242 242);
    }

    .dark .bg-books .fi-ta-content-ctn .fi-ta-content.fi-ta-content-grid .fi-ta-record.bg-book-overdue {
        --tw-ring-color: rgb(239 68 68 / 0.5);
        background-color: rgb(239 68 68 / 0.08);
    }

    .bg-books .fi-ta-content-ctn .fi-ta-content.fi-ta-content-grid .fi-ta-record.bg-book-due-soon {
        --tw-ring-color: rgb(245 158 11 / 0.55);
    }

    .dark .bg-books .fi-ta-content-ctn .fi-ta-content.fi-ta-content-grid .fi-ta-record.bg-book-due-soon {
        --tw-ring-color: rgb(245 158 11 / 0.45);
    }

    .bg-books .fi-btn:active:not(:disabled) {
        transform: scale(0.97);
    }

</style>
