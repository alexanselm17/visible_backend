<x-filament-panels::page>
    <form wire:submit="applyFilters" class="space-y-4">
        {{ $this->form }}
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-o-funnel" wire:loading.attr="disabled" wire:target="applyFilters">Apply filters</x-filament::button>
            <x-filament::button type="button" color="gray" wire:click="resetFilters" wire:loading.attr="disabled">Reset</x-filament::button>
            <span class="text-sm text-gray-500 dark:text-gray-400" role="status">Showing {{ \App\Support\IncomeReportFilters::make($this->filters)->label() }}</span>
        </div>
    </form>

    <x-filament::section icon="heroicon-o-information-circle">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            <strong>Approved income</strong> is paid token purchases, dated by approval time.
            <strong>Pending approval</strong> is bank-confirmed money not yet allocated.
            <strong>Unpaid orders</strong> and receipt claims without a bank confirmation are not income.
            Bank totals and approved income overlap: <strong>do not add them together</strong>.
            This is an operational sales dashboard, not a net-profit or tax report. All statuses reflect the current record, not a historical snapshot.
        </p>
    </x-filament::section>

    <x-filament-widgets::widgets :columns="$this->getColumns()" :widgets="$this->getVisibleWidgets()" :data="['filters' => $this->filters]" />
</x-filament-panels::page>
