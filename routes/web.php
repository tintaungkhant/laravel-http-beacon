<?php

use Illuminate\Support\Facades\Route;

Route::get('/beacon', function () {
    return view('beacon::layout');
});
