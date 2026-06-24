<?php

use App\Http\Controllers\PrintInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/print/invoice/{type}/{id}', PrintInvoiceController::class)
    ->name('invoice.print')
    ->middleware(['web', 'auth']);
