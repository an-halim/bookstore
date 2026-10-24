<?php

use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LoanController;
use Illuminate\Support\Facades\Route;

Route::apiResource('categories', CategoryController::class);
Route::apiResource('books', BookController::class);
Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
Route::post('loans', [LoanController::class, 'store'])->name('loans.store');
Route::get('loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
Route::post('loans/{loan}/return', [LoanController::class, 'return'])->name('loans.return');
