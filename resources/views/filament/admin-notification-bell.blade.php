@php
    use Filament\Support\Icons\Heroicon;
    use Filament\View\PanelsIconAlias;
    use Illuminate\Support\Number;

    $hasUnread = $unreadNotificationsCount > 0;
@endphp

<x-filament::icon-button
    :badge="$unreadNotificationsCount ?: null"
    badge-color="danger"
    :color="$hasUnread ? 'danger' : 'gray'"
    :icon="$hasUnread ? Heroicon::Bell : Heroicon::OutlinedBell"
    :icon-alias="PanelsIconAlias::TOPBAR_OPEN_DATABASE_NOTIFICATIONS_BUTTON"
    icon-size="lg"
    :label="
        $hasUnread
        ? trans_choice('filament-panels::layout.actions.open_database_notifications.label_with_unread_count', $unreadNotificationsCount, ['count' => Number::format($unreadNotificationsCount, locale: app()->getLocale())])
        : __('filament-panels::layout.actions.open_database_notifications.label')
    "
    @class(['fi-topbar-database-notifications-btn', 'admin-bell-unread' => $hasUnread])
/>
