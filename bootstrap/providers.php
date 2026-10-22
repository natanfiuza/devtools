<?php

use App\Providers\AiServiceProvider;
use App\Providers\ApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PaymentsServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AiServiceProvider::class,
    ApiServiceProvider::class,
    PaymentsServiceProvider::class,
];
