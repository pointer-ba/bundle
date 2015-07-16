<?php

namespace PointerBa\Bundle\IsPermitted;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Route;

class IsPermitted {

    protected $auth;

    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }
    
    protected function failed ($request)
    {
        return $request->ajax() ? response('Unauthorized.', 401)
                                : redirect()->guest('auth/login');
    }

    public function handle($request, Closure $next)
    {
        if ($this->auth->guest())
            return $this->failed($request);

        $user = $this->auth->user();

        $routeName = $request->route()->getUri();
        $routePieces = explode('/', $routeName);

        $finalPermission = strtolower($request->method()) . "/" . $routeName;
        $permissionSet = [];

        for ($i = 0; $i < count($routePieces); $i++)
            $permissionSet[] = $i == 0 ? $routePieces[$i] : $permissionSet[$i - 1] . "/" . $routePieces[$i];

        $permissionSet[] = $finalPermission;

        if (!$user->can($permissionSet))
            return $this->failed($request);

        return $next($request);
    }

}