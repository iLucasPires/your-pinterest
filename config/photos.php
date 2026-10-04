<?php

return [
    'disk' => env('FILESYSTEM_DISK', 'local'),
    'thumbnail_width' => (int) env('PHOTO_THUMBNAIL_WIDTH', 500),
    'preview_width' => (int) env('PHOTO_PREVIEW_WIDTH', 1920),
    'quality' => (int) env('PHOTO_WEBP_QUALITY', 82),
];
