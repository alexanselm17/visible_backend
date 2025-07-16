<?php
namespace App\Repositories\Setup;

use App\Models\Company;
use App\Models\Drum;
use App\Models\PetrolStation;
use App\Models\ProductsModel;
use App\Models\Pump;
use App\Models\Shift;
use App\Models\Stock;
use App\Repositories\Setup\SetupRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SetupRepository implements SetupRepositoryInterface
{
    public function registerCompany(Request $request){
        try {
            DB::beginTransaction();
            $company=Company::create([
                "name"=>$request->input("name")
             ]);
             $petrolStation = PetrolStation::create([
                'name' => "Dummy Petrol {$company->id}",
                'type' => 'NIOT',
                'company_id' => $company->id,
            ]);
            
            $product = ProductsModel::create([
                'name'=>"PETROLEUM",
                'category'=>"FUEL",
                'buying_price'=>0.00,
                'selling_price'=>0.00,
                'min_stock'=>0,
                'petrol_id'=>$petrolStation->id,
            ]);
            
            $tank=Drum::create([
                'name'=>"PETROLEUM TANK A",
                'product_id'=>$product->id,
                'is_on_shift'=>0,
                'petrol_id'=>$petrolStation->id,
            ]);
            
            $stock=Stock::create([
                'drum_id'=>$tank->id,
                'stock'=>10000,
                'product_id'=>$product->id,
                'petrol_id'=>$petrolStation->id,
            ]);
            
            $shift=Shift::create([
                'petrol_id'=>$petrolStation->id,
                'description' => "Dummy Shift",
            ]);
            $pump=Pump::create([
                'name'=>"PETROLEUM PUMP A1",
                'curr_volume'=>0,
                'curr_cash'=>0,
                'drum_id'=>$tank->id,
                'is_on_shift'=>0,
                'petrol_id'=>$petrolStation->id,
            ]);
            DB::commit();

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => "Company created successfully",
                'data' => $company,
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::debug('Sign Up Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }
    public function updateCompany(Request $request, $id){
        try {
             // Find the company by ID
             $company = Company::findOrFail($id);
            
             // Update the company attributes
             $company->update([
                 'name' => $request->input('name'),
             ]);
 
             return response()->json([
                 'ok' => true,
                 'status' => 'success',
                 'message' => 'Company updated successfully',
                 'company' => $company
             ]);
        } catch (\Throwable $th) {
            Log::error('Company Update Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'An error occurred while updating the company'
            ], 500);
        }
    }
    public function registerPetrolStation(Request $request,$companyId){
        try {
            DB::beginTransaction();
            $petrolStation=PetrolStation::create([
                'name' => $request->input('name'), 
                'type'=> $request->input('type'), 
                'company_id'=>$companyId
            ]);
          
            $product = ProductsModel::create([
                'name'=>"Dummy Product {$petrolStation->id}",
                'category'=>"FUEL",
                'buying_price'=>0.00,
                'selling_price'=>0.00,
                'min_stock'=>0,
                'petrol_id'=>$petrolStation->id,
            ]);
            $tank=Drum::create([
                'name'=>"Dummy Tanks {$product->id}",
                'product_id'=>$product->id,
                'is_on_shift'=>0,
                'petrol_id'=>$petrolStation->id,
            ]);
            $stock=Stock::create([
                'drum_id'=>$tank->id,
                'stock'=>10000,
                'product_id'=>$product->id,
                'petrol_id'=>$petrolStation->id,
            ]);
            $shift=Shift::create([
                'petrol_id'=>$petrolStation->id,
                'description' => "Dummy Shift",
            ]);
            $pump=Pump::create([
                'name'=>"Dummy Pump ",
                'curr_volume'=>0,
                'curr_cash'=>0,
                'drum_id'=>$tank->id,
                'is_on_shift'=>0,
                'petrol_id'=>$petrolStation->id,
            ]);
            DB::commit();
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Petrol Station Created successfully',
                'company' => $petrolStation
            ]);
        } catch (\Throwable $th) {
            Log::error('Company Creating Error: ' . $th->getMessage());
            DB::rollBack();
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'An error occurred while creating the company: ' . $th->getMessage(),
            ], 500);
            
        }
    }
    public function updatePetrolStation(Request $request, $petrolStationId){
        try {
            $petrolStation = PetrolStation::findOrFail($petrolStationId);
            $petrolStation->update([
                'name' => $request->input('name'),
                'type' => $request->input('type')
            ]);

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Petrol station updated successfully',
                'petrolStation' => $petrolStation
            ]);

        } catch (\Throwable $th) {
            Log::error('Petrol Station Update Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'An error occurred while updating the petrol station'
            ], 500);
        }
    }

    public function getPetrolStation($companyId){
        try {
            $company = Company::findOrFail($companyId);

            $petrolStations = $company->petrolStations()->paginate(10);

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'data' => $petrolStations
            ]);
        } catch (\Throwable $th) {
            Log::error('Petrol Station Update Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'An error occurred while fetching the petrol station'
            ], 500);
        }
    }

}