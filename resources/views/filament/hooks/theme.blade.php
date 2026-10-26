{{-- Shared by the admin and user panels: Light/Dark segmented control + "water drop" theme wave --}}
<style>
    :root {
        --bg-ease-out: cubic-bezier(0.23, 1, 0.32, 1);
    }

    /* Appearance row inside the user menu */
    .bg-appearance {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.375rem 0.5rem;
    }

    .bg-appearance-label {
        font-size: 0.875rem;
        color: var(--gray-700);
    }

    .dark .bg-appearance-label {
        color: var(--gray-200);
    }

    .bg-segmented {
        position: relative;
        display: grid;
        grid-template-columns: 1fr 1fr;
        padding: 2px;
        border-radius: 9999px;
        background: var(--gray-100);
    }

    .dark .bg-segmented {
        background: rgb(255 255 255 / 0.06);
    }

    .bg-segmented-thumb {
        position: absolute;
        top: 2px;
        bottom: 2px;
        left: 2px;
        width: calc(50% - 2px);
        border-radius: 9999px;
        background: white;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.12), 0 0 0 0.5px rgb(0 0 0 / 0.06);
        transition: transform 240ms var(--bg-ease-out);
    }

    .dark .bg-segmented-thumb {
        background: var(--gray-700);
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.4);
    }

    .bg-segmented.is-dark .bg-segmented-thumb {
        transform: translateX(100%);
    }

    .bg-segment {
        position: relative;
        display: grid;
        place-items: center;
        width: 2.25rem;
        height: 1.75rem;
        border-radius: 9999px;
        color: var(--gray-400);
        transition: color 160ms ease, transform 120ms ease-out;
    }

    .bg-segment .fi-icon {
        width: 1rem;
        height: 1rem;
    }

    .bg-segment.is-active {
        color: var(--gray-950);
    }

    .dark .bg-segment.is-active {
        color: white;
    }

    .bg-segment:active {
        transform: scale(0.94);
    }

    .bg-segment:focus-visible {
        outline: 2px solid var(--primary-500);
        outline-offset: 1px;
    }

    @media (hover: hover) and (pointer: fine) {
        .bg-segment:not(.is-active):hover {
            color: var(--gray-600);
        }

        .dark .bg-segment:not(.is-active):hover {
            color: var(--gray-300);
        }
    }

    /* The wave: the new theme is revealed by an expanding circle (see bookGarden.switchTheme) */
    @media (prefers-reduced-motion: no-preference) {
        ::view-transition-old(root),
        ::view-transition-new(root) {
            animation: none;
            mix-blend-mode: normal;
        }
    }

    /* Freeze colour transitions while switching so the revealed theme is already settled */
    .bg-theme-switching *:not(.bg-segmented-thumb),
    .bg-theme-switching *::before,
    .bg-theme-switching *::after {
        transition: none !important;
    }

    /* Theme toggle, bell, then the avatar (links to the profile) at the end of the top bar */
    .bg-appearance-compact {
        padding: 0;
    }

    .bg-avatar-link {
        margin-left: 0.75rem;
        flex-shrink: 0;
        display: grid;
        place-items: center;
        border-radius: 9999px;
        outline: none;
        transition: transform 120ms ease-out, box-shadow 160ms ease;
    }

    .bg-avatar-link.is-active {
        box-shadow: 0 0 0 2px white, 0 0 0 4px rgb(16 185 129);
    }

    .dark .bg-avatar-link.is-active {
        box-shadow: 0 0 0 2px var(--gray-900), 0 0 0 4px rgb(16 185 129);
    }

    .bg-avatar-link:focus-visible {
        box-shadow: 0 0 0 2px white, 0 0 0 4px var(--primary-500);
    }

    .bg-avatar-link:active {
        transform: scale(0.95);
    }

    @media (hover: hover) and (pointer: fine) {
        .bg-avatar-link:not(.is-active):hover {
            box-shadow: 0 0 0 3px rgb(16 185 129 / 0.3);
        }
    }


    /* Profile: account section */
    .bg-account {
        display: grid;
        gap: 1rem;
    }

    @media (min-width: 48rem) {
        .bg-account {
            grid-template-columns: 1fr 1fr;
        }
    }

    .bg-account-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .bg-account-text {
        flex: 1 1 14rem;
        margin: 0;
        font-size: 0.875rem;
        line-height: 1.5;
        color: var(--gray-500);
    }

    .dark .bg-account-text {
        color: var(--gray-400);
    }

    /* Staff profile header */
    .bg-profile-card {
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .bg-profile-card .bg-profile-avatar {
        width: 4rem;
        height: 4rem;
        font-size: 1.5rem;
    }

    .bg-profile-meta {
        display: grid;
        gap: 0.25rem;
        min-width: 0;
    }

    .bg-profile-name-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.625rem;
    }

    .bg-profile-name {
        margin: 0;
        font-size: 1.375rem;
        font-weight: 600;
        line-height: 1.2;
        letter-spacing: -0.015em;
    }

    .bg-profile-detail {
        margin: 0;
        font-size: 0.875rem;
        color: var(--gray-500);
    }

    .dark .bg-profile-detail {
        color: var(--gray-400);
    }
</style>

<script>
    window.bookGarden = window.bookGarden || {}

    window.bookGarden.switchTheme = (theme, origin) => {
        const root = document.documentElement

        const apply = () => {
            // Set the class synchronously so the transition captures the final state;
            // Filament's own handler then persists it and updates $store.theme.
            root.classList.toggle('dark', theme === 'dark')
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme }))
        }

        if (! document.startViewTransition) {
            apply()

            return
        }

        const rect = origin.getBoundingClientRect()
        const x = rect.left + rect.width / 2
        const y = rect.top + rect.height / 2
        const radius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y))

        root.classList.add('bg-theme-switching')

        const transition = document.startViewTransition(apply)

        transition.ready.then(() => {
            // Reduced motion keeps the browser's default cross-fade instead of the wave
            if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return
            }

            root.animate(
                {
                    clipPath: [
                        `circle(0px at ${x}px ${y}px)`,
                        `circle(${radius}px at ${x}px ${y}px)`,
                    ],
                },
                {
                    duration: 650,
                    easing: 'cubic-bezier(0.23, 1, 0.32, 1)',
                    pseudoElement: '::view-transition-new(root)',
                },
            )
        })

        transition.finished.finally(() => root.classList.remove('bg-theme-switching'))
    }
</script>
