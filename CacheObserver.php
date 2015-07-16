<?php

namespace PointerBa\Bundle;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheObserver {

    /**
     * @var bool
     *
     * sets if cache clearing is enabled
     */
    public static $clearCache = true;

    /**
     * @var
     *
     * model instance that was observed
     */
    protected $model;

    /**
     * @var
     *
     * event registered
     */
    protected $method;

    /**
     * if clearing cache is enabled, clear cache
     */
    private function _clearCache()
    {
        if (static::$clearCache === true)
            $this->clearCache();
    }

    /**
     * default clear cache method (clears all cache)
     */
    protected function clearCache()
    {
        Cache::flush();
    }

    /**
     * @param $model
     *
     * fires when a model is saved
     */
    public function saved($model)
    {
        $this->model = $model;
        $this->method = 'saved';

        $this->_clearCache();
    }

    /**
     * @param $model
     *
     * fires when a model is deleted
     */
    public function deleted($model)
    {
        $this->model = $model;
        $this->method = 'deleted';

        $this->_clearCache();
    }

}