<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Auth\AuthManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the people a new task was assigned to: Brevo email plus the in-app and
 * phone notification.
 *
 * Queued by TaskController::store(). These are one HTTP call per person and per
 * device, so running them inside the request made "Save" wait on every one of
 * them. The task is already saved when this runs; a failure here cannot undo or
 * delay it.
 */
class NotifyTaskAssigned implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * @param int[] $assignedUserIds  everyone assigned (the actor is filtered out downstream)
     * @param int[] $mailUserIds      who gets the email (assignees other than the actor)
     */
    public function __construct(
        public int $taskId,
        public int $actorId,
        public string $actorRole,
        public array $assignedUserIds,
        public array $mailUserIds,
    ) {}

    public function handle(AuthManager $auth): void
    {
        $task = Task::find($this->taskId);
        $actor = User::find($this->actorId);
        if (!$task || !$actor) {
            return;
        }

        // NotificationService names the actor from the logged-in user, and a
        // queue worker has none.
        $auth->setUser($actor);

        foreach (User::whereIn('id', $this->mailUserIds)->get() as $user) {
            sendBrevoMail(
                $user->email,
                $user->name,
                'New Task Assigned: ' . $task->title,
                "You have been assigned a new task: <strong>{$task->title}</strong><br><br>Click <a href='"
                    . route('role.tasks.board', ['role' => $this->actorRole, 'board' => $task->board_id])
                    . "'>here</a> to view the task details."
            );
        }

        NotificationService::notifyTaskCreated($task, $this->assignedUserIds);
    }
}
