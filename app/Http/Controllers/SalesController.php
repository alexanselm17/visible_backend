<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveTransfer;
use App\Http\Requests\AssignStation;
use App\Http\Requests\StockReconcileRequest;
use Illuminate\Http\Request;
use App\Repositories\Sales\SalesRepositoryInterface;
use App\Repositories\Sales\SalesRepository;
use App\Http\Requests\PumpReconcileRequest;
use App\Http\Requests\DiscountCustomerRequest;
use App\Http\Requests\StartShiftRequest;
use App\Http\Requests\StartSessionRequest;
use App\Http\Requests\SalesRequest;
use App\Http\Requests\ReconcileCustomerRequest;
use App\Http\Requests\RepaymentRequest;
use App\Http\Requests\CreditSalesRequest;
use App\Http\Requests\CahierApprovalRequest;
use App\Http\Requests\BankingRequest;
use App\Http\Requests\CheckSalesStatus;
use App\Http\Requests\CustomerReport;
use App\Http\Requests\CustomersRequest;
use App\Http\Requests\PersonalSalesReport;
use App\Http\Requests\PurchasesRequest;
use App\Http\Requests\RecordClosingStock;
use App\Http\Requests\RecordOpeningStock;
use App\Http\Requests\ResetBankingRequest;
use App\Http\Requests\SalesRequestIOT;
use App\Http\Requests\SearchProductsStation;
use App\Http\Requests\StartDrumSessionRequest;
use App\Http\Requests\StartPumpSessionRequest;
use App\Http\Requests\StationSessionDetails;
use App\Http\Requests\StationTransferRequest;
use App\Http\Requests\StockReport;
use App\Http\Requests\ShiftDiscount;
use App\Models\Transaction;

class SalesController extends Controller
{
    private SalesRepositoryInterface $salesRepository;
    public function __construct(SalesRepositoryInterface $salesRepository)
    {
        $this->middleware(['auth:api', 'permission:reconcile_stock'])->only([
            'reconcileStock',
            'reconcilePumps'
        ]);

        $this->middleware(['auth:api', 'permission:approve_expenses'])->only([
            'approveExpenses',
        ]);
        $this->middleware(['auth:api', 'permission:reverse_pump'])->only([
            'reversePump',
        ]);
        $this->middleware(['auth:api', 'permission:approve_invoice'])->only([
            'approveInvoices',
        ]);
        $this->middleware(['auth:api', 'permission:record_dips'])->only([
            'recordDips',
        ]);
        $this->middleware(['auth:api', 'permission:shift'])->only([
            'startShift',
            'endShift'
        ]);
        $this->middleware(['auth:api', 'permission:pump_session'])->only([
            'pumpSession',

        ]);
        $this->middleware(['auth:api', 'permission:sale_in_station'])->only([
            'startProductSession',
            'endProductSession',
            'startStationSession'
        ]);

        $this->middleware(['auth:api', 'permission:reconcile_customer'])->only([
            'reconcileCustomer',
        ]);
        $this->middleware(['auth:api', 'permission:discount_customer'])->only([
            'discountCustomer',
        ]);
        $this->middleware(['auth:api', 'permission:approve_discount'])->only([
            'approveDiscounts',
        ]);
        $this->middleware(['auth:api', 'permission:shift_discount'])->only([
            'recordShiftDiscount',
        ]);
        $this->middleware(['auth:api', 'permission:customer_repayment'])->only([
            'customerRepayment',
        ]);
        $this->middleware(['auth:api', 'permission:reset_bankings'])->only([
            'resetBankings',
        ]);

        $this->middleware(['auth:api', 'permission:cashier_approvals'])->only([
            'cashierApprovals',
        ]);

        $this->middleware(['auth:api', 'permission:station_transfers'])->only([
            'stationTransfers',
            'approveTransfer'
        ]);
        $this->middleware(['auth:api', 'permission:record_purchases'])->only([
            'recordPurchases',
        ]);

        $this->middleware(['auth:api', 'permission:assign_station'])->only([
            'assignStation',
        ]);

        $this->salesRepository = $salesRepository;
    }
    public function reconcileStock(Request $request)
    {
        return $this->salesRepository->reconcileStock($request);
    }

     public function recordExpenses(Request $request)
    {
        return $this->salesRepository->recordExpenses($request);
    }

    public function getUnapprovedExpenses()
    {
        return $this->salesRepository->getUnapprovedExpenses();
    }

    public function approveExpenses(Request $request)
    {
        return $this->salesRepository->approveExpenses($request);
    }

    public function  getUnapprovedCustomerInvoices($petrolStationId,$shiftId)
    {
        return $this->salesRepository->getUnapprovedCustomerInvoices($petrolStationId,$shiftId);
    }
    public function approveInvoices(Request $request)
    {
        return $this->salesRepository->approveInvoices($request);
    }



    public function reconcilePumps(StockReconcileRequest $request, $pumpId, $petrolStationId)
    {
        return $this->salesRepository->reconcileStock($request);
    }





    public function recordSales(SalesRequest $request, )
    {
        return $this->salesRepository->recordSales($request);
    }

    public function reconcileCustomer(ReconcileCustomerRequest $request, $customerId)
    {
        return $this->salesRepository->reconcileCustomer($request, $customerId);
    }




    public function customerRepayment(RepaymentRequest $request, $shiftId, $customerId)
    {
        return $this->salesRepository->customerRepayment($request, $shiftId, $customerId);
    }

    public function resetBankings(ResetBankingRequest $request)
    {
        return $this->salesRepository->resetBankings($request);
    }

    public function recordCreditSales(CreditSalesRequest $request, $shiftId)
    {
        return $this->salesRepository->recordCreditSales($request, $shiftId);
    }
    public function getLastFewTransactions(Request $request, $shiftId, $userId)
    {
        return $this->salesRepository->getLastFewTransactions($request, $shiftId, $userId);
    }

    public function getUnApprovedCashierTransactions($petrolStationId, $shiftId)
    {
        return $this->salesRepository->getUnApprovedCashierTransactions($petrolStationId, $shiftId);
    }
    public function fetchPaymentMethods($category)
    {
        return $this->salesRepository->fetchPaymentMethods($category);
    }
    public function fetchShiftBanking(Request $request)
    {
        return $this->salesRepository->fetchShiftBanking($request);
    }
    public function getUnApprovedBankedTransactions(Request $request, $petrolStationId)
    {
        return $this->salesRepository->getUnApprovedBankedTransactions($request, $petrolStationId);
    }
    public function cashierApprovals(CahierApprovalRequest $request, $userId)
    {
        return $this->salesRepository->cashierApprovals($request, $userId);
    }
    public function recordBankings(BankingRequest $request, $petrolStationId)
    {
        return $this->salesRepository->recordBankings($request, $petrolStationId);
    }



    public function recordPurchases(PurchasesRequest $request)
    {
        return $this->salesRepository->recordPurchases($request);
    }

    public function  salesReturn($transactionId)
    {
        return $this->salesRepository->salesReturn($transactionId);
    }

    public function  getLast10SalesReceipts()
    {
        return $this->salesRepository->getLast10SalesReceipts();
    }

    public function  getAllProductStock(Request $request)
    {
        return $this->salesRepository->getAllProductStock($request);
    }





    public function recordBankingsAutomatic(Request $request)
    {
        return $this->salesRepository->recordBankingsAutomatic($request);
    }
    public function dashboardData(Request $request, $userId, $roleId, $companyId)
    {
        return $this->salesRepository->dashboardData($request, $userId, $roleId, $companyId);
    }
    public function statisticData(Request $request)
    {
        return $this->salesRepository->statisticData($request);
    }

    public function generateSalesReport(Request $request, $petrolStationId, $shiftId)
    {
        return $this->salesRepository->generateSalesReport($request, $petrolStationId, $shiftId);
    }
    public function generatePersonalSalesReport(PersonalSalesReport $request)
    {
        return $this->salesRepository->generatePersonalSalesReport($request);
    }
    public function downloadSalesReport(Request $request, $shiftId,$petrolStationId)
    {
        return $this->salesRepository->downloadSalesReport($request, $shiftId,$petrolStationId);
    }

    public function downloadCustomerReport(Request $request)
    {
        return $this->salesRepository->downloadCustomerReport($request);
    }
    public function generateCustomerReport(CustomerReport $request)
    {
        return $this->salesRepository->generateCustomerReport($request);
    }
    public function generateStockReport(StockReport $request, $petrolStationId)
    {
        return $this->salesRepository->generateStockReport($request, $petrolStationId);
    }
    public function periodicSalesmanReport(Request $request)
    {
        return $this->salesRepository->periodicSalesmanReport($request);
    }

    public function periodicGeneralReport(Request $request)
    {
        return $this->salesRepository->periodicGeneralReport($request);
    }


    public function dailyReport(Request $request)
    {
        return $this->salesRepository->dailyReport($request);
    }
}
