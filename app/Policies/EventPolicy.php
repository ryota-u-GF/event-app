<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * イベントを作ったユーザーだけが編集できる。
     */
    public function update(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }
}
