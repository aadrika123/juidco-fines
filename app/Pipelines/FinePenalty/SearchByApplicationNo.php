<?php

namespace App\Pipelines\FinePenalty;

use Closure;

class SearchByApplicationNo
{
    public function handle($request, Closure $next)
    {
        if (!request()->has('applicationNo')) {
            return $next($request);
        }
        return $next($request)
            ->where('application_no', 'ilike', '%' . request()->input('applicationNo') . '%');
    }
}
