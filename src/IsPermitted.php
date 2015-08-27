<?php

 namespace App\PointerBa\Bundle;

use Closure;

class IsPermitted
{
    protected function failed ($request)
    {
        return $request->ajax() 
            ? response('Unauthorized.', 401)
            : redirect()->guest('auth/login');
    }

    public function handle($request, Closure $next)
    {
        if (!$user = \Auth::user())
            return $this->failed($request);

        $routeName = $request->route()->getUri();
        $routePieces = explode('/', $routeName);

        $finalPermission = strtolower($request->method()) . "/" . $routeName;
        $permissionSet = [];

        for ($i = 0; $i < count($routePieces); $i++)
            $permissionSet[] = $i == 0 ? $routePieces[$i] : $permissionSet[$i - 1] . "/" . $routePieces[$i];

        $permissionSet[] = $finalPermission;

        return $user->can($permissionSet)
            ? $next($request);
            : $this->failed($request);
    }
}