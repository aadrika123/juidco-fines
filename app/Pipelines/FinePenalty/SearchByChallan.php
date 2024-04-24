<?php

namespace App\Pipelines\FinePenalty;

use Closure;


class SearchByChallan
{
    public function handle($request, Closure $next)
    {
        if (!request()->has('challanNo')) {
            return $next($request);
        }
        return $next($request)
            ->where('challan_no', 'ilike', '%' . request()->input('challanNo') . '%');
    }
}
