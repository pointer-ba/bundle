<?php

namespace PointerBa\Bundle;

use App\Http\Controllers\Controller;
use App\Repositories\Repository;
use Illuminate\Support\Facades\Auth;
use App\User;
use Illuminate\Support\Facades\View;

abstract class AdminController extends Controller {

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
     * @var Repository|null
     *
     * derived class repository, null if does not exist
     */

    protected $repo = null;

    /**
     * Sets local values
     */

    public function __construct ()
    {
        $this->user = Auth::user();
        View::share('currentUser', $this->user ?: new User);

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