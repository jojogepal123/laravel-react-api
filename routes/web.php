<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return ['Laravel' => app()->version()];
});
use HlrLookup\HLRRESTClient;


require __DIR__ . '/auth.php';
