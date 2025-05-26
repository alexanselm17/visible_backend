<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SetupController;
use App\Http\Middleware\CheckActiveUser;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1'], function () {
    //Auth Routes
    Route::group(['prefix' => 'auth'], function () {

          //Admin and manager only
          Route::post('/signup', [AuthController::class, 'signup']);

        Route::post('/signin', [AuthController::class, 'signin']);
        Route::put('/logout', [AuthController::class, 'signOut']);

    });
        Route::group(['prefix' => 'stock'], function () {
            Route::put('/reconcile_stock', [SalesController::class, 'reconcileStock']);
            Route::get('/', [SalesController::class, 'getAllProductStock']);
        });

Route::get('/download/advert/{path}', function ($path) {
    $fullPath = public_path("storage/" . $path);

    if (!file_exists($fullPath)) {
        return response()->json(['error' => 'File not found.'], 404);
    }

    $filename = basename($path);
    $headers = [
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        'Content-Transfer-Encoding' => 'binary',
        'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        'Expires' => '0',
        'Pragma' => 'public',
    ];

    return response()->make(file_get_contents($fullPath), 200, $headers);
})->where('path', '.*')->name('download.advert.image');




    Route::group(['prefix' => 'user'], function () {

        Route::post('/assign_permissions', [AuthController::class, 'assignPermissionsToUser']);

        Route::get('/user_permissions/{userId}', [AuthController::class, 'getUserPermissions']);

        Route::put('/reset_password', [AuthController::class, 'restorePassword']);

    });

    Route::group(['prefix' => 'sale_status'], function () {
        Route::post('/iot/check_sales_status', [SalesController::class, 'isValidToSell']);
    });
    Route::group(['prefix' => 'sales'], function () {
        Route::post('/iot', [SalesController::class, 'recordSalesIOT']);
    });

    Route::group(['prefix' => 'setup'], function () {

        Route::post('/company', [SetupController::class, 'registerCompany']);

        Route::put('/company/{id}', [SetupController::class, 'updateCompany']);
        Route::post('/company/petrol_station/{companyId}', [SetupController::class, 'registerPetrolStation']);
        Route::put('/company/petrol_station/{petrolStationId}', [SetupController::class, 'updatePetrolStation']);
        Route::get('/company/petrol_station/{companyId}', [SetupController::class, 'getPetrolStation']);
 });

 Route::middleware(['auth:sanctum', 'check.active'])->group(function () {

    Route::group(['prefix' => 'campaign'], function () {
        Route::post('/', [ProductController::class, 'startCampaigns']);
        Route::get('/', [ProductController::class, 'getCampaigns']);
        Route::get('/advert/{campaignId}', [ProductController::class, 'getAdvertCampaigns']);
        Route::post('/upload_product_advert/{campaignId}', [ProductController::class, 'uploadAdvertProducts']);
        Route::get('/get_product_advert', [ProductController::class, 'getAdvertProducts']);
        Route::post('/upload_screenshot/{advert_id}', [ProductController::class, 'uploadScreenShotPlusCompare']);
    
     });
 });




    Route::middleware(['auth:sanctum', 'check.active'])->group(function () {

        //User Routes
        Route::group(['prefix' => 'user'], function () {

        //Admin and manager only
            Route::get('/activate', [AuthController::class, 'activateCard']);
            Route::put('/deactivate', [AuthController::class, 'accountActivationCard']);
            Route::put('/assign_role', [AuthController::class, 'assignRole']);
            Route::put('/unassign_role', [AuthController::class, 'unAssignRole']);

        Route::get('/get_Roles', [AuthController::class, 'getRoles']);
           // Route::put('/assign_role', [AuthController::class, 'assignRole']);
            Route::get('/search', [AuthController::class, 'searchUser']);
          //  Route::put('/unassign_role', [AuthController::class, 'unAssignRole']);
            Route::get('/', [AuthController::class, 'getAllUsers']);
            Route::get('/user_without_role', [AuthController::class, 'getAllUsersWithoutRole']);
            Route::put('/', [AuthController::class, 'updateProfile']);

        });

        Route::group(['prefix' => 'product'], function () {

            //Admin and manager only
                Route::post('/upload_product_advert', [ProductController::class, 'uploadAdvertProducts']);
                Route::post('/', [ProductController::class, 'createProduct']);
                Route::post('/{masterProductId}', [ProductController::class, 'createChildProduct']);
                Route::put('/{productId}', [ProductController::class, 'updateProduct']);

            Route::get('/', [ProductController::class, 'getProducts']);
            Route::get('/search', [ProductController::class, 'searchProducts']);
        });








        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('/{userId}/{roleId}/{companyId}', [SalesController::class, 'dashboardData']);
            Route::get('/statistic', [SalesController::class, 'statisticData'])->name('dashboard.statistic');
        });

        Route::group(['prefix' => 'sale'], function () {

            Route::post('/invoice/{shiftId}', [SalesController::class, 'recordCreditSales']);
            Route::post('/', [SalesController::class, 'recordSales']);
            Route::get('/', [SalesController::class, 'getLast10SalesReceipts']);
            Route::put('/return_sales/{transactionId}', [SalesController::class, 'salesReturn']);
            Route::get('/{shiftId}/{userId}', [SalesController::class, 'getLastFewTransactions']);
        });

        Route::group(['prefix' => 'customers'], function () {


           Route::post('/{petrolStationId}', [CustomersController::class, 'createCustomer']);
                Route::post('reconcile/{customerId}', [SalesController::class, 'reconcileCustomer']);
              Route::post('repayment/{shiftId}/{customerId}', [SalesController::class, 'customerRepayment']);
                Route::put('/{id}', [CustomersController::class, 'updateCustomer']);
                Route::post('customer_discount/{customerId}', [SalesController::class, 'discountCustomer']);




            Route::get('/{petrolStationId}', [CustomersController::class, 'fetchCustomers']);
            Route::get('/{petrolStationId}/search', [CustomersController::class, 'searchCustomers']);

        });
        Route::group(['prefix' => 'activity'], function () {
            Route::post('/purchases', [SalesController::class, 'recordPurchases']);

            Route::post('/expenses', [SalesController::class, 'recordExpenses']);
            Route::get('/get_unapproved_expenses', [SalesController::class, 'getUnapprovedExpenses']);
            //this is admin routes only




            Route::post('/{petrolStationId}', [SalesController::class, 'startShift']);
            Route::get('/drum/{petrolStationId}/{shiftId}', [SalesController::class, 'getDrums']);
            Route::get('pump/{shiftId}/{userId}/{petrolStation}', [SalesController::class, 'getPumpAssignment']);

            Route::get('/drums/session/{shiftId}/{petrolId}', [SalesController::class, 'fetchDrumsShiftSession']);
            Route::post('/drums/record_dips/{shiftId}/{petrolId}/{drumId}', [SalesController::class, 'recordDips']);

            Route::put('/pumps/reverse', [SalesController::class, 'reversePump']);

            //Discounts
            Route::post('/discounts/{shiftId}', [SalesController::class, 'recordShiftDiscount']);
            Route::get('/get_unapproved_discounts/{petrolStationId}/{shiftId}', [SalesController::class, 'getUnapprovedShiftDiscount']);
            Route::put('/approve_discounts', [SalesController::class, 'approveDiscounts']);
            //Discounts

            Route::get('/{petrolStationId}', [SalesController::class, 'getShifts']);
            Route::get('/unapprovedInvoices/{petrolStationId}/{shiftId}', [SalesController::class, 'getUnapprovedCustomerInvoices']);
            Route::post('/assignStation/{petrolStationId}/{shiftId}', [SalesController::class, 'assignStation']);
            Route::put('/transfer', [SalesController::class, 'approveTransfer']);
            Route::post('/transfer/{shiftId}', [SalesController::class, 'stationTransfers']);
            Route::get('/transfer/{station}/{shiftId}', [SalesController::class, 'getAllUnApprovedTransfer']);

            Route::put('/endshift/{shiftId}', [SalesController::class, 'endShift']);

                Route::put('/approve_expenses', [SalesController::class, 'approveExpenses']);
                Route::put('/cashierApprovals/{userId}', [SalesController::class, 'cashierApprovals']);
                Route::post('/session/{petrolStationId}/{shiftId}', [SalesController::class, 'drumSession']);

            //let's add a middle ware to ensure no  salesman does the approvals
                Route::put('/invoices_approval', [SalesController::class, 'approveInvoices']);


            Route::get('/session/single_stations', [SalesController::class, 'getStationSessionDetails']);
            //NIOT


            //IOT
            // Route::post('/session/iot/{petrolStationId}/{shiftId}', [SalesController::class, 'drumSessionIOT']);
            Route::post('/station/start_session/{petrolStationId}/{shiftId}', [SalesController::class, 'startStationSession']);

            Route::get('station/search_products', [SalesController::class, 'searchProductStation']);

            Route::get('/unapprovedCashierTransaction/{petrolStationId}/{shiftId}', [SalesController::class, 'getUnApprovedCashierTransactions']);
            Route::get('/unapprovedBankedTransaction/{petrolStationId}', [SalesController::class, 'getUnApprovedBankedTransactions']);
            // get all bankings for that shift
            Route::get('bankings', [SalesController::class, 'fetchShiftBanking']);

            Route::post('/banking/{petrolStationId}', [SalesController::class, 'recordBankings']);
            Route::post('/banking/reset_transactions', [SalesController::class, 'resetBankings']);
            Route::post('/autobanking', [SalesController::class, 'recordBankingsAutomatic']);
            Route::get('/constants/{category}', [SalesController::class, 'fetchPaymentMethods']);
            Route::get('/station/product_not_sesstion/{shiftId}/{station}', [SalesController::class, 'getStationsProductsNotInSession']);
            Route::get('/station/product_opening_stock/{shiftId}/{station}', [SalesController::class, 'getStationsProductsInSession']);
            Route::get('/station_assignment/{petrolStationId}/{userId}/{shiftId}', [SalesController::class, 'getStationAssignment']);
        });
    });
    Route::group(['prefix' => 'report'], function () {
        Route::get('/sale_summary/{petrolStationId}/{shiftId}', [SalesController::class, 'generateSalesReport']);
        Route::get('/personal_sale_summary', [SalesController::class, 'generatePersonalSalesReport']);
        Route::get('/customer', [SalesController::class, 'generateCustomerReport']);
        Route::get('/stock/{petrolStationId}', [SalesController::class, 'generateStockReport']);
        Route::get('/sales-report/download/{shiftId}/{petrolStationId}', [SalesController::class, 'downloadSalesReport'])->name('download.sales_report');
        Route::get('/customer_report/download', [SalesController::class, 'downloadCustomerReport'])->name('download.customer_report');
        Route::get('/personal_periodic_sales_report', [SalesController::class, 'periodicSalesmanReport']);
        Route::get('/general_periodic_sales_report', [SalesController::class, 'periodicGeneralReport']);
        Route::get('/daily_report', [SalesController::class, 'dailyReport']);

    });
});
