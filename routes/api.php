<?php

use App\Http\Controllers\AdminFraudController;
use App\Http\Controllers\Api\CampaignOwner\AdvertSubmissionController;
use App\Http\Controllers\Api\CampaignOwner\CampaignController as CampaignOwnerCampaignController;
use App\Http\Controllers\Api\CampaignOwner\CampaignOwnerAuthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1'], function () {


    //Auth Routes
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/fcm-token', [NotificationController::class, 'updateFcmToken']);
        Route::post('/signup', [AuthController::class, 'signup']);
        Route::get('/location', [AuthController::class, 'getCountiesWithSubCounties']);
        Route::post('/all_account_activation', [AuthController::class, 'activateAllInactiveAcounts']);
        Route::post('/signin', [AuthController::class, 'signin']);
        Route::put('/logout', [AuthController::class, 'signOut']);
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




    Route::middleware(['auth:sanctum', 'check.active'])->group(function () {
        Route::group(['prefix' => 'campaign'], function () {
            Route::post('/', [ProductController::class, 'startCampaigns']);
            Route::put('/{id}', [ProductController::class, 'updateCampaign']);
            Route::get('/', [ProductController::class, 'getCampaigns']);
            Route::get('/advert/{campaignId}', [ProductController::class, 'getAdvertCampaigns']);
            Route::get('/fraud_advert/{campaignId}', [ProductController::class, 'getAdvertCampaignsFraud']);
            Route::put('/advert/{advertId}', [ProductController::class, 'updateAdvertProduct']);
            Route::post('/upload_product_advert/{campaignId}', [ProductController::class, 'uploadAdvertProducts']);
            Route::get('/get_product_advert', [ProductController::class, 'getAdvertProducts']);
            Route::post('/upload_screenshot/{advert_id}', [ProductController::class, 'uploadScreenShotPlusCompare']);
            Route::get('/dashboard/{userId}', [ProductController::class, 'getDashboardData']);
            Route::get('/admin_dashboard', [ProductController::class, 'getAdminDashboardData']);
        });
    });
    Route::group(['prefix' => 'campaign/report'], function () {
        Route::get('/campaign_report', [ProductController::class, 'getCampaignReports']);
        Route::get('/timely_campaign_report', [ProductController::class, 'getCampaignTimelyReports']);
        Route::get('/timely_individual_campaign_report', [ProductController::class, 'getCampaignTimelyPersionalReports']);
        Route::get('/excell_payment', [ProductController::class, 'getExcellFileForPayment']);
        Route::get('/timely_response', [ProductController::class, 'getCampaignTimelyPersional']);
    });

    Route::group(['prefix' => 'campaign'], function () {
        Route::post('/payment', [ProductController::class, 'uploadPaymentExcell']);
    });



    Route::middleware(['auth:sanctum', 'check.active'])->group(function () {
        //User Routes
        Route::group(['prefix' => 'user'], function () {
            Route::get('/activate', [AuthController::class, 'activateCard']);
            Route::get('/profile/{userId}', [AuthController::class, 'getUserProfileById']);
            Route::put('/deactivate', [AuthController::class, 'accountActivationCard']);
            Route::put('/assign_role', [AuthController::class, 'assignRole']);
            Route::put('/unassign_role', [AuthController::class, 'unAssignRole']);
            Route::get('/user_referals/{userId}', [AuthController::class, 'getAllUserReferred']);

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


        Route::prefix('notifications')->group(function () {
            Route::get('/user', [NotificationController::class, 'getUserNotifications']);
            Route::post('/mark-read', [NotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/delete', [NotificationController::class, 'deleteNotification']);
            Route::get('/stats', [NotificationController::class, 'getNotificationStats']);
        });


        Route::prefix('admin/fraud')->group(function () {
            Route::get('/campaign/{campaignId}', [AdminFraudController::class, 'getFraudForCampaign']);
            Route::get('/campaigns', [AdminFraudController::class, 'getFraudForAllCampaigns']);

            // Review actions
            Route::post('/review', [AdminFraudController::class, 'reviewFraudGroup']);
            Route::get('/guilty-users', [AdminFraudController::class, 'getGuiltyFraudUsers']);
            Route::post('/bulk-action', [AdminFraudController::class, 'bulkFraudAction']);

            // Optional history
            Route::get('/reviews', [AdminFraudController::class, 'listReviews']);
        });
    });


    Route::prefix('campaign-owner')
        ->group(function () {
            Route::post('/register', [CampaignOwnerAuthController::class, 'register']);
            Route::post('/campaign/submission', [AdvertSubmissionController::class, 'submit'])->middleware(['auth:sanctum', 'check.active', 'campaign_owner']);
            Route::get('/show/{userId}', [CampaignOwnerAuthController::class, 'show']);
            Route::get(
                '/{user_id}/advert-submissions',
                [AdvertSubmissionController::class, 'show']

            );
            Route::post('/list', [CampaignOwnerAuthController::class, 'campaignOwners'])->middleware(['auth:sanctum', 'check.active']);
        });
});
