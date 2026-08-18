<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;

class SupportTicketReplied extends Notification implements ShouldQueue
{
    use Queueable;

    protected $ticket;
    protected $reply;

    /**
     * Create a new notification instance.
     */
    public function __construct(SupportTicket $ticket, SupportTicketReply $reply)
    {
         $this->ticket = $ticket;
         $this->reply = $reply;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Build URL - use 'customer' role for customers, otherwise use actual user role
        $role = ($notifiable instanceof \App\Models\Customer) 
            ? 'customer' 
            : strtolower($notifiable->getRoleNames()->first());
            
        $url = route('role.support-tickets.show', [
            'role' => $role,
            'support_ticket' => $this->ticket->id
        ]);

        return (new MailMessage)
            ->subject('New Reply on Ticket #' . $this->ticket->id . ' - ' . $this->ticket->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A new reply has been added to your support ticket.')
            ->line('**Ticket:** ' . $this->ticket->title)
            ->line('**Replied by:** ' . $this->reply->user->name)
            ->line('**Reply:**')
            ->line(substr($this->reply->content, 0, 200) . (strlen($this->reply->content) > 200 ? '...' : ''))
            ->action('View Full Conversation', $url)
            ->line('Please check the ticket for complete details.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'reply_by' => $this->reply->user->name,
            'reply_excerpt' => substr($this->reply->content, 0, 100),
        ];
    }
}
