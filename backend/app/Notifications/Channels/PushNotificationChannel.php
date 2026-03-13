<?php

namespace App\Notifications\Channels;

use App\Services\NotificationService;
use Illuminate\Notifications\Notification;

/**
 * Push Notification Channel
 * Sends notifications via FastAPI push notification service
 */
class PushNotificationChannel
{
    private NotificationService $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    /**
     * Send the given notification
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return void
     */
    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toPush')) {
            return;
        }
        
        $data = $notification->toPush($notifiable);
        
        $this->notificationService->sendPush(
            $notifiable,
            $data['title'] ?? 'Notification',
            $data['body'] ?? '',
            $data['data'] ?? []
        );
    }
}
