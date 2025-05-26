<?php

namespace App\Http\Controllers;

use App\Repositories\Customers\CustomerRepositoryInterface;
use App\Http\Requests\CustomersRequest;
use Illuminate\Http\Request;
use App\Http\Requests\CustomerUpdateRequest;


class CustomersController extends Controller
{
    private CustomerRepositoryInterface $customerRepository;

    /**
     * Inject the CustomerRepositoryInterface into the controller.
     *
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(CustomerRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;

        $this->middleware(['auth:api', 'permission:customer_operations'])->only([
            'createCustomer',
            'updateCustomer',
        ]);
    }

    /**
     * Create a new customer.
     *
     * @param CustomersRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCustomer(CustomersRequest $request,$petrolStationId)
    {
        return $this->customerRepository->createCustomer($request,$petrolStationId);
    }


    public function updateCustomer( CustomerUpdateRequest $request, String $id)
    {
        return $this->customerRepository->updateCustomer($request, $id);
    }


    public function fetchCustomers(Request $request,$petrolStationId)
    {
        $perPage = $request->input('per_page', 10);
        return $this->customerRepository->fetchCustomers($perPage,$petrolStationId);
    }
    public function searchCustomers(Request $request,$petrolStationId)
    {

        return $this->customerRepository->searchCustomers($request,$petrolStationId);
    }
}
