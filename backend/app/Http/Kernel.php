<?php

namespace App\Http;

use Illuminate\Base\Http\Kernel as BaseHttpKernel;

class Kernel extends BaseHttpKernel
{
    protected $middlewareAliases = [
        'cors' => \App\Http\Middleware\HandleCors::class,
    ];
}