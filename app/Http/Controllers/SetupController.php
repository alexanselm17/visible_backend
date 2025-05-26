<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company;
use App\Http\Requests\PetrolStation;
use App\Http\Requests\UpdateCompany;
use App\Http\Requests\UpdatePetrolStation;
use App\Repositories\Setup\SetupRepositoryInterface;


class SetupController extends Controller
{
    private SetupRepositoryInterface $setupRepository;
    public function __construct(SetupRepositoryInterface $setupRepository)
    {
        $this->middleware(['auth:api', 'permission:company_setup'])->only([
            'registerCompany',
            'updateCompany',
            'registerPetrolStation',
            'updatePetrolStation',

        ]);
        $this->setupRepository = $setupRepository;
    }

    public function registerCompany(Company $request){
        return $this->setupRepository->registerCompany($request);
    }
    public function updateCompany(UpdateCompany $request,$id){
        return $this->setupRepository->updateCompany($request,$id);
    }
    public function registerPetrolStation(PetrolStation $request,$companyId){
        return $this->setupRepository->registerPetrolStation($request,$companyId);
    }
    public function updatePetrolStation(UpdatePetrolStation $request,$petrolStationId){
        return $this->setupRepository->updatePetrolStation($request,$petrolStationId);
    }
    public function getPetrolStation( $companyId){
        return $this->setupRepository->getPetrolStation($companyId);
    }
}
