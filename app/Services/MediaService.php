<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;

class MediaService
{
    public function register(User $user, string $filename, string $diskName, string $mimeType, int $size): Media
    {
        return Media::createOrFirst(
            ['disk_name' => $diskName],
            [
                'user_id' => $user->id,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'size' => $size,
            ]
        );
    }
}
