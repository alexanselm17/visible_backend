<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductAdvertRequest;
use App\Http\Requests\RepostAdvertRequest;
use App\Http\Requests\StartCampaignRequest;
use App\Repositories\Products\ProductRepositoryInterface;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {

        $this->middleware(['auth:api', 'permission:advert_operations'])->only([
            'updateAdvertProduct',
            'uploadAdvertProducts',
        ]);
        $this->middleware(['auth:api', 'permission:campaign_operations'])->only([
            'startCampaigns',
            'updateCampaign',
        ]);

        $this->middleware(['auth:api', 'permission:payment_operations'])->only([
            'uploadPaymentExcell',
        ]);

        $this->productRepository = $productRepository;
    }

    public function startCampaigns(StartCampaignRequest $request)
    {
        return $this->productRepository->startCampaigns($request);
    }

    public function getCampaigns(Request $request)
    {
        return $this->productRepository->getCampaigns($request);
    }

    public function updateCampaign(Request $request, $id)
    {
        return $this->productRepository->updateCampaign($request, $id);
    }

    public function uploadAdvertProducts(ProductAdvertRequest $request, $campaignId)
    {
        return $this->productRepository->uploadAdvertProducts($request, $campaignId);
    }


    public function repostAdvertProducts(RepostAdvertRequest $request, $advertId)
    {
        return $this->productRepository->repostAdvertProducts($request, $advertId);
    }

    public function getAdvertProducts(Request $request)
    {
        return $this->productRepository->getAdvertProducts($request);
    }

    public function uploadScreenShotPlusCompare(Request $request, $advert_id)
    {
        return $this->productRepository->uploadScreenShotPlusCompare($request, $advert_id);
    }

    public function getDashboardData(Request $request, $userId)
    {
        return $this->productRepository->getDashboardData($request, $userId);
    }

    public function getAdminDashboardData(Request $request)
    {
        return $this->productRepository->getAdminDashboardData($request);
    }

    public function getCampaignReports(Request $request)
    {
        return $this->productRepository->getCampaignReports($request);
    }

    public function getCampaignTimelyReports(Request $request)
    {
        return $this->productRepository->getCampaignTimelyReports($request);
    }

    public function getCampaignTimelyPersionalReports(Request $request)
    {
        return $this->productRepository->getCampaignTimelyPersionalReports($request);
    }

    public function getCampaignTimelyPersional(Request $request)
    {
        return $this->productRepository->getCampaignTimelyPersional($request);
    }

    public function getExcellFileForPayment(Request $request)
    {
        return $this->productRepository->getExcellFileForPayment($request);
    }

    // public function updateAdvertProduct(ProductAdvertRequest $request, $advertId){
    //     return $this->productRepository->updateAdvertProduct($request,$advertId);
    // }

    public function getAdvertCampaignsFraud(Request $request, $campaignId)
    {
        return $this->productRepository->getAdvertCampaignsFraud($request, $campaignId);
    }

    public function uploadPaymentExcell(Request $request)
    {
        return $this->productRepository->uploadPaymentExcell($request);
    }

    public function getAdvertCampaigns(Request $request, $campaignId)
    {
        return $this->productRepository->getAdvertCampaigns($request, $campaignId);
    }
}
