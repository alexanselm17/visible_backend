<?php
 namespace App\Repositories\Setup;
 use Illuminate\Http\Request;

 interface SetupRepositoryInterface{
    public function registerCompany(Request $request);
    public function updateCompany(Request $request, $id);
    public function registerPetrolStation(Request $request,$companyId);
    public function updatePetrolStation(Request $request, $petrolStationId);
    public function getPetrolStation($companyId);
 }