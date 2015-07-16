<?php

namespace PointerBa\Bundle;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait PublishableTrait {

    public static $IS_PUBLISHED = [
        1 => 'Da',
        0 => 'Ne'
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