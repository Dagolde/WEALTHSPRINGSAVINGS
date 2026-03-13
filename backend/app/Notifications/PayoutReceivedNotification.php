<?php

namespace App\Notifications;

use App\Notifications\Channels\PushNotificationChannel;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Payout Received Notification
 * Sent when a user receives their payout
 */
class PayoutReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    
    private float $amount;
    private string $groupName;
    
    /**
     * Create a new notification instance
     *
     * @param float $amount
     * @param string $groupName
     */
    public function __construct(float $amount, string $groupName)
    {
        $this->amount = $amount;
        $this->groupName = $groupName;
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
            'title' => '🎉 Payout Received!',
            'body' => "Your payout of ₦{$this->amount} from {$this->groupName} has been credited to your wallet.",
            'data' => [
                'type' => 'payout_received',
                'amount' => $this->amount,
                'group_name' => $this->groupName,
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
        return "Great news! Your payout of ₦{$this->amount} from {$this->groupName} has been credited to your wallet.";
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
            ->subject('Payout Received')
            ->greeting("Hello {$notifiable->name},")
            ->line('Great news! Your payout has been processed successfully.')
            ->line("**Group:** {$this->groupName}")
            ->line("**Amount:** ₦" . number_format($this->amount, 2))
            ->line("**Date:** " . now()->toDateString())
            ->line('The funds have been credited to your wallet.')
            ->action('View Wallet', url('/wallet'));
    }
}
