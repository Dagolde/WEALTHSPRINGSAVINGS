<?php

namespace App\Notifications;

use App\Notifications\Channels\PushNotificationChannel;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Contribution Reminder Notification
 * Sent to remind users to make their daily contribution
 */
class ContributionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    
    private array $groupData;
    
    /**
     * Create a new notification instance
     *
     * @param array $groupData
     */
    public function __construct(array $groupData)
    {
        $this->groupData = $groupData;
    }
    
    /**
     * Get the notification's delivery channels
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return [PushNotificationChannel::class, SmsChannel::class, 'mail'];
    }
    
    /**
     * Get the push notification representation
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toPush($notifiable): array
    {
        return [
            'title' => 'Contribution Reminder',
            'body' => "Please make your daily contribution of ₦{$this->groupData['amount']} for {$this->groupData['name']}.",
            'data' => [
                'type' => 'contribution_reminder',
                'group_id' => $this->groupData['id'],
                'amount' => $this->groupData['amount'],
            ],
        ];
    }
    
    /**
     * Get the SMS representation
     *
     * @param mixed $notifiable
     * @return string
     */
    public function toSms($notifiable): string
    {
        return "Reminder: Please make your daily contribution of ₦{$this->groupData['amount']} for {$this->groupData['name']}.";
    }
    
    /**
     * Get the mail representation
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Contribution Reminder')
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a reminder to make your daily contribution for **{$this->groupData['name']}**.")
            ->line("**Amount:** ₦{$this->groupData['amount']}")
            ->line("**Due Date:** {$this->groupData['due_date']}")
            ->line('Please ensure you contribute before the deadline to avoid penalties.')
            ->action('Make Contribution', url('/contributions'));
    }
}
