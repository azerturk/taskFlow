@props(['task', 'attachment'])

<div class="flex shrink-0 gap-3">
    @if(str_starts_with($attachment->media->mime_type, 'image/') || $attachment->media->mime_type === 'application/pdf')
        <a href="{{ route('tasks.media.preview', [$task, $attachment]) }}" data-media-preview data-preview-name="{{ $attachment->media->original_name }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">Preview</a>
    @endif
    <a href="{{ route('tasks.media.download', [$task, $attachment]) }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">Download</a>
    @can('deleteAttachment', [$task, $attachment])
        <form method="POST" action="{{ route('tasks.media.destroy', [$task, $attachment]) }}" data-confirm="Delete this attachment?">@csrf @method('DELETE')<button type="submit" class="text-sm font-semibold text-rose-700 hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500">Delete</button></form>
    @endcan
</div>
