<?php

namespace App\Repositories\Products;

use App\Http\Requests\ProductAdvertRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\StartCampaignRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\Request;

interface ProductRepositoryInterface
{
    public function createProduct(ProductRequest $request);

    public function createChildProduct(Request $request, $masterProductId);
    public function updateProduct(UpdateProductRequest $request, $productId);
    public function getProducts();
    public function searchProducts(Request $request);


    public function startCampaigns(StartCampaignRequest $request);
    public function uploadAdvertProducts(ProductAdvertRequest $request, $campaignId);
    public function getAdvertProducts(Request $request);
    public function uploadScreenShotPlusCompare(Request $request, $advert_id);
    public function getCampaigns(Request $request);
    public function getAdvertCampaigns(Request $request, $campaignId);

    public function getAdvertCampaignsFraud(Request $request, $campaignId);

    public function getDashboardData(Request $request, $userId);

    public function getAdminDashboardData(Request $request);

    public function getCampaignReports(Request $request);
    public function getCampaignTimelyReports(Request $request);

    public function getCampaignTimelyPersionalReports(Request $request);

    public function getCampaignTimelyPersional(Request $request);

    public function getExcellFileForPayment(Request $request);

    public function updateCampaign(Request $request, $id);

    public function updateAdvertProduct(ProductAdvertRequest $request, $advertId);
}
