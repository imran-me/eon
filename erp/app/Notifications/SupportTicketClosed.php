<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SupportTicket;

class SupportTicketClosed extends Notification implements ShouldQueue
{
    use Queueable;

    protected $ticket;

    /**
     * Create a new notification instance.
     */
    public function __construct(SupportTicket $ticket)
    {
        $this->ticket = $ticket;
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
            ->subject('Support Ticket Closed - #' . $this->ticket->id . ' - ' . $this->ticket->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your support ticket has been closed.')
            ->line('**Ticket Details:**')
            ->line('Title: ' . $this->ticket->title)
            ->line('Priority: ' . ucfirst($this->ticket->priority))
            ->line('Department: ' . $this->ticket->ticketDepartment->name)
            ->line('Company: ' . $this->ticket->company->name)
            ->action('View Ticket', $url)
            ->line('If you need further assistance, please create a new ticket or reply to reopen this one.')
            ->line('Thank you for using our support system!');
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
            'status' => $this->ticket->status,
        ];
    }
}
