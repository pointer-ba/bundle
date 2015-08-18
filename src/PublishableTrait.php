<?php

namespace PointerBa\Bundle;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait PublishableTrait {

    public static $IS_PUBLISHED = [
        0 => 'Ne',
        1 => 'Da'
    ];

    /**
     * @param Builder $query
     *
     * get only published records
     */

    public function scopePublished(Builder $query)
    {
        $query->where('is_published', '=', 1)
            ->where('published_at', '<=', Carbon::now());
    }

}