<?php

use App\Http\Controllers\ProductController;
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

Route::get('/timely_individual_campaign_report', [ProductController::class, 'getCampaignTimelyPersionalReports'])->name('user.report');
Route::get('/campaign_report', [ProductController::class, 'getCampaignReports'])->name('campaign_report');
Route::get('/timely_campaign_report', [ProductController::class, 'getCampaignTimelyReports'])->name('timely_campaign_report');
