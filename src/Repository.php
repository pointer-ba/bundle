<?php

namespace PointerBa\Bundle;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\Paginator;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

abstract class Repository {

    /**
     * folder where all repo keys are stored
     */
    const BASE_KEY_STORE_PATH = '../storage/repositories/';

    /**
     * @var bool
     *
     * sets if only visible results should be relevant
     */
    protected $onlyVisible = true;

    /**
     * @var bool
     *
     * sets if only the author's records should be retrieved
     */
    protected $onlyAuthored = false;

    /**
     * @var
     *
     * path of key store
     */
    protected $keyStorePath;

    /**
     * @var
     *
     * class basename
     */
    protected $baseName;

    /**
     * @var string
     *
     * full class name of model for derived class
     */
    protected $modelClass;

    /**
     * @var bool
     *
     * true if the result set should be paginated, false otherwise
     */
    protected $paginate = false;

    /**
     * @var int
     *
     * maximum items per page
     */
    protected $perPage = 15;

    /**
     * @var bool
     *
     * true if cache should be used, false otherwise
     */
    protected $cache = true;

    /**
     * @var Builder
     *
     * Eloquent instance for querying
     */
    private $model;

    /**
     * @var int | null
     *
     * minutes for cache storage (null represents infinity)
     */
    protected $cacheTime = null;

    /**
     * @var string
     *
     * key for cache check, determined when a call is made from derived classes
     */
    private $cacheKey;

    /**
     * @var Closure
     *
     * optional extra querying for non-custom queries
     */
    private $closure = null;

    /**
     * @var null
     *
     * limit to the number of results
     */
    private $limit = null;

    /**
     * @var null
     *
     * Filters for querying
     */
    private $filters = [];

    /**
     * @var
     *
     * additional key ending (for additional closures)
     */
    private $keyFragment = null;

    /**
     * @var null
     *
     * used for overriding the default model identifier for finding a record
     */
    private $identifier = 'id';

    /**
     * @var bool
     *
     * determines whether results should not be fetched in a future repository usage
     */
    protected $appendExcludes = false;

    /**
     * @var array
     *
     * set of identifiers to be excluded from query
     */
    protected $excludes = [];

    /**
     * sets basename of class
     */
    public function __construct()
    {
        $this->baseName = class_basename($this);

        $this->keyStorePath = static::BASE_KEY_STORE_PATH . $this->baseName;
    }


    /**
     * global find filter, can be used to apply global rules in derived classes
     */
    protected function prepare(Builder $model) {}

    /**
     * filters only visible instances, can be used to apply global visibility rules in derived classes
     */
    protected function filterVisible(Builder $model) {}

    /**
     * custom filtering based on $filters array that can be set via setFilter($filters) expected to be implemented in derived classes
     */
    protected function filter(Builder $model, array $filters) {}

    /**
     * filter sets where the current user's records are taken into account
     */
    protected function filterAuthored(Builder $model)
    {
        $user = Auth::user();

        $model->where('author_id', '=',  $user ? $user->id : null);
    }

    /**
     * method expected to optionally filter for fetching all records in derived class
     */
    protected function all() {}

    /**
     * @param $content
     *
     * stores into cache if the Repository::$cache is set to true
     */
    public static function clearCache()
    {
        $path = static::BASE_KEY_STORE_PATH . class_basename(get_called_class());

        if (!File::exists($path))
            return;

        foreach(file($path) as $line)
            Cache::forget(trim($line));

        File::put($path, '');
    }


    /**
     * @param $content
     *
     * stores content to cache based on cache key
     */
    public function storeToCache($content)
    {
        if ($content === null)
            $content = 'null';

        if ($this->cache && $this->cacheKey)
        {
            if ($this->cacheTime > 0)
                Cache::put($this->cacheKey, $content, $this->cacheTime);

            else
                Cache::forever($this->cacheKey, $content);

            if (!File::exists($this->keyStorePath))
                File::put($this->keyStorePath, $this->cacheKey . "\n");

            else
                File::append($this->keyStorePath, $this->cacheKey . "\n");
        }
    }

    /**
     * @param $method
     * @param $params
     * @return Collection
     *
     * sets cache key if derived class method call
     * derived classes methods (like 'featured') are to be used as 'getFeatured'
     */
    public function __call($method, $parameters)
    {
        $realMethod = lcfirst(substr($method, 3));

        if (!method_exists($this, $realMethod))
            throw new \Exception("Method does not exist - " . $method);

        if ($this->cache)
        {
            $this->cacheKey = ($this->onlyVisible ? '-' : '+')
                . ($this->onlyAuthored ? '-' : '+')
                . "{$realMethod}[" . serialize($parameters) .  "]" . "\{" . serialize($this->filters) . "\}" . "<" . implode(',', $this->excludes) .  ">";

            if ($this->limit)
                $this->cacheKey .= "|{$this->limit}|";

            if ($this->paginate)
                $this->cacheKey .= 'p=' . (Paginator::resolveCurrentPage() ?: 1) . "&pp=" . $this->perPage;

            if ($this->closure)
                $this->cacheKey .= "{{$this->keyFragment}}";

            if ($cache = Cache::get($this->cacheKey))
            {
                $this->clearFilters();

                if ($this->appendExcludes)
                {
                    $this->excludes += $cache->lists($this->identifier)->all();

                    $this->appendExcludes = false;
                }

                return $cache === 'null' ? null : $cache;
            }
        }

        $this->model = (new $this->modelClass);

        $this->model = $this->model->whereNotIn($this->model->getTable() . '.' . $this->identifier, $this->excludes);

        $this->prepare($this->model);
        $this->filter($this->model, $this->filters);

        if ($this->onlyVisible)
            $this->filterVisible($this->model);

        if ($this->onlyAuthored)
            $this->filterAuthored($this->model);

        if ($this->limit)
            $this->model->take($this->limit);

        array_unshift($parameters, $this->model);

        call_user_func_array(array($this, $realMethod), $parameters);

        if ($this->closure)
        {
            $closure = $this->closure;

            $closure($this->model);
        }

        $result = $this->get();

        if ($this->appendExcludes)
        {
            $this->excludes += $result->lists($this->identifier)->all();

            $this->appendExcludes = false;
        }

        $this->clearFilters();

        return $result;
    }

    /**
     * @param callable $closure
     * @param $keyFragment
     * @return $this
     *
     * additional querying on some method
     */
    public function closure(Closure $closure, $keyFragment)
    {
        $this->closure = $closure;
        $this->keyFragment = $keyFragment;

        return $this;
    }


    /**
     * @return Collection
     *
     * method expected to be called (optionally) after each real repository call
     * also sets cache if cache is enabled
     */
    private function get()
    {
        $result = $this->paginate ? $this->model->paginate($this->perPage) : $this->model->get();
        $this->storeToCache($result);

        return $result;
    }

    /**
     * @param $id
     * @param bool $orFail
     * @return Model|null
     *
     * method attempts to find record with given id
     */
    public function find($id, $orFail = false)
    {
        if ($this->cache)
        {
            $this->cacheKey = ($this->onlyVisible ? '-' : '+')
                . ($this->onlyAuthored ? '-' : '+')
                . "{$this->baseName}.find[{$id}]";

            if ($cache = Cache::get($this->cacheKey))
            {
                $this->clearFilters();
                return $cache === 'null' ? null : $cache;
            }
        }

        $this->model = (new $this->modelClass)->where(DB::raw(1), '=', 1);

        $this->prepare($this->model);

        if ($this->onlyVisible)
            $this->filterVisible($this->model);

        if ($this->onlyAuthored)
            $this->filterAuthored($this->model);

        $this->filter($this->model, $this->filters);

        $this->model->where($this->identifier, '=', $id);

        $result = $orFail ? $this->model->firstOrFail()
            : $this->model->first();

        $this->storeToCache($result);

        $this->clearFilters();

        return $result;
    }

    /**
     * @param $id
     * @return Model|null
     * method attempts ot find record with given id, throws exception otherwise
     */
    public function findOrFail($id)
    {
        return $this->find($id, true);
    }

    /**
     * @param callable $closure
     * @param null $cacheKey
     * @param bool $orFail
     * @return mixed
     *
     * allows for custom repository querying for finding a single record
     */
    public function customFind(Closure $closure, $cacheKey = null, $orFail = false)
    {
        if ($this->cache)
        {
            $this->cacheKey = "~[{$cacheKey}]";

            if ($cache = Cache::get($this->cacheKey))
                return $cache === 'null' ? null : $cache;
        }

        $this->model = (new $this->modelClass)->where(DB::raw(1), '=', 1);

        $closure($this->model);

        $result = $orFail ? $this->model->firstOrFail() : $this->model->first();

        $this->storeToCache($result);

        return $result;
    }

    /**
     * @param callable $closure
     * @param null $cacheKey
     * @return mixed
     *
     * method attempts to find record with given id, throws exception otherwise
     */
    public function customFindOrFail(Closure $closure, $cacheKey = null)
    {
        return $this->customFind($closure, $cacheKey, true);
    }

    /**
     * @param callable $closure
     * @param null $cacheKey
     * @return mixed
     *
     * allows for custom repository querying with optional caching
     */
    public function customGet(Closure $closure, $cacheKey = null)
    {
        $this->cacheKey = $cacheKey;

        if ($this->cacheKey && $this->cache)
        {
            $this->cacheKey = "~[{$this->cacheKey}]";

            if ($this->paginate)
                $this->cacheKey .= 'p=' . (Paginator::resolveCurrentPage() ?: 1) . "&pp=" . $this->perPage;

            if ($cache = Cache::get($this->cacheKey))
                return $cache === 'null' ? null : $cache;
        }

        $this->model = (new $this->modelClass)->where(DB::raw(1), '=', 1);

        $closure($this->model);

        $result = $this->model->get();

        $this->storeToCache($result);

        return $result;
    }

    /**
     * @param $limit
     *
     * Sets the limit for result set
     */
    public function limit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Clears the limit for the result set
     */
    public function clearLimit()
    {
        $this->limit = null;
    }

    /**
     * @param array $filters
     *
     * Sets the filters for querying
     */
    public function filters(array $filters = [])
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * Clears all filters
     */
    public function clearFilters()
    {
        $this->filters = [];

        return $this;
    }

    /**
     * @param $paginate
     * @return mixed
     *
     * sets whether results should be paginated or not
     */
    public function paginate($paginate = true)
    {
        $this->paginate = $paginate;

        return $this;
    }

    /**
     * @param null $paginate
     * @return $this|null
     *
     * sets or gets the identifier for finding a record
     */
    public function identifier($identifier = null)
    {
        if ($identifier !== null)
        {
            $this->identifier = $identifier;

            return $this;
        }

        return $this->identifier;
    }

    /**
     * @param $cache
     *
     * sets or gets if cache should be used
     */
    public function cache($cache = null)
    {
        if ($cache !== null)
        {
            $this->cache = $cache;

            return $this;
        }

        return $this->cache;
    }

    /**
     * @param null $onlyVisible
     * @return $this|bool
     *
     * sets or gets if only visible records should be returned
     */
    public function onlyVisible($onlyVisible = null)
    {
        if ($onlyVisible !== null)
        {
            $this->onlyVisible = $onlyVisible;

            return $this;
        }

        return $this->onlyVisible;
    }

    /**
     * @param null $onlyVisible
     * @return $this|bool
     *
     * sets or gets if only visible records should be returned
     */
    public function onlyAuthored($onlyAuthored = null)
    {
        if ($onlyAuthored !== null)
        {
            $this->onlyAuthored = $onlyAuthored;

            return $this;
        }

        return $this->onlyAuthored;
    }

    /**
     * @param $perPage
     *
     * set maximum amount of items per page
     */
    public function perPage($perPage = null)
    {
        if ($perPage !== null)
        {
            $this->perPage = $perPage;

            return $this;
        }

        return $this->perPage;
    }

    /**
     * @param $cacheTime
     *
     * set or get maximum cache time
     */
    public function cacheTime($cacheTime = -1)
    {
        if ($cacheTime !== -1)
        {
            $this->cacheTime = $cacheTime;

            return $this;
        }

        return $this->cacheTime;
    }

    /**
     * @param null $appendExcludes
     * @return $this
     *
     * sets whether the result identifiers should be appended to future excludes
     */
    public function appendExcludes($appendExcludes = true)
    {
        $this->appendExcludes = $appendExcludes;

        return $this;
    }

    /**
     * @param array $excludes
     *
     * adds to the array of identifier exclusions
     */
    public function addExcludes(array $excludes = [])
    {
        $this->excludes += $excludes;

        return $this;
    }

    /**
     * @return string
     *
     * gets last used cache key
     */
    public function lastCacheKey()
    {
        return $this->cacheKey;
    }

    /**
     * @return array
     *
     * gets current identifier excludes
     */
    public function getExcludes()
    {
        return $this->excludes;
    }

    /**
     * @return array
     *
     * default array of field population for creation
     */
    protected function createDefaults()
    {
        return [
            'author_id' => Auth::user() ? Auth::user()->id : null
        ];
    }

    /**
     * @param $data
     * @return Model
     *
     * creates a new record and returns the model instance
     */
    public function create($data)
    {
        $class = $this->modelClass;

        return $class::create($data + $this->createDefaults());
    }

    /**
     * @return array
     *
     * default array of field population for updates
     */
    protected function updateDefaults()
    {
        return [];
    }

    /**
     * @param $id
     * @param $data
     * @param bool $orFail
     * @return mixed
     *
     * updates an existing record
     */
    public function update($id, $data, $orFail = false)
    {
        if ($id instanceof Model)
            $this->model = $id;

        else
        {
            $this->model = (new $this->modelClass)
                ->where('id', '=', $id);

            $this->model->first();

            $this->model = $orFail ? $this->model->firstOrFail()
                : $this->model->first();

            if (!$this->model)
                return false;
        }

        $this->model->fill($data + $this->updateDefaults())
            ->save();

        return $this->model;
    }

    /**
     * @param $id
     * @param $data
     * @return Model
     *
     * updates existing record or fails if not found
     */
    public function updateOrFail($id, $data)
    {
        return $this->update($id, $data, true);
    }

    /**
     * @param $id
     * @return mixed
     *
     * deletes an existing record or set of records
     */
    public function destroy($id)
    {
        $class = $this->modelClass;

        return $class::destroy($id);
    }

}