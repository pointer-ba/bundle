<?php

namespace PointerBa\Bundle;


trait AuthoredTrait {

    /**
     * @param Builder $query
     *
     * the record belongs to a user (author)
     */

    public function author()
    {
        return $this->belongsTo('App\User', 'author_id');
    }

}