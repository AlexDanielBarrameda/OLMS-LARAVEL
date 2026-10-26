<style>
    .fi-global-search {
        display: none !important;
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
