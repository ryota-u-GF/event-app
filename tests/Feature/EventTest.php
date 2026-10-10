<?php

use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

// ---- 登録 ----

test('ログイン済みユーザーはイベントを登録できる', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::events.create')
        ->set('title', '朝焼けトレッキング')
        ->set('description', '岩手山に登ります。')
        ->set('venue', '岩手山 馬返し登山口')
        ->set('starts_at', '2026-11-01T05:00')
        ->set('ends_at', '2026-11-01T09:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    $this->assertDatabaseHas('events', [
        'user_id' => $user->id,
        'title' => '朝焼けトレッキング',
        'venue' => '岩手山 馬返し登山口',
    ]);
});

test('ゲストはイベント登録画面を開けずログインへ誘導される', function () {
    $this->get(route('events.create'))->assertRedirect(route('login'));
});

test('イベント登録の入力が不正だと登録できない', function (array $overrides, string $errorKey) {
    $user = User::factory()->create();

    $input = array_merge([
        'title' => 'タイトル',
        'description' => '説明',
        'venue' => '会場',
        'starts_at' => '2026-11-01T10:00',
        'ends_at' => '2026-11-01T12:00',
    ], $overrides);

    $component = Livewire::actingAs($user)->test('pages::events.create');
    foreach ($input as $key => $value) {
        $component->set($key, $value);
    }
    $component->call('save')->assertHasErrors($errorKey);

    expect(Event::count())->toBe(0);
})->with([
    'タイトルが空' => [['title' => ''], 'title'],
    'タイトルが101文字' => [['title' => str_repeat('あ', 101)], 'title'],
    '説明が空' => [['description' => ''], 'description'],
    '説明が2001文字' => [['description' => str_repeat('あ', 2001)], 'description'],
    '会場が空' => [['venue' => ''], 'venue'],
    '会場が101文字' => [['venue' => str_repeat('あ', 101)], 'venue'],
    '開始日時が空' => [['starts_at' => ''], 'starts_at'],
    '開始日時が日付でない' => [['starts_at' => 'あした'], 'starts_at'],
    '終了日時が開始より前' => [['ends_at' => '2026-11-01T09:00'], 'ends_at'],
    '終了日時が開始と同じ' => [['ends_at' => '2026-11-01T10:00'], 'ends_at'],
]);

// ---- 編集 ----

test('主催者はイベントを編集できる', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->set('title', '更新後のタイトル')
        ->set('venue', '更新後の会場')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'title' => '更新後のタイトル',
        'venue' => '更新後の会場',
    ]);
});

test('他人のイベントは編集画面を開けない', function () {
    $event = Event::factory()->create(['title' => '元のタイトル']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::events.edit', ['event' => $event])
        ->assertForbidden();

    $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => '元のタイトル']);
});

test('ゲストはイベント編集画面を開けずログインへ誘導される', function () {
    $event = Event::factory()->create();

    $this->get(route('events.edit', $event))->assertRedirect(route('login'));
});

test('イベント編集の入力が不正だと更新できない', function (string $field, string $value) {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id, 'title' => '元のタイトル']);

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field);

    $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => '元のタイトル']);
})->with([
    'タイトルが空' => ['title', ''],
    'タイトルが101文字' => ['title', str_repeat('あ', 101)],
    '説明が空' => ['description', ''],
    '会場が空' => ['venue', ''],
    '開始日時が日付でない' => ['starts_at', 'あした'],
    '終了日時が開始より前' => ['ends_at', '2000-01-01T00:00'],
]);

// ---- 削除 ----

test('主催者はイベントを削除できる', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->call('delete')
        ->assertRedirect(route('events.index'));

    $this->assertModelMissing($event);
});

test('他人のイベントは削除できない', function () {
    $event = Event::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::events.edit', ['event' => $event])
        ->assertForbidden();

    $this->assertModelExists($event);
});

// ---- 複製 ----

test('主催者はイベントを複製できる', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'user_id' => $user->id,
        'title' => '朝焼けトレッキング',
        'starts_at' => '2026-11-01 05:00:00',
        'ends_at' => '2026-11-01 09:00:00',
    ]);

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->call('duplicate');

    $copy = Event::where('id', '!=', $event->id)->sole();

    expect($copy->title)->toBe('朝焼けトレッキング（複製）')
        ->and($copy->user_id)->toBe($user->id)
        ->and($copy->description)->toBe($event->description)
        ->and($copy->venue)->toBe($event->venue)
        ->and($copy->starts_at->toDateTimeString())->toBe('2026-11-08 05:00:00')
        ->and($copy->ends_at->toDateTimeString())->toBe('2026-11-08 09:00:00')
        ->and($event->fresh()->starts_at->toDateTimeString())->toBe('2026-11-01 05:00:00')
        ->and($event->fresh()->ends_at->toDateTimeString())->toBe('2026-11-01 09:00:00')
        ->and($event->fresh()->title)->toBe('朝焼けトレッキング');
});

test('複製すると複製先の編集画面へ移動する', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->call('duplicate');

    $copy = Event::where('id', '!=', $event->id)->sole();

    $component->assertRedirect(route('events.edit', $copy));
});

test('イベントを複製しても券種はコピーされない', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $event->ticketTypes()->create(['name' => '一般', 'price' => 3000, 'capacity' => 50]);

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->call('duplicate');

    $copy = Event::where('id', '!=', $event->id)->sole();

    expect($copy->ticketTypes()->count())->toBe(0)
        ->and($event->ticketTypes()->count())->toBe(1);
});

test('複製後のタイトルは100文字を超えないよう元のタイトルが切り詰められる', function (int $originalLength, int $expectedKeptLength) {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id, 'title' => str_repeat('あ', $originalLength)]);

    Livewire::actingAs($user)
        ->test('pages::events.edit', ['event' => $event])
        ->call('duplicate');

    $copy = Event::where('id', '!=', $event->id)->sole();

    expect($copy->title)->toBe(str_repeat('あ', $expectedKeptLength).'（複製）');
})->with([
    '100文字は96文字に切られる' => [100, 96],
    '96文字はそのまま' => [96, 96],
    '50文字はそのまま' => [50, 50],
]);

test('他人のイベントは複製できない', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);

    $component = Livewire::actingAs($owner)
        ->test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component->call('duplicate')->assertForbidden();

    expect(Event::count())->toBe(1);
});

// ---- 一覧 ----

test('一覧では自分のイベントにだけ編集ボタンが出る', function () {
    $user = User::factory()->create();
    $mine = Event::factory()->create(['user_id' => $user->id]);
    $others = Event::factory()->create();

    $this->actingAs($user)
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee(route('events.edit', $mine), false)
        ->assertDontSee(route('events.edit', $others), false);
});

test('ゲストの一覧には編集ボタンも登録ボタンも出ない', function () {
    $event = Event::factory()->create();

    $this->get(route('events.index'))
        ->assertOk()
        ->assertSee($event->title)
        ->assertDontSee(route('events.edit', $event), false)
        ->assertDontSee(route('events.create'), false);
});
