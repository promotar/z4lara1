<?php

namespace Modules\Store\Domain\Media;

enum MediaRole: string
{
    case Cover = 'cover';
    case Gallery = 'gallery';
    case Thumbnail = 'thumbnail';
    case Swatch = 'swatch';
}
