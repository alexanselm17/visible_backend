<?php

namespace App\Repositories\Customers;

use App\Http\Requests\CustomersRequest;
use App\Http\Requests\CustomerUpdateRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Customers;
use Illuminate\Http\Request;

interface CustomerRepositoryInterface
{
  
    public function createCustomer(CustomersRequest $request,$petrolStationId);

 
    public function updateCustomer(CustomerUpdateRequest $request, String $id);

    public function fetchCustomers(int $perPage = 10,$petrolStationId);
    public function searchCustomers(Request $request,$petrolStationId);
}
