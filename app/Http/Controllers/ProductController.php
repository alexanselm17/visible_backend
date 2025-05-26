<?php

namespace App\Http\Controllers;
use App\Repositories\Products\ProductRepositoryInterface;
use Illuminate\Http\Request;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\UpdateProductRequest ;
use App\Http\Requests\CreateDrumRequest;
use App\Http\Requests\UpdateDrumRequest;
use App\Http\Requests\CreatePumpRequest;
use App\Http\Requests\CreatesStationRequest;
use App\Http\Requests\ProductAdvertRequest;
use App\Http\Requests\StartCampaignRequest;
use App\Http\Requests\UpdatePumpRequest;

class ProductController extends Controller
{
    private ProductRepositoryInterface $productRepository;
    public function __construct(ProductRepositoryInterface $productRepository){
        $this->middleware(['auth:api', 'permission:products_operations'])->only([
            'createProduct',
            'createChildProduct',
            'updateProduct',
        ]);
        $this->middleware(['auth:api', 'permission:basic_setup_operations'])->only([
            'createDrum',
            'updateDrum',
        ]);
        $this->middleware(['auth:api', 'permission:basic_setup_operations'])->only([
            'createPump',
            'updatePump',
        ]);
        $this->middleware(['auth:api', 'permission:basic_setup_operations'])->only([
            'uploadAdvertProducts',
            'uploadScreenShotPlusCompare',
        ]);
        $this->productRepository = $productRepository;
    }
    public function createProduct(ProductRequest $request){
        return $this->productRepository->createProduct($request);
    }

    public function createChildProduct(Request $request,$masterProductId){
        return $this->productRepository->createChildProduct($request,$masterProductId);
    }

    public function updateProduct(UpdateProductRequest $request, $productId){

        return $this->productRepository->updateProduct($request, $productId);
    }
    public function getProducts(){
        return $this->productRepository->getProducts();
    }
    
    public function getAdvertCampaigns(Request $request,$campaignId){
        return $this->productRepository->getAdvertCampaigns($request,$campaignId);
    }
     public function searchProducts(Request $request){
        return $this->productRepository->searchProducts($request);
    }

    public function startCampaigns(StartCampaignRequest $request){
        return $this->productRepository->startCampaigns($request);
    }
     public function getCampaigns(Request $request){
        return $this->productRepository->getCampaigns($request);
    }

    public function uploadAdvertProducts(ProductAdvertRequest $request,$campaignId){
        return $this->productRepository->uploadAdvertProducts($request,$campaignId);
    }

    public function getAdvertProducts(Request $request){
        return $this->productRepository->getAdvertProducts($request);
    }


    public function uploadScreenShotPlusCompare(Request $request,$advert_id){
        return $this->productRepository->uploadScreenShotPlusCompare($request,$advert_id);
    }







}
