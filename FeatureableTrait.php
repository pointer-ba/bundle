<?php

namespace PointerBa\Bundle;

use Illuminate\Database\Eloquent\Builder;

trait FeatureableTrait {

    public static $IS_FEATURED = [
        1 => 'Da',
        0 => 'Ne'
    ];

    /**
     * @param Builder $query
     *
     * get only published records
     */

    public function scopeFeatured(Builder $query)
    {
        $query->where('is_featured', '=', 1);
    }

}