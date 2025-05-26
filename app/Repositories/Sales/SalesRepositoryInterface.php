<?php

namespace App\Repositories\Sales;

use App\Http\Requests\ApproveTransfer;
use App\Http\Requests\AssignStation;
use App\Http\Requests\StockReconcileRequest;
use App\Http\Requests\PumpReconcileRequest;
use App\Http\Requests\SalesRequest;
use App\Http\Requests\CreditSalesRequest;
use App\Http\Requests\StartShiftRequest;
use App\Http\Requests\RepaymentRequest;
use App\Http\Requests\CahierApprovalRequest;
use App\Http\Requests\StartSessionRequest;
use App\Http\Requests\ReconcileCustomerRequest;
use App\Http\Requests\BankingRequest;
use App\Http\Requests\CheckSalesStatus;
use App\Http\Requests\CustomerReport;
use App\Http\Requests\DiscountCustomerRequest;
use App\Http\Requests\PersonalSalesReport;
use App\Http\Requests\PurchasesRequest;
use App\Http\Requests\RecordClosingStock;
use App\Http\Requests\RecordOpeningStock;
use App\Http\Requests\ResetBankingRequest;
use App\Http\Requests\SalesRequestIOT;
use App\Http\Requests\SearchProductsStation;
use App\Http\Requests\ShiftDiscount;
use App\Http\Requests\StartDrumSessionRequest;
use App\Http\Requests\StartPumpSessionRequest;
use App\Http\Requests\StationSessionDetails;
use App\Http\Requests\StationTransferRequest;
use App\Http\Requests\StockReport;
use Illuminate\Http\Request;

interface SalesRepositoryInterface
{
    //reconciliations
    public function reconcileStock(Request $request);


    //sales
    public function recordSales(SalesRequest $request);

    //purchases
    public function recordPurchases(PurchasesRequest $request);
    public function salesReturn($transactionId);
    public function recordCreditSales(CreditSalesRequest $request, $shiftId);
    public function getLastFewTransactions(Request $request, $shiftId, $userId);
    public function getUnApprovedBankedTransactions(Request $request, $petrolStationId);

    //money
    public function getUnApprovedCashierTransactions($petrolStationId, $shiftId);
    public function cashierApprovals(CahierApprovalRequest $request, $userId);
    public function recordBankings(BankingRequest $request, $petrolStationId);
    public function recordBankingsAutomatic(Request $request);
    public function fetchPaymentMethods($category);
    public function fetchShiftBanking(Request $request);
    public function resetBankings(ResetBankingRequest $request);
    public function recordExpenses(Request $request);
    public function getUnapprovedExpenses();
    public function getLast10SalesReceipts();
    public function getAllProductStock(Request $request);
    public function approveExpenses(Request $request);





    //customers
    public function reconcileCustomer(ReconcileCustomerRequest $request, $customerId);


    public function customerRepayment(RepaymentRequest $request, $shiftId, $customerId);



    //shift


    public function getUnapprovedCustomerInvoices($petrolStationId,$shiftId);
    public function approveInvoices(Request $request);





    //DashboardData
    public function dashboardData(Request $request, $userId, $roleId, $companyId);
    public function statisticData(Request $request);

    //Report
    public function generateSalesReport(Request $request, $petrolStationId, $shiftId);
    public function generateCustomerReport(CustomerReport $request);
    public function generatePersonalSalesReport(PersonalSalesReport $request);
    public function generateStockReport(StockReport $request, $petrolStationId);
    public function periodicSalesmanReport(Request $request);
    public function periodicGeneralReport(Request $request);

    public function dailyReport(Request $request);



    //Downloads
    public function downloadSalesReport(Request $request, $shiftId, $petrolStationId);
    public function downloadCustomerReport(Request $request);

}
