<?php

use App\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
  return view('welcome');
});

// routes/web.php
Route::get('/sales-report/{petrolStationId}/{shiftId}', [SalesController::class, 'generateSalesReport'])
  ->name('reports.sales_report');
Route::get('/personal_sale_summary', [SalesController::class, 'generatePersonalSalesReport'])->name('reports.personal_sales_report');
Route::get('/customer', [SalesController::class, 'generateCustomerReport'])->name('reports.customer_report');
Route::get('/stock/{petrolStationId}', [SalesController::class, 'generateStockReport'])->name('reports.stock_report');

Route::get('/personal_periodic_sales_report', [SalesController::class, 'periodicSalesmanReport'])->name('reports.timely_personal_report');
Route::get('/general_periodic_sales_report', [SalesController::class, 'periodicGeneralReport'])->name('reports.timely_report');
Route::get('/daily_report', [SalesController::class, 'dailyReport'])->name('reports.daily_report');
