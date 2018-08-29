<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\Facade\Trace as ZipkinTrace;
class Trace
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        ZipkinTrace::makeTrace();
        sleep(1);
        list($time,$id) = ZipkinTrace::addSpan();
        sleep(1);
        ZipkinTrace::addSpanEnd($time,$id,'agent');
        sleep(1);
        ZipkinTrace::endTrace();
        return $next($request);
    }
}
