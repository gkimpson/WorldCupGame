<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
    <div>
        <flux:heading size="xl">{{ __('Private Leagues') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Create a league for friends or join one with a code.') }}</flux:text>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card>
            <form wire:submit="createLeague" class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Create League') }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500">{{ __('You will be added as the owner automatically.') }}</flux:text>
                </div>

                <flux:input wire:model="name" :label="__('League name')" type="text" required />

                <flux:button type="submit" variant="primary" icon="plus" wire:loading.attr="disabled">
                    {{ __('Create') }}
                </flux:button>
            </form>
        </flux:card>

        <flux:card>
            <form wire:submit="joinLeague" class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Join League') }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500">{{ __('Enter the private code shared by a league member.') }}</flux:text>
                </div>

                <flux:input wire:model="joinCode" :label="__('Join code')" type="text" required />

                <flux:button type="submit" variant="primary" icon="arrow-right" wire:loading.attr="disabled">
                    {{ __('Join') }}
                </flux:button>
            </form>
        </flux:card>
    </div>

    <flux:card>
        <div class="mb-4 flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Your Leagues') }}</flux:heading>
            <flux:badge>{{ $leagues->count() }}</flux:badge>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('League') }}</flux:table.column>
                <flux:table.column>{{ __('Owner') }}</flux:table.column>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($leagues as $membership)
                    <flux:table.row wire:key="league-{{ $membership->league_id }}">
                        <flux:table.cell>
                            <flux:link :href="route('leagues.show', $membership->league)" wire:navigate>
                                {{ $membership->league->name }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $membership->league->owner->name }}</flux:table.cell>
                        <flux:table.cell>
                            <code class="rounded border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900">
                                {{ $membership->league->join_code }}
                            </code>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $membership->role->label() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <flux:text class="text-center text-zinc-500">{{ __('You are not in any private leagues yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
