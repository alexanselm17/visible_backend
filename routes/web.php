<?php

use App\Http\Controllers\LegalPageController;
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

// Stable, anonymous HTML URLs for app settings and store-listing disclosures.
Route::get('/privacy-policy', [LegalPageController::class, 'show'])->defaults('slug', 'privacy-policy')->name('legal.privacy');
Route::get('/terms-and-conditions', [LegalPageController::class, 'show'])->defaults('slug', 'terms-and-conditions')->name('legal.terms');
Route::redirect('/privacy', '/privacy-policy', 301);
Route::redirect('/terms', '/terms-and-conditions', 301);
Route::get('/delete-account', [LegalPageController::class, 'requestForm'])->name('legal.delete-account');
Route::get('/privacy-request', [LegalPageController::class, 'requestForm'])->name('legal.privacy-request');
Route::post('/privacy-request', [LegalPageController::class, 'storeRequest'])->middleware('throttle:5,1')->name('legal.request.store');
Route::get('/legal/preview/{document}', [LegalPageController::class, 'preview'])->name('legal.preview');

Route::get('/timely_individual_campaign_report', [ProductController::class, 'getCampaignTimelyPersionalReports'])->name('user.report');
Route::get('/campaign_report', [ProductController::class, 'getCampaignReports'])->name('campaign_report');
Route::get('/timely_campaign_report', [ProductController::class, 'getCampaignTimelyReports'])->name('timely_campaign_report');
