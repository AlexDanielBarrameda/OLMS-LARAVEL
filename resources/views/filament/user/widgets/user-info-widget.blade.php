<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display: flex; align-items: center; gap: 1.5rem; padding: 0.5rem 0;">
            
            <div style="height: 5rem; width: 5rem; border-radius: 9999px; background-color: #059669; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.875rem; font-weight: bold; flex-shrink: 0;">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: bold; margin: 0;">
                    {{ $user->name }}
                </h2>
                
                <p style="font-size: 0.875rem; color: #6b7280; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                    <x-heroicon-m-envelope style="width: 1.25rem; height: 1.25rem;" /> 
                    {{ $user->email }}
                </p>
                
                <p style="font-size: 0.875rem; color: #6b7280; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                    <x-heroicon-m-book-open style="width: 1.25rem; height: 1.25rem;" /> 
                    {{ $currentlyBorrowed }} books currently borrowed
                </p>
                
                <p style="font-size: 0.875rem; color: #6b7280; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                    <x-heroicon-m-clock style="width: 1.25rem; height: 1.25rem;" /> 
                    Member since {{ $user->created_at->format('F Y') }}
                </p>
            </div>

        </div>
    </x-filament::section>
</x-filament-widgets::widget>