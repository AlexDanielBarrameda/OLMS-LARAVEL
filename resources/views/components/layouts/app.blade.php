<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Book Garden Library' }}</title>

        @vite(['resources/css/app.css'])
        
        @livewireStyles
        @filamentStyles

        <style>
            /* Hides Alpine.js elements before they fully load */
            [x-cloak] { display: none !important; }
            
            /* Notifications float top-right */
            .fi-no {
                position: fixed !important;
                top: 2rem !important;
                right: 2rem !important;
                z-index: 9999 !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 1rem !important;
                pointer-events: none !important;
                width: 100% !important;
                max-width: 24rem !important;
            }

            /* Notification card */
            .fi-no-notification {
                pointer-events: auto !important;
                background-color: white !important;
                border-radius: 0.75rem !important;
                box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1) !important;
                padding: 1rem !important;
                border: 1px solid #f3f4f6 !important;
                display: flex !important;
                align-items: flex-start !important;
                gap: 1rem !important;
                animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }

            /* Notification icons */
            .fi-no-notification svg {
                width: 1.5rem !important;
                height: 1.5rem !important;
                flex-shrink: 0 !important;
            }

            /* Notification title and body */
            .fi-no-notification-title {
                font-weight: 600 !important;
                color: #111827 !important;
                font-size: 0.875rem !important;
            }
            
            .fi-no-notification-body {
                color: #4b5563 !important;
                font-size: 0.875rem !important;
                margin-top: 0.25rem !important;
                line-height: 1.25rem !important;
            }

            /* Semantic icon colors: red for errors, green for success */
            .text-danger-600 { color: #ef4444 !important; }
            .text-success-600 { color: #10b981 !important; }

            /* Smooth entrance animation */
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        </style>
    </head>
    <body class="antialiased">
        {{ $slot }}
        
        @livewire('notifications')
        
        @livewireScripts
        @filamentScripts
    </body>
</html>