<?php

namespace App\Pipelines\Rig;

use Closure;

class SearchByMobileNo
{
    public function handle($request, Closure $next)
    {
        if (!request()->has('mobile')) {
            return $next($request);
        }
        return $next($request)
            ->where('mobile', 'ilike', '%' . request()->input('mobile') . '%');
    }
}
