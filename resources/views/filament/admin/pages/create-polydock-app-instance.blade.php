<x-filament-panels::page>
    <form wire:submit="create">
        @csrf
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" size="lg">
                Create Instance
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
