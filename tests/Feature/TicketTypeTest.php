<?php

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

test('主催者は編集画面で券種を追加できる', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->set('ticketName', '一般')
        ->set('ticketPrice', '3000')
        ->set('ticketCapacity', '20')
        ->call('addTicketType')
        ->assertHasNoErrors()
        ->assertSee('一般')
        ->assertSee('3,000円');

    $this->assertDatabaseHas('ticket_types', [
        'event_id' => $event->id,
        'name' => '一般',
        'price' => 3000,
        'capacity' => 20,
    ]);
});

test('他人のイベントの編集画面は開けない', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('events.edit', $event))
        ->assertForbidden();
});

test('券種の入力が不正だと追加できない', function (string $name, string $price, string $capacity, string $errorKey) {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->set('ticketName', $name)
        ->set('ticketPrice', $price)
        ->set('ticketCapacity', $capacity)
        ->call('addTicketType')
        ->assertHasErrors($errorKey);

    expect(TicketType::count())->toBe(0);
})->with([
    '名前が空' => ['', '1000', '10', 'ticketName'],
    '名前が51文字' => [str_repeat('あ', 51), '1000', '10', 'ticketName'],
    '価格がマイナス' => ['一般', '-1', '10', 'ticketPrice'],
    '定員が0' => ['一般', '1000', '0', 'ticketCapacity'],
]);

test('主催者は券種を削除できる', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $ticketType = TicketType::factory()->create(['event_id' => $event->id]);

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->call('deleteTicketType', $ticketType->id);

    $this->assertModelMissing($ticketType);
});

test('他のイベントの券種は削除できない', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $other = TicketType::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->call('deleteTicketType', $other->id)
        ->assertNotFound();

    $this->assertModelExists($other);
});

test('イベントを削除すると券種も削除される', function () {
    $ticketType = TicketType::factory()->create();

    $ticketType->event->delete();

    $this->assertModelMissing($ticketType);
});

test('券種の名前は50文字まで追加できる', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->set('ticketName', str_repeat('あ', 50))
        ->set('ticketPrice', '1000')
        ->set('ticketCapacity', '10')
        ->call('addTicketType')
        ->assertHasNoErrors();

    expect($event->ticketTypes()->count())->toBe(1);
});

test('イベント一覧に最安の券種の価格が表示される', function (array $prices, string $expected) {
    $event = Event::factory()->create();
    foreach ($prices as $price) {
        TicketType::factory()->create(['event_id' => $event->id, 'price' => $price]);
    }

    $this->get(route('events.index'))->assertSee($expected);
})->with([
    '最安が1,500円' => [[3000, 1500], '1,500円〜'],
    '最安が0円' => [[0, 2000], '無料'],
    '券種なし' => [[], '─'],
]);
