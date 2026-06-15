<x-filament-panels::page>
    <div class="w-full bg-white dark:bg-gray-900 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800 shadow-sm">
        <iframe src="{{ url(config('horizon.path', 'horizon')) }}" class="w-full border-0" style="height: calc(100vh - 200px); min-height: 700px;"></iframe>
    </div>
</x-filament-panels::page>
