<?php

use App\Actions\Walks\LogWalk;
use App\Enums\GroupRole;
use App\Enums\WalkDirection;
use App\Models\Group;
use App\Models\Hill;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Log a walk')] class extends Component
{
    public ?int $groupId = null;

    public string $title = '';

    public string $notes = '';

    /** @var list<array{date: string, direction: string, duration: string, hills: list<int>}> */
    public array $days = [];

    /** The people who were on the walk, by user id. */
    public array $people = [];

    /** Who climbed which hill: [day index][hill id] => list of user ids. */
    public array $ticks = [];

    /** Names of people with no account who came along (group walks only). */
    public array $guests = [];

    public string $guestName = '';

    /** The day the hill picker adds to, as an index. */
    public int $pickerDay = 0;

    public string $list = 'M';

    public string $search = '';

    public function mount(): void
    {
        $this->people = [Auth::id()];
        $this->days = [$this->blankDay()];

        $groupId = (int) request()->query('group');
        if ($groupId > 0 && $this->groups->contains('id', $groupId)) {
            $this->groupId = $groupId;
        }

        $hill = Hill::where('slug', request()->query('hill'))->first();
        if ($hill !== null) {
            $this->addHill($hill->id);
        }
    }

    /**
     * @return array{date: string, direction: string, duration: string, hills: list<int>}
     */
    private function blankDay(): array
    {
        return ['date' => now()->toDateString(), 'direction' => '', 'duration' => '', 'hills' => []];
    }

    /**
     * The groups this person can log walks into.
     *
     * @return Collection<int, Group>
     */
    #[Computed]
    public function groups(): Collection
    {
        return Auth::user()->groups()
            ->wherePivotIn('role', [GroupRole::Admin->value, GroupRole::Member->value])
            ->orderBy('name')
            ->get();
    }

    /**
     * Who could have been on a walk with the chosen group.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function groupPeople(): Collection
    {
        if ($this->groupId === null) {
            return new Collection;
        }

        return Group::findOrFail($this->groupId)->users()
            ->wherePivotIn('role', [GroupRole::Admin->value, GroupRole::Member->value])
            ->orderBy('name')
            ->get();
    }

    /**
     * Every hill on the form, by id, so each can show its name.
     *
     * @return \Illuminate\Support\Collection<int, Hill>
     */
    #[Computed]
    public function hillsOnForm(): \Illuminate\Support\Collection
    {
        $ids = collect($this->days)->pluck('hills')->flatten()->all();

        return Hill::whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * Hills matching the picker, leaving out what the chosen day already has.
     *
     * @return Collection<int, Hill>
     */
    #[Computed]
    public function results(): Collection
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return new Collection;
        }

        $already = $this->days[$this->pickerDay]['hills'] ?? [];

        return Hill::query()
            ->when(array_key_exists($this->list, config('dobih.lists')), fn ($query) => $query
                ->whereHas('classes', fn ($classes) => $classes->where('hill_classes.code', $this->list)))
            ->whereNotIn('id', $already)
            ->where(fn ($query) => $query
                ->where('name', 'like', '%'.trim($this->search).'%')
                ->orWhereHas('alternateNames', fn ($names) => $names->where('name', 'like', '%'.trim($this->search).'%')))
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    public function updatedGroupId(): void
    {
        // Whoever was chosen for the old group might not be in the new one.
        $this->groupId = $this->groupId === null || $this->groupId === 0 ? null : $this->groupId;

        if ($this->groupId !== null && ! $this->groups->contains('id', $this->groupId)) {
            $this->groupId = null;
        }

        $this->people = [Auth::id()];
        $this->guests = [];
        $this->syncTicks();
    }

    public function togglePerson(int $userId): void
    {
        if (! $this->groupPeople->contains('id', $userId)) {
            return;
        }

        $this->people = in_array($userId, $this->people, true)
            ? array_values(array_diff($this->people, [$userId]))
            : [...$this->people, $userId];

        $this->syncTicks();
    }

    /**
     * Keep the "who climbed what" grid to the people on the walk. Anyone added is ticked for
     * every hill, which is what most walks are.
     */
    private function syncTicks(): void
    {
        foreach ($this->days as $day => $data) {
            foreach ($data['hills'] as $hillId) {
                $current = $this->ticks[$day][$hillId] ?? [];
                $kept = array_values(array_intersect($current, $this->people));
                $added = array_diff($this->people, $current);

                // A person newly on the walk is ticked everywhere; someone already ticked stays so.
                $this->ticks[$day][$hillId] = array_values(array_unique([...$kept, ...$added]));
            }
        }
    }

    public function toggleTick(int $day, int $hillId, int $userId): void
    {
        if (! in_array($userId, $this->people, true)) {
            return;
        }

        $current = $this->ticks[$day][$hillId] ?? [];

        $this->ticks[$day][$hillId] = in_array($userId, $current, true)
            ? array_values(array_diff($current, [$userId]))
            : [...$current, $userId];
    }

    public function addDay(): void
    {
        $last = end($this->days);

        $this->days[] = [...$this->blankDay(), 'date' => $last['date'] ?? now()->toDateString()];
    }

    public function removeDay(int $index): void
    {
        if (count($this->days) <= 1 || ! isset($this->days[$index])) {
            return;
        }

        unset($this->days[$index], $this->ticks[$index]);
        $this->days = array_values($this->days);
        $this->ticks = array_values($this->ticks);
        $this->pickerDay = min($this->pickerDay, count($this->days) - 1);
    }

    public function addHill(int $hillId): void
    {
        $day = $this->pickerDay;

        if (! isset($this->days[$day]) || in_array($hillId, $this->days[$day]['hills'], true) || ! Hill::whereKey($hillId)->exists()) {
            return;
        }

        $this->days[$day]['hills'][] = $hillId;
        $this->ticks[$day][$hillId] = $this->people;
        $this->search = '';
    }

    public function removeHill(int $day, int $hillId): void
    {
        $this->days[$day]['hills'] = array_values(array_diff($this->days[$day]['hills'], [$hillId]));
        unset($this->ticks[$day][$hillId]);
    }

    public function moveHill(int $day, int $hillId, string $direction): void
    {
        $hills = $this->days[$day]['hills'];
        $position = array_search($hillId, $hills, true);
        $swap = $direction === 'up' ? $position - 1 : $position + 1;

        if ($position === false || ! isset($hills[$swap])) {
            return;
        }

        [$hills[$position], $hills[$swap]] = [$hills[$swap], $hills[$position]];
        $this->days[$day]['hills'] = $hills;
    }

    public function moveToDay(int $from, int $hillId, int $to): void
    {
        if ($from === $to || ! isset($this->days[$to]) || in_array($hillId, $this->days[$to]['hills'], true)) {
            return;
        }

        $this->days[$to]['hills'][] = $hillId;
        $this->ticks[$to][$hillId] = $this->ticks[$from][$hillId] ?? $this->people;
        $this->removeHill($from, $hillId);
    }

    public function addGuest(): void
    {
        $name = trim($this->guestName);

        if ($this->groupId !== null && $name !== '' && ! in_array($name, $this->guests, true) && count($this->guests) < 20) {
            $this->guests[] = mb_substr($name, 0, 100);
        }

        $this->guestName = '';
    }

    public function removeGuest(int $index): void
    {
        unset($this->guests[$index]);
        $this->guests = array_values($this->guests);
    }

    public function save(LogWalk $logWalk): void
    {
        $this->validate([
            'title' => ['nullable', 'string', 'max:250'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'days' => ['required', 'array', 'min:1', 'max:21'],
            'days.*.date' => ['required', 'date', 'before_or_equal:today'],
            'days.*.direction' => ['nullable', 'in:'.implode(',', array_map(fn (WalkDirection $d) => $d->value, WalkDirection::cases()))],
            'days.*.duration' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'days.*.hills' => ['required', 'array', 'min:1'],
        ], [
            'days.*.date.before_or_equal' => __('A walk that has happened can\'t be in the future.'),
            'days.*.hills.required' => __('Every day needs at least one hill.'),
            'days.*.hills.min' => __('Every day needs at least one hill.'),
        ]);

        try {
            $walk = $logWalk->handle(Auth::user(), [
                'group_id' => $this->groupId,
                'title' => $this->title,
                'notes' => $this->notes,
                'days' => array_map(fn (array $day) => [
                    'date' => $day['date'],
                    'direction' => $day['direction'] !== '' ? $day['direction'] : null,
                    'duration_minutes' => $day['duration'] !== '' ? (int) $day['duration'] : null,
                    'hills' => $day['hills'],
                ], $this->days),
                'people' => $this->people,
                'ticks' => $this->ticks,
                'guests' => $this->guests,
            ]);
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->redirectRoute('walks.show', $walk, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-2xl space-y-8">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Log a walk') }}</flux:heading>
            <flux:subheading>{{ __('Record hills you have climbed: one, a round, or a trip over several days.') }}</flux:subheading>
        </div>

        <flux:button icon="x-mark" :href="route('walks.index')" wire:navigate variant="ghost" aria-label="{{ __('Cancel') }}" />
    </div>

    <form wire:submit="save" class="space-y-8" data-test="log-walk-form">
        {{-- Who --}}
        <section class="space-y-4">
            <flux:select wire:model.live="groupId" :label="__('Who with')">
                <flux:select.option value="">{{ __('Solo') }}</flux:select.option>
                @foreach ($this->groups as $group)
                    <flux:select.option value="{{ $group->id }}">{{ $group->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($groupId !== null)
                <div class="space-y-2" data-test="people">
                    <flux:text class="text-sm font-medium">{{ __('Who was on the walk') }}</flux:text>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->groupPeople as $person)
                            <flux:button
                                size="sm"
                                type="button"
                                :variant="in_array($person->id, $people, true) ? 'primary' : 'outline'"
                                wire:click="togglePerson({{ $person->id }})"
                                wire:key="person-{{ $person->id }}"
                            >
                                {{ $person->name }}
                            </flux:button>
                        @endforeach
                    </div>

                    @error('people') <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror

                    <div class="flex flex-wrap items-center gap-2 pt-2">
                        @foreach ($guests as $index => $guest)
                            <flux:badge wire:key="guest-{{ $index }}">
                                {{ $guest }}
                                <button type="button" wire:click="removeGuest({{ $index }})" class="ms-1" aria-label="{{ __('Remove :name', ['name' => $guest]) }}">&times;</button>
                            </flux:badge>
                        @endforeach
                    </div>

                    <div class="flex items-end gap-2">
                        <flux:input wire:model="guestName" wire:keydown.enter.prevent="addGuest" :label="__('A guest with no account')" placeholder="{{ __('Name') }}" class="flex-1" />
                        <flux:button type="button" wire:click="addGuest">{{ __('Add') }}</flux:button>
                    </div>
                </div>
            @endif
        </section>

        {{-- Days --}}
        @foreach ($days as $index => $day)
            <section class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="day-{{ $index }}" data-test="day">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ count($days) > 1 ? __('Day :n', ['n' => $index + 1]) : __('The walk') }}</flux:heading>

                    @if (count($days) > 1)
                        <flux:button size="xs" variant="ghost" type="button" wire:click="removeDay({{ $index }})">{{ __('Remove day') }}</flux:button>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <flux:input type="date" wire:model="days.{{ $index }}.date" :label="__('Date')" :max="now()->toDateString()" />
                    <flux:input type="number" min="1" wire:model="days.{{ $index }}.duration" :label="__('Minutes taken (optional)')" />
                </div>

                <flux:select wire:model="days.{{ $index }}.direction" :label="__('Direction (optional)')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach (WalkDirection::cases() as $direction)
                        <flux:select.option value="{{ $direction->value }}">{{ $direction->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="space-y-2">
                    @forelse ($day['hills'] as $position => $hillId)
                        @php $hill = $this->hillsOnForm[$hillId] ?? null; @endphp

                        @if ($hill)
                            <div class="space-y-2 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900" wire:key="day-{{ $index }}-hill-{{ $hillId }}" data-test="day-hill">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0 font-medium">
                                        <span class="text-zinc-500">{{ $position + 1 }}.</span> {{ $hill->name }}
                                        <span class="text-sm font-normal text-zinc-500">{{ $hill->height_m }}m</span>
                                    </div>

                                    <div class="flex shrink-0 items-center">
                                        <flux:button size="xs" variant="ghost" icon="chevron-up" type="button" wire:click="moveHill({{ $index }}, {{ $hillId }}, 'up')" aria-label="{{ __('Move up') }}" />
                                        <flux:button size="xs" variant="ghost" icon="chevron-down" type="button" wire:click="moveHill({{ $index }}, {{ $hillId }}, 'down')" aria-label="{{ __('Move down') }}" />
                                        <flux:button size="xs" variant="ghost" icon="x-mark" type="button" wire:click="removeHill({{ $index }}, {{ $hillId }})" aria-label="{{ __('Remove') }}" />
                                    </div>
                                </div>

                                @if (count($days) > 1)
                                    <flux:select size="sm" wire:change="moveToDay({{ $index }}, {{ $hillId }}, $event.target.value)" aria-label="{{ __('Move to another day') }}">
                                        <flux:select.option value="{{ $index }}">{{ __('Move to another day…') }}</flux:select.option>
                                        @foreach ($days as $other => $unused)
                                            @if ($other !== $index)
                                                <flux:select.option value="{{ $other }}">{{ __('Day :n', ['n' => $other + 1]) }}</flux:select.option>
                                            @endif
                                        @endforeach
                                    </flux:select>
                                @endif

                                @if ($groupId !== null && count($people) > 1)
                                    <div class="flex flex-wrap gap-1.5" data-test="who-did-it">
                                        @foreach ($this->groupPeople->whereIn('id', $people) as $person)
                                            <flux:button
                                                size="xs"
                                                type="button"
                                                :variant="in_array($person->id, $ticks[$index][$hillId] ?? [], true) ? 'filled' : 'ghost'"
                                                wire:click="toggleTick({{ $index }}, {{ $hillId }}, {{ $person->id }})"
                                                wire:key="tick-{{ $index }}-{{ $hillId }}-{{ $person->id }}"
                                            >
                                                {{ in_array($person->id, $ticks[$index][$hillId] ?? [], true) ? '✓ ' : '' }}{{ $person->name }}
                                            </flux:button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    @empty
                        <flux:text class="text-sm">{{ __('No hills yet. Search below to add some.') }}</flux:text>
                    @endforelse
                </div>

                @error("days.$index.date") <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror
                @error("days.$index.hills") <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror
                @error("days.$index.duration") <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror
            </section>
        @endforeach

        @error('days') <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror

        <div>
            <flux:button type="button" wire:click="addDay" icon="plus" size="sm">{{ __('Add another day') }}</flux:button>
        </div>

        {{-- Picker --}}
        <section class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="hill-picker">
            <flux:heading>{{ __('Add hills') }}</flux:heading>

            <div class="grid grid-cols-2 gap-3">
                @if (count($days) > 1)
                    <flux:select wire:model.live="pickerDay" :label="__('To')">
                        @foreach ($days as $index => $unused)
                            <flux:select.option value="{{ $index }}">{{ __('Day :n', ['n' => $index + 1]) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:select wire:model.live="list" :label="__('From')">
                    @foreach (config('dobih.lists') as $code => $name)
                        <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="all">{{ __('All hills') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search by name…') }}" />

            @if (mb_strlen(trim($search)) >= 2 && $this->results->isEmpty())
                <flux:text class="text-sm" data-test="no-results">{{ __('Nothing found in this list. Try another list, or All hills.') }}</flux:text>
            @endif

            @foreach ($this->results as $result)
                <div class="flex items-center justify-between gap-3" wire:key="result-{{ $result->id }}">
                    <div class="min-w-0 truncate">{{ $result->name }} <span class="text-sm text-zinc-500">{{ $result->height_m }}m</span></div>
                    <flux:button size="xs" type="button" wire:click="addHill({{ $result->id }})" data-test="add-hill">{{ __('Add') }}</flux:button>
                </div>
            @endforeach
        </section>

        {{-- Details --}}
        <section class="space-y-4">
            <flux:input wire:model="title" :label="__('Title (optional)')" placeholder="{{ __('For example, Knoydart weekend') }}" />
            <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="3" />
        </section>

        <flux:button type="submit" variant="primary" class="w-full" data-test="save-walk-button">{{ __('Save the walk') }}</flux:button>
    </form>
</div>
