<?php

namespace App\Models\Concerns;

use App\Support\Img;

/**
 * Makes the resized WebP copies of a model's photos as soon as it is saved
 * (after the admin's response is sent), so the first shop visitor never waits
 * for the resizer — a large photo can take several seconds.
 *
 * The model lists its photo paths in resizableImages().
 */
trait WarmsImages
{
    /** @return array<int, string|null> storage paths of this record's photos */
    abstract public function resizableImages(): array;

    public static function bootWarmsImages(): void
    {
        static::saved(function ($model) {
            Img::warmLater($model->resizableImages());
        });
    }
}
