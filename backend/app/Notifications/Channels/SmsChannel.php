<?php

namespace App\Notifications\Channels;

use App\Services\NotificationService;
use Illuminate\Notifications\Notification;

/**
 * SMS Channel
 * Sends notifications via FastAPI SMS service
 */
class SmsChannel
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
        if (!method_exists($notification, 'toSms')) {
            return;
        }
        
        $message = $notification->toSms($notifiable);
        
        $this->notificationService->sendSMS($notifiable, $message);
    }
}
