<?php

namespace Modules\Tasks\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Media\Exceptions\MediaUploadValidationException;
use Modules\Tasks\Http\Requests\UploadTaskAttachmentRequest;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Services\TaskAttachmentService;

class TaskAttachmentController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskAttachmentService $attachments) {}

    public function store(UploadTaskAttachmentRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('uploadAttachment', $task);
        try {
            $this->attachments->uploadMany($task, $request->user(), $request->file('media'));
        } catch (MediaUploadValidationException $exception) {
            throw ValidationException::withMessages(['media' => [$exception->getMessage()]]);
        }

        return back()->with('success', 'Attachment uploaded.');
    }

    public function download(Task $task, TaskAttachment $attachment)
    {
        abort_unless($attachment->task_id === $task->id, 404);
        $this->authorize('view', $task);

        return $this->attachments->download($task, $attachment, request()->user());
    }

    public function preview(Task $task, TaskAttachment $attachment)
    {
        abort_unless($attachment->task_id === $task->id, 404);
        $this->authorize('view', $task);

        return $this->attachments->preview($task, $attachment, request()->user());
    }

    public function destroy(Task $task, TaskAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->task_id === $task->id, 404);
        $this->authorize('deleteAttachment', [$task, $attachment]);
        $this->attachments->delete($task, $attachment, request()->user());

        return back()->with('success', 'Attachment deleted.');
    }
}
