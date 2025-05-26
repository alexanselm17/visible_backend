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

    public function createChildProduct(Request $request,$masterProductId);
    public function updateProduct(UpdateProductRequest $request, $productId);
    public function getProducts();
    public function searchProducts(Request $request);


    public function startCampaigns(StartCampaignRequest $request);
    public function uploadAdvertProducts(ProductAdvertRequest $request,$campaignId);
    public function getAdvertProducts(Request $request);
    public function uploadScreenShotPlusCompare(Request $request,$advert_id);
    public function getCampaigns(Request $request);
    public function getAdvertCampaigns(Request $request,$campaignId);


}