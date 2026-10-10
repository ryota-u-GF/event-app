<?php

use App\Models\Event;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('イベントを編集')] class extends Component {
    public Event $event;

    #[Validate('required|string|max:100')]
    public string $title = '';

    #[Validate('required|string|max:2000')]
    public string $description = '';

    #[Validate('required|string|max:100')]
    public string $venue = '';

    #[Validate('required|date')]
    public string $starts_at = '';

    #[Validate('required|date|after:starts_at')]
    public string $ends_at = '';

    public string $ticketName = '';

    public string $ticketPrice = '';

    public string $ticketCapacity = '';

    public function mount(Event $event): void
    {

        $this->authorize('update', $event);

        $this->event = $event;
        $this->title = $event->title;
        $this->description = $event->description;
        $this->venue = $event->venue;
        $this->starts_at = $event->starts_at->format('Y-m-d\TH:i');
        $this->ends_at = $event->ends_at->format('Y-m-d\TH:i');
    }

    public function save(): void
    {

        $this->authorize('update', $this->event);

        $this->event->update($this->validate());

        session()->flash('status', 'イベントを更新しました。');

        $this->redirectRoute('events.index', navigate: true);
    }

    public function delete(): void
    {

        $this->authorize('update', $this->event);

        $this->event->delete();

        session()->flash('status', 'イベントを削除しました。');

        $this->redirectRoute('events.index', navigate: true);
    }

    public function duplicate(): void
    {

        $this->authorize('update', $this->event);

        $copy = Event::create([
            'user_id' => auth()->id(),
            'title' => mb_substr($this->event->title, 0, 96).'（複製）',
            'description' => $this->event->description,
            'venue' => $this->event->venue,
            'starts_at' => $this->event->starts_at->copy()->addWeek(),
            'ends_at' => $this->event->ends_at->copy()->addWeek(),
        ]);

        session()->flash('status', 'イベントを複製しました。');

        $this->redirectRoute('events.edit', $copy, navigate: true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\TicketType>
     */
    #[Computed]
    public function ticketTypes()
    {
        return $this->event->ticketTypes()->orderBy('id')->get();
    }

    public function addTicketType(): void
    {
        $this->authorize('update', $this->event);

        $validated = $this->validate([
            'ticketName' => 'required|string|max:50',
            'ticketPrice' => 'required|integer|min:0|max:1000000',
            'ticketCapacity' => 'required|integer|min:1|max:100000',
        ]);

        $this->event->ticketTypes()->create([
            'name' => $validated['ticketName'],
            'price' => $validated['ticketPrice'],
            'capacity' => $validated['ticketCapacity'],
        ]);

        $this->reset('ticketName', 'ticketPrice', 'ticketCapacity');
        unset($this->ticketTypes);
    }

    public function deleteTicketType(int $id): void
    {
        $this->authorize('update', $this->event);

        $this->event->ticketTypes()->findOrFail($id)->delete();

        unset($this->ticketTypes);
    }
};
?>

<div class="mx-auto max-w-2xl space-y-6">
    <flux:heading size="xl">イベントを編集</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="title" label="タイトル" />
        <flux:textarea wire:model="description" label="説明" rows="6" />
        <flux:input wire:model="venue" label="会場" />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="starts_at" label="開始日時" type="datetime-local" />
            <flux:input wire:model="ends_at" label="終了日時" type="datetime-local" />
        </div>

        <div class="flex justify-between">
            <div class="flex gap-3">
                <flux:button wire:click="delete" wire:confirm="このイベントを削除しますか？" variant="danger" icon="trash">削除</flux:button>
                <flux:button wire:click="duplicate" wire:confirm="このイベントを複製しますか？" icon="document-duplicate">イベントを複製</flux:button>
            </div>
            <div class="flex gap-3">
                <flux:button :href="route('events.index')" variant="ghost" wire:navigate>キャンセル</flux:button>
                <flux:button type="submit" variant="primary">更新する</flux:button>
            </div>
        </div>
    </form>

    <flux:heading size="lg">券種</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>名前</flux:table.column>
            <flux:table.column>価格</flux:table.column>
            <flux:table.column>定員</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->ticketTypes as $ticketType)
                <flux:table.row wire:key="ticket-type-{{ $ticketType->id }}">
                    <flux:table.cell>{{ $ticketType->name }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($ticketType->price) }}円</flux:table.cell>
                    <flux:table.cell>{{ number_format($ticketType->capacity) }}名</flux:table.cell>
                    <flux:table.cell>
                        <flux:button wire:click="deleteTicketType({{ $ticketType->id }})" wire:confirm="この券種を削除しますか？" size="sm" variant="ghost" icon="trash" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">券種はまだありません。</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <form wire:submit="addTicketType" class="grid gap-4 sm:grid-cols-4 sm:items-end">
        <flux:input wire:model="ticketName" label="券種名" />
        <flux:input wire:model="ticketPrice" label="価格(円)" type="number" min="0" />
        <flux:input wire:model="ticketCapacity" label="定員" type="number" min="1" />
        <flux:button type="submit" icon="plus">追加</flux:button>
    </form>
</div>
