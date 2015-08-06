<?php

namespace PointerBa\Bundle;

use App\User;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

/**
 * Class AdminController
 * @package PointerBa\Bundle
 */
abstract class AdminController extends Controller
{
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * @var User
     *
     * currently active user
     */
    protected $user;

    /**
     * @var bool
     *
     * sets if a repo should be used
     */
    protected $noRepo = false;

    /**
     * @var null
     *
     * repo class name
     */
    protected $repoClassName = null;

    /**
     * @var Repository
     *
     * derived class repository, null if does not exist
     */
    protected $repo;

    /**
     * Sets local values
     */
    public function __construct ()
    {
        $this->user = \Auth::user();
        \View::share('currentUser', $this->user ?: new User);

        if (!$this->noRepo)
        {

            $repoClass = 'App\\Repositories\\';

            if ($this->repoClassName)
                $repoClass .= $this->repoClassName;

            else
            {

                $derivedClassName = class_basename(get_called_class());

                $this->repoClassName = str_replace('Controller', '', $derivedClassName) . 'Repository';

                $repoClass .=  $this->repoClassName;
            }

            if (class_exists($repoClass))
            {
                $this->repo = new $repoClass;
                $this->repo->onlyVisible(false);
            }
        }
    }

}