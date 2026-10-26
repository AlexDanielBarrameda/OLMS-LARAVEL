{{-- Overrides Filament's Light/Dark/System switcher with a two-state control. "System" users see whichever theme is currently resolved. --}}
@props([
    'compact' => false,
])

<div
    x-data
    @class(['bg-appearance', 'bg-appearance-compact' => $compact])
>
    @unless ($compact)
        <span class="bg-appearance-label">Appearance</span>
    @endunless

    <div
        role="radiogroup"
        aria-label="Appearance"
        x-bind:class="{ 'is-dark': $store.theme === 'dark' }"
        class="bg-segmented"
    >
        <span class="bg-segmented-thumb" aria-hidden="true"></span>

        @foreach (['light' => \Filament\Support\Icons\Heroicon::Sun, 'dark' => \Filament\Support\Icons\Heroicon::Moon] as $theme => $icon)
            @php
                $label = __("filament-panels::layout.actions.theme_switcher.{$theme}.label");
            @endphp

            <button
                type="button"
                role="radio"
                aria-label="{{ $label }}"
                x-bind:aria-checked="$store.theme === @js($theme)"
                x-bind:class="{ 'is-active': $store.theme === @js($theme) }"
                x-on:click="$store.theme !== @js($theme) && window.bookGarden.switchTheme(@js($theme), $el)"
                x-tooltip="{
                    content: @js($label),
                    theme: $store.theme,
                }"
                class="bg-segment"
            >
                {{
                    \Filament\Support\generate_icon_html($icon, alias: match ($theme) {
                        'light' => \Filament\View\PanelsIconAlias::THEME_SWITCHER_LIGHT_BUTTON,
                        'dark' => \Filament\View\PanelsIconAlias::THEME_SWITCHER_DARK_BUTTON,
                    })
                }}
            </button>
        @endforeach
    </div>
</div>
