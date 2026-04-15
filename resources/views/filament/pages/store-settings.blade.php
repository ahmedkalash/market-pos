<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button type="submit">
                {{ __('company_settings.save_settings') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
