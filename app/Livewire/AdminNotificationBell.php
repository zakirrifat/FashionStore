<?php

namespace App\Livewire;

use Filament\Livewire\DatabaseNotifications;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\View\View;

/** The admin bell, which turns red while there are unread alerts. */
class AdminNotificationBell extends DatabaseNotifications
{
    #[Unexposed]
    public function getTrigger(): ?View
    {
        return view('filament.admin-notification-bell');
    }
}
