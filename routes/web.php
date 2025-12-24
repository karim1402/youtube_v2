<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // dd("here");
    return view('welcome');
});
