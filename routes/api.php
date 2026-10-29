<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/customers/orders', [OrderController::class, 'history']);
Route::get('/customers/lookup', [OrderController::class, 'lookup']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/catalog', [ProductController::class, 'catalog']);
Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
