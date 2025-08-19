<?php

namespace App\Pipelines\FinePenalty;

use Closure;

class SearchHoldingNo
{
    public function handle($query, Closure $next)
    {
        if (request()->filled('holdingNo')) {
            $query->where('holding_no', request()->holdingNo);
        }
        return $next($query);
    }
}
