<?php

namespace App\Repositories\Customers;

use App\Models\Customers;
use App\Http\Requests\CustomersRequest;
use App\Http\Requests\CustomerUpdateRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Repositories\Customers\CustomerRepositoryInterface;

class CustomerRepository implements CustomerRepositoryInterface
{
  
    public function createCustomer(CustomersRequest $request,$petrolStationId){
        try {
         //  DD($petrolStationId);
            $customer = Customers::create([
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'petrol_id'=>$petrolStationId
            ]);

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => "Customer created successfully",
                'data' => $customer,
            ]);

        } catch (\Throwable $th) {
            Log::debug('Sign Up Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }

 
    public function updateCustomer(CustomerUpdateRequest $request, String $id){
        try {
            $customer = Customers::findOrFail($id);

            $customer->update([
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
            ]);

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => "Customer updated successfully",
                'data' => $customer,
            ]);
        } catch (\Throwable $th) {
            Log::debug('Customer Update Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }


    public function fetchCustomers(int $perPage = 10,$petrolStationId){
        try {
            $customers = Customers::where('petrol_id',$petrolStationId)->paginate($perPage);
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'customers' => $customers,
            ]);
        } catch (\Throwable $th) {
            Log::debug('Fetch Customers Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }
    
     public function searchCustomers(Request $request,$petrolStationId){
    try {
      $searchQuery = $request->query('query');

     $customers = Customers::where('petrol_id',$petrolStationId)->where('name', 'like', '%' . $searchQuery . '%')->take(5)->get();


        // Return a success response with the paginated products
        return response()->json([
            'ok' => true,
            'status' => 'success',
            'message' => 'Customers retrieved successfully.',
            'customers' => $customers
        ]);

    } catch (\Throwable $th) {
        // Log the error and return an error response
        Log::debug('Get Products Error: ' . $th->getMessage());
        return response()->json([
            'ok' => false,
            'status' => 'error',
            'message' => 'Failed to retrieve customers. Please try again.',
            'error' => $th->getMessage()
        ]);
    }
}

}
