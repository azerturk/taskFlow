<?php

namespace Modules\Tasks\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Modules\Media\Support\MediaFilePolicy;

class AllowedTaskAttachmentFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $mimeType = strtolower($value->getMimeType() ?: 'application/octet-stream');

        if (! MediaFilePolicy::accepts($extension, $mimeType)) {
            $fail('The :attribute content does not match the uploaded file type.');
        }
    }
}
