<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SupportTicket;

class SupportTicketCreated extends Notification implements ShouldQueue
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
            ->subject('New Support Ticket Created - #' . $this->ticket->id)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A new support ticket has been created.')
            ->line('**Ticket Details:**')
            ->line('Title: ' . $this->ticket->title)
            ->line('Priority: ' . ucfirst($this->ticket->priority))
            ->line('Department: ' . $this->ticket->ticketDepartment->name)
            ->line('Created by: ' . $this->ticket->createdBy->name)
            ->line('Company: ' . $this->ticket->company->name)
            ->action('View Ticket', $url)
            ->line('Please review and respond as soon as possible.');
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
            'priority' => $this->ticket->priority,
            'created_by' => $this->ticket->createdBy->name,
        ];
    }
}
