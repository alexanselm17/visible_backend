<?php

namespace App\Repositories\Sales;

use App\Http\Requests\ApproveTransfer;
use App\Http\Requests\AssignStation;
use App\Models\Shift;
use App\Models\Session;
use App\Models\SalesPending;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\DiscountCustomerRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Requests\StockReconcileRequest;
use App\Http\Requests\PumpReconcileRequest;
use App\Http\Requests\SalesRequest;
use App\Http\Requests\RepaymentRequest;
use App\Http\Requests\StartShiftRequest;
use App\Http\Requests\StartSessionRequest;
use App\Models\Company;
use App\Models\Discounts;
use App\Http\Requests\ShiftDiscount;
use App\Models\Dips;
use App\Models\Pump;
use App\Models\ProductsModel;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\PumpReading;
use App\Models\PumpLog;
use App\Models\Drum;
use App\Models\ExpensesModel;
use App\Models\User;
use App\Models\Customers;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\TransactionProduct;
use App\Models\TransactionDetail;
use App\Models\DrumSessionDetail;
use App\Models\PumpSessionDetail;
use App\Http\Requests\CahierApprovalRequest;
use App\Models\SysMeta;
use App\Models\Banking;
use App\Models\Messaging;
use Carbon\Carbon;
use App\Http\Requests\BankingRequest;
use App\Http\Requests\CheckSalesStatus;
use PDF;
use App\Http\Requests\ReconcileCustomerRequest;
use App\Http\Requests\CreditSalesRequest;
use App\Http\Requests\CustomerReport;
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
use App\Models\PetrolStation;
use App\Models\RolesModel;
use App\Models\Stations;
use App\Models\StationSessiontModel;
use App\Models\StationShiftModel;
use App\Models\Transfer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SalesRepository implements SalesRepositoryInterface
{
    public function reconcileStock(Request $request)
    {
        DB::beginTransaction();
        try {

            $productId = $request->input('product_id');
            $quantity = $request->input('quantity');

            $stock = Stock::where('product_id', $productId)->first();

            $prevStockAmount = $stock ? $stock->stock : 0;

            if ($stock) {
                // Update the existing stock
                $stock->stock = $quantity;
                $stock->save();
            } else {
                // Create new stock record
                $stock = Stock::create([
                    'product_id' => $productId,
                    'stock' => $quantity,
                ]);
            }

            // Log the stock reconciliation
            StockLog::create([
                'product_id' => $productId,
                'prev_stock' => $prevStockAmount,
                'curr_stock' => $quantity,
                'quantity' => $quantity,
            ]);

            DB::commit();

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Stock reconciled successfully.',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::debug('Reconcile Stock Error: ' . $th->getMessage());

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'Reconcile stock failed. Please try again.',
                'error' => $th->getMessage(),
            ]);
        }
    }



      public function recordExpenses(Request $request)
  {
      try {

          // Create the expense record
          $expenses = ExpensesModel::create([
              'description' => $request->input('description'),
              'posted_by' => $request->input('user_id'),
              'amount' => $request->input('amount'),

            ]);

          // Return success response
          return response()->json([
              'ok' => true,
              'status' => 'success',
              'message' => 'Expense added successfully.',
              'expenses' => $expenses,
          ]);

      } catch (\Throwable $th) {
          // Handle any errors during processing
          return response()->json([
              'ok' => false,
              'status' => 'error',
              'message' => 'Error in processing expenses.',
              'error' => $th->getMessage(),
          ], 500);
      }
  }

  public function getUnapprovedExpenses()
{
    try {
        // Retrieve expenses with the specified shift ID and unapproved status
        $expenses = ExpensesModel::where('approval_status', false)
            ->leftJoin('users','expenses.posted_by','=','users.id')
            ->select('expenses.*','users.fullname as posted_by')
            ->get();

        // Return success response
        return response()->json([
            'ok' => true,
            'status' => 'success',
            'message' => 'Unapproved expenses retrieved successfully.',
            'expenses' => $expenses,
        ]);
    } catch (\Throwable $th) {
        // Handle any errors during processing
        return response()->json([
            'ok' => false,
            'status' => 'error',
            'message' => 'Error in retrieving unapproved expenses.',
            'error' => $th->getMessage(),
        ], 500);
    }
}

public function approveExpenses(Request $request)
{
    try {
        // Validate incoming request data
        $validatedData = $request->validate([
            'expense_id' => 'required|exists:expenses,id', // Validates the expense ID
            'status' => 'required|in:1,2', // Ensures status is either 1 (approved) or 2 (rejected)
            'approved_by' => 'required|exists:users,id',
        ]);

        // Retrieve the expense by ID
        $expense = ExpensesModel::findOrFail($validatedData['expense_id']);
   // Determine the message based on the status
   $message = $validatedData['status'] == 1
   ? "Expenses approved successfully"
   : "Expenses rejected successfully";
        // Update the approval status and approved_by fields
        $expense->approval_status = $validatedData['status'];
        $expense->approved_by = $validatedData['approved_by'];
        $expense->save();

        // Return success response
        return response()->json([
            'ok' => true,
            'status' => 'success',
            'message' => $message,
            'expense' => $expense,
        ]);

    } catch (\Throwable $th) {
        // Handle errors during processing
        return response()->json([
            'ok' => false,
            'status' => 'error',
            'message' => 'Error in approving the expense.',
            'error' => $th->getMessage(),
        ], 500);
    }
}

  public function dashboardData(Request $request, $userId, $roleId, $companyId)
  {
    try {

      // Get the role difference
      $petrolId = $request->query('petrol_id');
      $role = RolesModel::where('id', $roleId)->first();
      // Retrieve the latest shift for both admin and salesman

      $person = User::where('id', $userId)->where('role_id', $roleId)->first();
      // Compare the role_id with the retrieved role's id


      if ($person->role_id != $role->id) {
        return response()->json([
          'ok' => false,
          'status' => 'error',
          'message' => 'The role is not the user role.',
          'error' => "The role is not the user role"
        ], 500);
      }
      if ($role->slug == "admin") {
        $petrolStations = PetrolStation::where('company_id', $person->company_id)->get();
      } else {
        $petrolStations = PetrolStation::where('id', $person->petrol_id)->get();
      }




      $seachedPetrol = PetrolStation::where('id', $petrolId)->first();
      $firstPetrolStation = $seachedPetrol == null ? $petrolStations->first() : $seachedPetrol;


      $shift = Shift::where('petrol_id', $firstPetrolStation->id)->orderByDesc('created_at')->first();



      $firstPetrolStation = PetrolStation::where('id', $person->petrol_id)->first();

      if ($role->slug == 'salesman') {

        if ($shift == null) {
          $stations = null;
          $bankings = null;
          $pump = null;
        } else {

          $petrolStations = PetrolStation::where('id', $person->petrol_id)->get();

          $stations = StationShiftModel::where('assigned_to', $userId)
            ->where('shift_id', $shift->id)
            ->with('station')
            ->get();

          $bankings = Banking::select(
            'processed_by',
            DB::raw("
                                                    SUM(CASE WHEN sys_metas.meta_shortcode = 'mpesa' THEN amount ELSE 0 END) as mpesa_total,
                                                    SUM(CASE WHEN sys_metas.meta_shortcode = 'cash' THEN amount ELSE 0 END) as cash_total,
                                                    SUM(CASE WHEN sys_metas.meta_shortcode NOT IN ('mpesa', 'cash') THEN amount ELSE 0 END) as other_total
                                                ")
          )
            ->join('sys_metas', 'bankings.deposit_method', '=', 'sys_metas.id')
            ->where('shift_id', $shift->id)
            ->where('processed_by', $userId)
            ->where('approval_status', 1)
            ->with('processedBy')
            ->groupBy('processed_by')
            ->get();
          $myPetrolStation = PetrolStation::where('id', $person->petrol_id)->first();
          if ($myPetrolStation->type == "IOT") {
            $pump = PumpSessionDetail::where('shift_id', $shift->id)
              ->with('pump')
              ->where('petrol_id', $person->petrol_id)
              ->get();
          } else {
            $pump = PumpSessionDetail::where('shift_id', $shift->id)
              ->where('assigned_to', $userId)
              ->with('pump')
              ->where('petrol_id', $person->petrol_id)
              ->get();
          }


          $stations->load('station');
        }
      } else {

        if ($shift == null) {
          //we get all the bankings done by all salesman
          $bankings = null;
        } else {

          if ($role->slug == "admin") {
            $petrolStations = PetrolStation::where('company_id', $person->company_id)->get();
          } else {
            $petrolStations = PetrolStation::where('id', $person->petrol_id)->get();
          }





          $seachedPetrol = PetrolStation::where('id', $petrolId)->first();
          $firstPetrolStation = $seachedPetrol == null ? $petrolStations->first() : $seachedPetrol;

          $bankings = Banking::select(
            'processed_by',
            DB::raw("SUM(CASE WHEN sys_metas.meta_shortcode = 'mpesa' THEN amount ELSE 0 END) as mpesa_total,
                                    SUM(CASE WHEN sys_metas.meta_shortcode = 'cash' THEN amount ELSE 0 END) as cash_total,
                                    SUM(CASE WHEN sys_metas.meta_shortcode NOT IN ('mpesa', 'cash') THEN amount ELSE 0 END) as other_total
                                     ")
          )
            ->join('sys_metas', 'bankings.deposit_method', '=', 'sys_metas.id')
            ->where('shift_id', $shift->id)
            ->where('approval_status', 1)
            ->where('bankings.petrol_id', $firstPetrolStation->id)
            ->with('processedBy')
            ->groupBy('processed_by')
            ->get();
        }


        // Get products in the FUEL category and calculate their total stock
        $products = ProductsModel::where('category', 'FUEL')
          ->with(['drums.stock'])
          ->where('petrol_id', $firstPetrolStation->id)
          ->get()
          ->map(function ($product) {
            $totalStock = $product->drums->reduce(function ($carry, $drum) {
              return $carry + ($drum->stock->stock ?? 0);
            }, 0);
            return [
              'name' => $product->name,
              'selling_price' => $product->selling_price,
              'total_stock' => $totalStock,
              'total_cash' => $totalStock * $product->selling_price
            ];
          });
      }

      $customerCount = Customers::where('petrol_id', $firstPetrolStation->id)->count();
      $pumpCount = Pump::where('petrol_id', $firstPetrolStation->id)->count();
      $tankCount = Drum::where('petrol_id', $firstPetrolStation->id)->count();
      $stationCount = Stations::where('petrol_id', $firstPetrolStation->id)->count();

      return response()->json([
        'ok' => true,
        'status' => 'success',
        'message' => 'Record retrieved successfully',
        'data' => [
          'shift' => $shift,
          'products' => $products ?? null,
          'pump' => $pump ?? null,
          'user' => $person,
          'bankings' => $bankings,
          'stations' => $stations ?? null,
          'customer_count' => $customerCount,
          'pump_count' => $pumpCount,
          'tank_count' => $tankCount,
          'station_count' => $stationCount,
          'petrolStation' => $petrolStations
        ],
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Stock operation failed. Please try again.',
        'error' => $th->getMessage()
      ]);
    }
  }



  public function statisticData(Request $request)
  {
    try {
      $searchQuery = $request->query('date_filter');
      $compareWith = $request->query('compare_with');
      $petrolId = $request->query('petrol_station');

      // Parse the date_filter into a Carbon instance
      $searchDate = Carbon::parse($searchQuery);

      // 1. Get all shifts for the selected day
        $shifts = Shift::where('petrol_id',  $petrolId )
      ->whereRaw('DATE(created_at) = ?', [$searchQuery])
      ->get();

      $shiftIds = $shifts->pluck('id');

      $totalFuelSales = 0;
      $totalNonFuelSales = 0;

      // To store sales of each fuel product separately
      $fuelSalesByProduct = [];

      // Function to calculate the total sales for the given shifts
      $calculateSales = function ($shifts) {
        $totalFuelSales = 0;
        $totalNonFuelSales = 0;
        $fuelSalesByProduct = [];

        foreach ($shifts as $shift) {
          // 2. Get pump session details for this shift
          $pumpSessions = $shift->pumpSessionDetails;

          foreach ($pumpSessions as $pumpSession) {
            $pump = $pumpSession->pump;
            $product = $pump->drum->product;

            // 3. Calculate fuel sales (ensure numeric values)
            $fuelSale = is_numeric($pumpSession->ended_cash)
              ? (float)$pumpSession->ended_cash - (float)($pumpSession->start_cash ?? 0)
              : 0;

            $totalFuelSales += $fuelSale;
            if (!isset($fuelSalesByProduct[$product->name])) {
              $fuelSalesByProduct[$product->name] = 0;
            }
            $fuelSalesByProduct[$product->name] += $fuelSale;
          }


          // 4. Get non-fuel product sales (ensure numeric values)
          $stationSessions = StationSessiontModel::where('shift_id', $shift->id)->get();

          foreach ($stationSessions as $stationSession) {
            $product = $stationSession->product;

            $nonFuelSale = is_numeric($stationSession->closing_stock) && is_numeric($stationSession->opening_stock) && is_numeric($stationSession->price)
              ? (float)($stationSession->opening_stock- ($stationSession->closing_stock  ?? 0)) * (float)($stationSession->price ?? 0)
              : 0;

            $totalNonFuelSales += $nonFuelSale;
          }
        }

        // Format fuel sales by product to the desired structure
        $formattedFuelSalesByProduct = [];
        foreach ($fuelSalesByProduct as $productName => $sales) {
          $formattedFuelSalesByProduct[] = [
            'products_name' => $productName,
            'total_sold' => number_format((float)$sales, 2)
          ];
        }

        return [
          'totalFuelSales' => number_format((float)$totalFuelSales, 2),  // Cast to float before formatting
          'totalNonFuelSales' => number_format((float)$totalNonFuelSales, 2),  // Cast to float before formatting
          'fuelSalesByProduct' => $formattedFuelSalesByProduct,
          'totalSales' => number_format((float)($totalFuelSales + $totalNonFuelSales), 2),  // Cast to float before formatting
        ];
      };

      // Calculate current sales for the selected day
      $currentSales = $calculateSales($shifts);


      // Initialize comparison sales
      $previousSales = [
        'totalFuelSales' => 0,
        'totalNonFuelSales' => 0,
        'fuelSalesByProduct' => [],
        'totalSales' => 0,
      ];

      // 2. Get shifts for the comparison period
      if ($compareWith) {
        $comparisonShifts = [];
        switch ($compareWith) {
          case 'yesterday':

            $comparisonDate = $searchDate->copy()->subDay();
            $comparisonShifts = Shift::whereDate('started_at', $comparisonDate)->where('petrol_id', $petrolId)->get();
            break;
          case 'last_week':
            $searchDate = Carbon::parse($searchQuery); // Convert the search query to a Carbon instance

            $startOfLastWeek = $searchDate->copy()->subWeek()->startOfWeek(); // Start of last week
            $endOfLastWeek = $searchDate->copy()->subWeek()->endOfWeek();     // End of last week

            // Use these dates for your query
            $comparisonShifts = Shift::whereBetween('started_at', [$startOfLastWeek, $endOfLastWeek])
              ->where('petrol_id', $petrolId)
              ->get();
            $comparisonShifts = Shift::whereBetween('started_at', [$startOfLastWeek, $endOfLastWeek])->where('petrol_id', $petrolId)->get();
            break;
          case 'last_month':
            $startOfLastMonth = $searchDate->copy()->subMonth()->startOfMonth();
            $endOfLastMonth = $searchDate->copy()->subMonth()->endOfMonth();

            $comparisonShifts = Shift::whereBetween('started_at', [$startOfLastMonth, $endOfLastMonth])->where('petrol_id', $petrolId)->get();
            break;
          case 'last_year':
            $year = $searchDate->copy()->subYear()->year;
            $comparisonShifts = Shift::whereYear('started_at', $year)->where('petrol_id', $petrolId)->get();
            break;
          default:
            $comparisonShifts = Shift::whereDate('started_at', $searchDate)->where('petrol_id', $petrolId)->get();
        }

        // Calculate the previous sales
        if (count($comparisonShifts) > 0) {
          $previousSales = $calculateSales($comparisonShifts);
        }
      }

      // 3. Calculate percentage change (ensure numeric values)
      $currentSalesTotal = is_numeric(str_replace(',', '', $currentSales['totalSales']))
      ? (float)str_replace(',', '', $currentSales['totalSales'])
      : 0;

      $previousSalesTotal = is_numeric(str_replace(',', '', $previousSales['totalSales']))
      ? (float)str_replace(',', '', $previousSales['totalSales'])
      : 0;

      $percentageChange = $previousSalesTotal > 0
        ? (($currentSalesTotal - $previousSalesTotal) / $previousSalesTotal) * 100
        : ($currentSalesTotal > 0 ? 100 : 0);

      // 4. Get all bankings for the selected day with approval_status = 1 and grouped by deposit method
      $bankingsByMethod = Banking::whereIn('shift_id', $shiftIds)
        ->where('approval_status', 1)  // Filter for approved bankings
        ->select('deposit_method', \DB::raw('SUM(amount) as total_amount'))
        ->groupBy('deposit_method')
        ->where('petrol_id', $petrolId)
        ->get()
        ->map(function ($banking) {

          $method = SysMeta::find($banking->deposit_method);
          return [
            'method' => $method->meta_value,
            'total_amount' => number_format((float)$banking->total_amount, 2),  ];
        });




      // 5. Calculate total invoice sales for the shifts
      $totalInvoicesSales = Invoice::whereHas('transaction', function ($query) use ($shiftIds) {
        $query->whereIn('shift_id', $shiftIds);
      })->sum('amount');

      // 6. Return the summarized data including fuel sales by product, bankings, and comparison data
      return response()->json([
        'ok' => true,
        'status' => 'success',
        'total_fuel_sales' => $currentSales['totalFuelSales'],
        'fuel_sales_by_product' => $currentSales['fuelSalesByProduct'],  // Returning sales of each fuel product
        'total_non_fuel_sales' => $currentSales['totalNonFuelSales'],
        'total_sales' => $currentSales['totalSales'],
        'previous_sales' => number_format((float)$previousSalesTotal, 2),  // Cast to float
        'percentage_change' => number_format((float)$percentageChange, 2),  // Cast to float
        'bankings_by_method' => $bankingsByMethod,  // Banking totals by method
        'total_invoices_sales' => number_format((float)$totalInvoicesSales, 2),  // Cast to float
      ]);
    } catch (\Throwable $th) {
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Sales operation failed. Please try again.',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function recordSales(SalesRequest $request)
{
    try {
        DB::beginTransaction();
        $data=[];


        $products = $request->input('products');
        $payments = $request->input('payment');
        $userId = $request->input('user_id');

        // Validate that total payment equals total sales
        $grossTotalPayment = 0;
        foreach ($payments as $payment) {
            $grossTotalPayment += $payment['amount'];
        }

        $grossTotal = 0;
        foreach ($products as $product) {
            $grossTotal += $product['price']*$product['quantity']-$product['discount'];
        }


        if ($grossTotalPayment != $grossTotal) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Please account for all payment.'
            ]);
        }

        $thereIsInvoice=false;
        foreach ($payments as $payment) {
          if ($payment['name'] == "Invoice") {
            $thereIsInvoice=true;
            //let's ensure that customer details is provided

            if(count($payments) < 1){
              return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Should only be one payment method'
            ]);
            }

          }
        }
        // Step 1: Create the transaction
        $transaction = Transaction::create([
            'is_returned' => false

        ]);

        // Step 2: Create transaction details
        TransactionDetail::create([
            'transaction_type' => 'Sales',
            'transaction_id' => $transaction->id,
            'processed_by' => $userId,
            'gross_total' => $grossTotal,
            'approval_status'=>$thereIsInvoice == true? false:true
        ]);



        // Step 3: Create transaction products
        foreach ($products as $product) {
            TransactionProduct::create([
                'transaction_id' => $transaction->id,
                'product_id' => $product['id'],
                'price' => $product['price'],
                'quantity' => $product['quantity'],
                'total' => $product['quantity'] * $product['price']-$product['discount'],
                'discount'=>$product['discount']
            ]);
        }

        // let's handle the stock operation here
        foreach ($products as $product) {
            //chech if the product is master product
            $prod=ProductsModel::where('id',$product['id'])->first();
            if($prod -> parent_id == null){
                //here we just stock-quantity
                $stock=Stock::where('product_id',$product['id'])->first();
                if($stock == null){
                    //we need to have stock for us to sell
                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => 'Stock not Found'
                    ], 400);
                }
                else if($product['quantity']>$stock->stock ){

                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => 'No enough stock to sell '
                    ], 400);

                }
                else{
                    $currStock=$stock->stock;
                    $stock->stock=$currStock-$product['quantity'];
                    $stock->save();

                    StockLog::create([
                        'product_id' => $product['id'],
                        'prev_stock' => $currStock,
                        'curr_stock' => $currStock-$product['quantity'],
                        'quantity' => $product['quantity'],
                    ]);

                }

            }
            else{
                //we have to multiply unit*quanty and subtract from stock its master
                $masterProduct=ProductsModel::where('id',$prod->parent_id)->first();
                $stock=Stock::where('product_id',$masterProduct->id)->first();
                if($stock == null){
                    //we need to have stock for us to sell
                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => 'Stock not Found'
                    ], 400);
                }
                else if($product['quantity']>$stock->stock ){

                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => 'No enough stock to sell '
                    ], 400);

                }
                else{
                    $currStock=$stock->stock;
                    $stock->stock=$currStock-$product['quantity']*$prod->unit;
                    $stock->save();

                    StockLog::create([
                        'product_id' => $product['id'],
                        'prev_stock' => $currStock,
                        'curr_stock' => $currStock-$product['quantity']*$prod->unit,
                        'quantity' => $product['quantity']*$prod->unit,
                    ]);

                }

            }
        }


        // Step 4: Handle payments
        foreach ($payments as $payment) {

            if ($payment['name'] == "Invoice") {

              $customerId = $request->input('customer_id');
              $customer=Customers::where('id',$customerId )->first();
              if($customer == null){
                     return response()->json([
            'ok' => false,
            'status' => 'failed',
            'message' => 'Please provide the customer'
        ], 400);
              }
              $customerLastInvoice = Invoice::where('customer_id', $customerId)->latest()->first();
              $customerBalance = $customerLastInvoice ? $customerLastInvoice->customer_balance : 0;
             $pendingInvoice= SalesPending::create([
                "invoice_number" => $transaction->id,
                "type" => "Invoice Sales",
                "amount" => $payment['amount'],
                "customer_id" => $customerId,
                "customer_balance" => $customerBalance + $payment['amount'],
                "posted_by" => $userId,
                "invoice_note" => $payment['invoice_note'],
              ]);

            } elseif ($payment['name'] == "Cash") {

                $depositMethod = SysMeta::where('meta_value', $payment['name'])->first();

                Banking::create([
                    "amount" => $payment['amount'],
                    'processed_by' => $userId,
                    'approval_status' => 0,
                    'transaction_id' => $transaction->id,
                    'approved_by' => null,
                    'deposit_method' => $depositMethod->id,
                ]);
            } else {
                // Handle other payment types
                $existTransaction = Banking::where('reference', $payment['reference'])->first();

                if ($existTransaction) {
                    if ($existTransaction->processed_by != null) {
                        return response()->json([
                            'ok' => false,
                            'status' => 'failed',
                            'message' => "Transaction already processed."
                        ], 500);
                    } else {
                        $existTransaction->processed_by = $userId;
                        $existTransaction->save();
                    }
                } else {
                    $depositMethod = SysMeta::where('meta_value', $payment['name'])->first();

                    Banking::create([
                        'reference' => $payment['reference'],
                        'amount' => $payment['amount'],
                        'processed_by' => $userId,
                        'approval_status' => 0,
                        'transaction_id' => $transaction->id,
                        'approved_by' => null,
                        'deposit_method' => $depositMethod->id,
                    ]);
                }
            }
        }

        DB::commit();


        return response()->json([
            'ok' => true,
            'status' => 'success',
            'message' => 'Sales recorded successfully.'
        ], 200);

    }  catch (\Throwable $th) {
      DB::rollBack();

      Log::debug('Drum Session Error: ' . $th->getMessage());

      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Failed to record Sales.',
        'error' => $th->getMessage()
      ]);
    }
}

public function salesReturn($transactionId)
{
    try {
        DB::beginTransaction();

        // 1. Get the transaction
        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Transaction not found.'
            ], 404);
        }

        if ($transaction->is_returned === true) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'This transaction has already been returned.'
            ], 400);
        }
        $transDetails= TransactionDetail::where('transaction_id',$transactionId)->first();
        $transDetails->is_returned= true;
        $transDetails->save();

        // 2. Get transaction products
        $products = TransactionProduct::where('transaction_id', $transactionId)->get();

        foreach ($products as $item) {
            $product = ProductsModel::find($item->product_id);

            if (!$product) continue;

            $qtyToReturn = $item->quantity;

            if ($product->parent_id === null) {
                // Master product: add back quantity directly
                $stock = Stock::where('product_id', $product->id)->first();
                if ($stock) {
                    $prevStock = $stock->stock;
                    $stock->stock += $qtyToReturn;
                    $stock->save();

                    StockLog::create([
                        'product_id' => $product->id,
                        'prev_stock' => $prevStock,
                        'curr_stock' => $stock->stock,
                        'quantity' => -1 * $qtyToReturn, // negative indicates reversal
                    ]);
                }
            } else {
                // Child product: adjust master product stock
                $masterProduct = ProductsModel::find($product->parent_id);
                if ($masterProduct) {
                    $stock = Stock::where('product_id', $masterProduct->id)->first();
                    if ($stock) {
                        $reversedQty = $qtyToReturn * $product->unit;
                        $prevStock = $stock->stock;
                        $stock->stock += $reversedQty;
                        $stock->save();

                        StockLog::create([
                            'product_id' => $product->id,
                            'prev_stock' => $prevStock,
                            'curr_stock' => $stock->stock,
                            'quantity' => -1 * $reversedQty,
                        ]);
                    }
                }
            }
        }

        // 3. Mark transaction as returned
        $transaction->is_returned = true;
        $transaction->save();

        DB::commit();

        return response()->json([
            'ok' => true,
            'status' => 'success',
            'message' => 'Sales return processed successfully.'
        ]);
    } catch (\Throwable $th) {
        DB::rollBack();

        Log::debug('Sales Return Error: ' . $th->getMessage());

        return response()->json([
            'ok' => false,
            'status' => 'error',
            'message' => 'Failed to process sales return.',
            'error' => $th->getMessage()
        ], 500);
    }
}

public function getLast10SalesReceipts()
{
    try {
        $transactions = Transaction::where('is_returned', false)
            ->whereHas('transactionDetails', function ($q) {
                $q->where('transaction_type', 'Sales');
            })
            ->latest()
            ->take(10)
            ->with([
                'transactionDetails' => function ($q) {
                    $q->select('id', 'transaction_id', 'gross_total', 'processed_by', 'created_at')
                      ->with('processedBy:id,fullname,email');
                },
                'transactionProducts' => function ($q) {
                    $q->select('id', 'transaction_id', 'product_id', 'price', 'quantity', 'discount', 'total')
                      ->with('product:id,name');
                },
                'salesPendings:id,invoice_number,type,amount,customer_id,invoice_note',
               'bankings' => function ($q) {
    $q->select('id', 'transaction_id', 'reference', 'amount', 'deposit_method')
      ->with('depositMethod:id,meta_key,meta_value'); // <-- include SysMeta details
},
            ])

            ->get();

        return response()->json([
            'ok' => true,
            'status' => 'success',
            'receipts' => $transactions
        ]);
    } catch (\Throwable $th) {
        \Log::error('Error fetching last 10 sales receipts: ' . $th->getMessage());

        return response()->json([
            'ok' => false,
            'status' => 'error',
            'message' => 'Failed to fetch sales receipts.',
            'error' => $th->getMessage()
        ], 500);
    }
}



public function getAllProductStock(Request $request)
{
    try {
        // Default to 15 per page, but allow client to override via ?per_page=xx
        $perPage = $request->input('per_page', 15);

        $stockItems = Stock::with('product:id,name,selling_price,min_stock,unit,unit_name,parent_id')
            ->select('id', 'product_id', 'stock')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'status' => 'success',
            'stock' => $stockItems
        ]);
    } catch (\Throwable $th) {
        \Log::error('Error fetching product stock: ' . $th->getMessage());

        return response()->json([
            'ok' => false,
            'status' => 'error',
            'message' => 'Failed to fetch product stock.',
            'error' => $th->getMessage()
        ], 500);
    }
}







public function recordPurchases(PurchasesRequest $request)
{
    try {
        DB::beginTransaction();

        $products = $request->input('products');
        $userId = $request->input('user_id');

        // Step 1: Create the transaction
        $transaction = Transaction::create([
            'is_returned' => false
        ]);

        // Step 2: Calculate gross total
        $grossTotal = collect($products)->sum(function ($product) {
            return $product['price'] * $product['quantity'];
        });

        // Step 3: Process each product
        foreach ($products as $product) {
            // Create transaction detail
            TransactionDetail::create([
                'transaction_type' => 'Purchases',
                'transaction_id' => $transaction->id,
                'processed_by' => $userId,
                'gross_total' => $grossTotal,
            ]);

            // Record product in transaction
            TransactionProduct::create([
                'transaction_id' => $transaction->id,
                'product_id' => $product['id'],
                'price' => $product['price'],
                'quantity' => $product['quantity'],
                'total' => $product['quantity'] * $product['price'],
            ]);

            // Handle stock update
            $stock = Stock::where('product_id', $product['id'])->first();
            if ($stock) {
                $prevStock = $stock->stock;
                $stock->stock += $product['quantity'];
                $stock->save();
            } else {
                $prevStock = 0;
                $stock = Stock::create([
                    'stock' => $product['quantity'],
                    'product_id' => $product['id'],
                ]);
            }

            // Record stock log
            StockLog::create([
                'curr_stock' => $stock->stock,
                'prev_stock' => $prevStock,
                'quantity' => $product['quantity'],
                'product_id' => $product['id'],
            ]);
        }

        DB::commit();

        return response()->json([
            'ok' => true,
            'status' => 'success',
            'message' => 'Purchases made successfully',
        ]);
    } catch (\Throwable $th) {
        DB::rollBack();
        return response()->json([
            'ok' => false,
            'status' => 'error',
            'message' => 'Purchases operation failed. Please try again.',
            'error' => $th->getMessage(),
        ], 500);
    }
}




protected function sendSms($receipients,$message, $sender_id,$apiKey)
{

$apiKey =  $apiKey;

$recipient = $receipients;
$senderId =  $sender_id;
$message = $message;

$postData = [
    'recipient' => $recipient,
    'sender_id' => $senderId,
    'type' => 'plain',
    'message' => $message,
];

$curl = curl_init('https://bulksms.talksasa.com/api/v3/sms/send');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    'Content-Type: application/json',
    'Accept: application/json'
]);
//remember to comment this
//return true;
$response = curl_exec($curl);

$curlError = curl_error($curl);

curl_close($curl);


}


  public function approveInvoices(Request $request){
    try{
      DB::beginTransaction();
      $approvalStatus=$request->input('status');

      $salesInvoiceId=$request->input('pending_sales_invoice_id');
      $salesInvoices=SalesPending::where('id',  $salesInvoiceId)->first();
      $transDetails=  TransactionDetail::where('transaction_id',$salesInvoices->invoice_number)->first();
      if($approvalStatus == 2){
        $transDetails->approval_status=2;
        $transDetails->save();
         DB::commit();
       return response()->json([
          'ok' => true,
          'status' => 'Success',
         'message'=>'Transaction Rejected sucessfully'
        ]);

      }else{
        $transDetails->approval_status=1;
        $transDetails->save();
        $customerId = $salesInvoices->customer_id;

        $customerLastInvoice = Invoice::where('customer_id', $customerId)->latest()->first();
        $customerBalance = $customerLastInvoice ? $customerLastInvoice->customer_balance : 0;

        $customer = Customers::where("id",$customerId)->first();

        $invoice=Invoice::create([
          "invoice_number" =>  $salesInvoices->invoice_number,
          "type" => "Invoice Sales",
          "amount" =>  $salesInvoices->amount,
          "customer_id" => $customerId,
          'petrol_id' =>   $customer->petrol_id,
          "customer_balance" => $customerBalance + $salesInvoices->amount,
          "posted_by" => $salesInvoices->posted_by,
          "invoice_note" => $salesInvoices->invoice_note,
          "created_at"=>$salesInvoices->created_at
        ]);



           // Step 5: Handle the messaging here after database operations
           $custsomerWholeDetails=Customers::where('id', $customerId)->first();
           $customerName = Customers::where('id', $customerId)->first()->name;
           $customerNumber = Customers::where('id', $customerId)->first()->phone;
           $processedBy = User::where('id', $salesInvoices->posted_by)->first()->fullname;
           $customerCurrentBalance = $customerBalance + $salesInvoices->amount;
           $customerPreviousBalance = $customerBalance;
           $customerCurrentBalanceFormatted = number_format($customerCurrentBalance, 2, '.', ',');
           $customerPreviousBalanceFormatted = number_format($customerPreviousBalance, 2, '.', ',');
           $petrolStation= PetrolStation::where('id',  $custsomerWholeDetails->petrol_id)->first();

           $petrolStationName = PetrolStation::where('id', $custsomerWholeDetails->petrol_id)->first()->name;

           $company=Company::where('id',$petrolStation->company_id)->first();
           $admin=User::where('company_id',$company->id)->where('petrol_id',null)->first()->phone;
           $amountPurchased =$salesInvoices->amount;
           $message = "A/C - ({$petrolStationName}) \n" .
      "Customer - {$customerName}  \n" .
       "Invoice Note: {$invoice->invoice_note} \n" .
      "- Purchase on a Credit of Ksh. {$amountPurchased} \n" .
      "- Previous bal Ksh. {$customerPreviousBalanceFormatted} \n" .
      "- New bal Ksh. {$customerCurrentBalanceFormatted} \n" .
      "- Processed by {$processedBy}";


       $data = [
           'recipient' =>"$customerNumber,$admin",
           'sender_id'=>"BookPrestig",
           'type' => 'plain',
           'message' => $message,
       ];

        $receipients="$customerNumber,$admin";

         $messageBody=Messaging::where('petrol_id', $petrolStation->id)->where('company_id', $petrolStation->	company_id)->first();
         if($messageBody){
            if (isset($data['recipient'])) {
            //   $this->sendSms($receipients,$message,$messageBody->sender_id//,$messageBody->token);
          }
          }
          DB::commit();
          return response()->json([
            'ok' => true,
            'status' => 'Success',
           'message'=>'Transaction Approved sucessfully'
          ]);
      }

    }
    catch (\Throwable $th) {
      DB::rollBack();

      return response()->json([
        'ok' => false,
        'status' => 'Failed',
        'error' => $th->getMessage(),
      ], 500);
    }
  }


  public function getUnapprovedCustomerInvoices($petrolStationId,$shiftId){
    try{

      $paginatedInvoices = DB::table('sales_pending')
        ->leftJoin('users as posted_by_user', 'sales_pending.posted_by', '=', 'posted_by_user.id')
        ->leftJoin('transactions','sales_pending.invoice_number','=','transactions.id')
        ->leftJoin('trans_details','transactions.id','=','trans_details.transaction_id')
        ->leftJoin('customers','sales_pending.customer_id','=',"customers.id")
        ->where('sales_pending.type', 'Invoice Sales')
        ->where('transactions.shift_id',$shiftId)
        ->where('trans_details.approval_status',0)
        ->select(
          'sales_pending.id',
          'sales_pending.invoice_number',
          'sales_pending.customer_balance',
          'sales_pending.invoice_note',
          'sales_pending.amount as invoice_total',
          'posted_by_user.fullname as posted_by_name',
          'sales_pending.created_at',
          'customers.name'
        )
        ->orderBy('sales_pending.created_at', 'asc')
        ->paginate(5, ['*'], 'page')
        ->withQueryString();

      // Get related products by invoice numbers
      $invoiceNumbers = $paginatedInvoices->pluck('invoice_number');

      $products = DB::table('tran_products')
        ->leftJoin('products', 'tran_products.product_id', '=', 'products.id')
        ->whereIn('tran_products.transaction_id', $invoiceNumbers)
        ->select(
          'tran_products.transaction_id as invoice_number',
          'products.name as product_name',
          'tran_products.quantity',
          'tran_products.price'
        )
        ->get()
        ->groupBy('invoice_number');

      // Combine paginated invoices with related products
      $sales_invoices = $paginatedInvoices->setCollection(
        $paginatedInvoices->getCollection()->map(function ($invoice) use ($products) {
          $invoice->products = $products->get($invoice->invoice_number, collect());
          return $invoice;
        })
      );

      return response()->json([
        'ok' => true,
        'status' => 'success',
        'transactions' => $sales_invoices,
      ]);

    } catch (\Throwable $th) {

      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Failed to retrieve customer Invoices.',
        'error' => $th->getMessage()
      ]);
    }
  }


  public function reconcileCustomer(ReconcileCustomerRequest $request, $customerId)
  {
    try {
      // Start a transaction
      DB::beginTransaction();

      // Fetch the customer
      $customer = Customers::findOrFail($customerId);

      // Create a new invoice record
      $invoice = Invoice::create([
        'type' => $request->input('type'),
        'amount' => $request->input('balance'),
        'customer_id' => $customer->id,
        'customer_balance' => $request->input('balance'),
        'posted_by' => $request->input('posted_by'),
      ]);


      // Commit the transaction
      DB::commit();

      return response()->json([
        'ok' => true,
        'status' => 'success',
        'message' => 'Customer reconciled successfully',
        'data' => $invoice,
      ]);
    } catch (\Throwable $th) {
      // Rollback the transaction if something goes wrong
      DB::rollBack();

      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Reconciliation failed: ' . $th->getMessage(),
      ]);
    }
  }

  public function customerRepayment(RepaymentRequest $request, $shiftId, $customerId)
  {
    DB::beginTransaction();
    try {
      $depositMethod = SysMeta::where('meta_key', 'deposit_account')
        ->where('meta_value', $request->input('payment.method'))
        ->first();
    //   if ($depositMethod->meta_shortcode == "mpesa") {
    //     //for mpesa we need to update the mpesa transaction
    //     //let's get the specific record in banking table
    //     $mpesaTransaction = Banking::where('reference', $request->input('payment.reference'))
    //       ->where('amount', $request->input('payment.amount'))
    //       ->where('processed_by', null)
    //       ->first();
    //     if ($mpesaTransaction == null) {
    //       return response()->json([
    //         'ok' => false,
    //         'status' => 'failed',
    //         'message' => 'Transaction not found',

    //       ], 404);
    //     }
    //     $mpesaTransaction->approval_status = true;
    //     $mpesaTransaction->shift_id = $shiftId;
    //     $mpesaTransaction->processed_by = $request->input('posted_by');
    //     $mpesaTransaction->save();
    //     $banking_id = $mpesaTransaction->id;
    //   } else {
        //for other method we need to insert in the banking table
        $shift=Shift::where('id',$shiftId)->first();
        $newBanking = Banking::create([
          "amount" => $request->input('payment.amount'),
          "processed_by" => $request->input('posted_by'),
          "shift_id" => $shiftId,
          "deposit_method" => $depositMethod->id,
          "reference"=>$request->input('payment.reference'),
          'petrol_id'=>$shift->petrol_id
        ]);
        $banking_id = $newBanking->id;
     // }
      //now let's update the invoices table then end the transaction
      $customerLastInvoice = Invoice::where('customer_id', $customerId)->latest()->first();
      $customerLastInvoice->type = "Repayment";

      $customerLastInvoice->amount = $request->input('payment.amount');
      $customerCurrentBalance = $customerLastInvoice->customer_balance - $request->input('payment.amount');
      $customerPreviousBalance = $customerLastInvoice->customer_balance;

      $customerNewRecord = Invoice::create([
        "type" => "Repayment",
        "amount" => $request->input('payment.amount'),
        "customer_id" => $customerLastInvoice->customer_id,
        "customer_balance" => $customerLastInvoice->customer_balance - $request->input('payment.amount'),
        "posted_by" => $request->input('posted_by'),
        "banking" => $banking_id
      ]);

      $paidVia=SysMeta::where('id',$depositMethod->id)->first();
      $userId=$request->input('posted_by');
      $customerDetails = Customers::where('id', $customerId)->first();
      $customerName = Customers::where('id', $customerId)->first()->name;

      $customerNumber = Customers::where('id', $customerId)->first()->phone;
      $processedBy = User::where('id', $userId)->first()->fullname;


        $customerCurrentBalanceFormatted = number_format($customerCurrentBalance, 2, '.', ',');
        $customerPreviousBalanceFormatted = number_format($customerPreviousBalance, 2, '.', ',');
        $petrolStation= PetrolStation::where('id',$customerDetails->petrol_id)->first();
        $petrolStationName = PetrolStation::where('id', $customerDetails->petrol_id)->first()->name;
        $company=Company::where('id',$petrolStation->company_id)->first();

        $admin=User::where('company_id',$company->id)->where('petrol_id',null)->first()->phone;


        $amountRepayed = $request->input('payment.amount');
        $message = "A/C - $petrolStationName \n" .
         "Customer - $customerName \n".
        "- Invoice Repayment of Ksh. $amountRepayed ($paidVia->meta_value)\n" .
        "- Previous  bal Ksh.$customerPreviousBalanceFormatted \n" .
        "- New bal Ksh. $customerCurrentBalanceFormatted \n" .
        "- Processed by $processedBy ";

        $messageBody=Messaging::where('petrol_id', $petrolStation->id)->where('company_id', $petrolStation->	company_id)->first();


    $recipient="$customerNumber,$admin";
    $message =$message;
     $data = [
                 'recipient' =>"$customerNumber,$admin"];



      // Commit the transaction
      DB::commit();
        // if($messageBody){
        //   if (isset($data['recipient'])) {
        //     $this->sendSms($recipient,$message,$messageBody->sender_id,$messageBody->token);
        // }
        // }

      return response()->json([
        'ok' => true,
        'status' => 'success',
        'message' => 'Customer repayment done successfully',

      ]);
    } catch (\Throwable $th) {
      DB::rollBack();
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Repayment failed: ' . $th->getMessage(),
      ]);
    }
  }

  public function recordCreditSales(CreditSalesRequest $request, $shiftId)
  {
    DB::beginTransaction();
    try {
      // Fetch the previous customer record
      $customerLastInvoice = Invoice::where('customer_id', $request->input('customer_id'))->latest()->first();

      // Initialize the customer balance to 0 if no previous invoice exists
      $customerBalance = $customerLastInvoice ? $customerLastInvoice->customer_balance : 0;

      // Create a new record in the invoice table
      $newInvoiceSales = Invoice::create([
        "invoice_number" => $request->input('transaction'),
        "type" => "Invoice Sales",
        "amount" => $request->input('amount'),
        "customer_id" => $request->input('customer_id'),
        "customer_balance" => $customerBalance + $request->input('amount'),
        "posted_by" => $request->input('posted_by'),
      ]);

      // Commit the transaction
      DB::commit();
      return response()->json([
        'ok' => true,
        'status' => 'success',
        'message' => 'Invoice recorded successfully',
      ]);
    } catch (\Throwable $th) {
      DB::rollBack();
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Credit Sales failed: ' . $th->getMessage(),
      ], 400);
    }
  }

  public function getLastFewTransactions(Request $request, $shiftId, $userId)
  {
    try {
      // Fetch the last 5 transactions recorded by the user within the shift, along with transaction details, products, and pump details
      $transactions = Transaction::with(['transactionDetails.pump', 'transactionDetails.transactionProduct', 'transactionDetails.transactionProduct.product'])
        ->whereHas('session', function ($query) use ($shiftId) {
          $query->where('shift_id', $shiftId);
        })
        ->whereHas('transactionDetails', function ($query) use ($userId) {
          $query->where('processed_by', $userId);
        })
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();

      return response()->json([
        'ok' => true,
        'status' => 'success',
        'data' => $transactions,
      ]);
    } catch (\Throwable $th) {
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Fetch failed: ' . $th->getMessage(),
      ], 404);
    }
  }

  public function getUnApprovedCashierTransactions($petrolStationId, $shiftId)
  {
    try {
      $unapprovedBankings = DB::table('bankings')
        ->leftJoin('users as processed_user', 'bankings.processed_by', '=', 'processed_user.id')
        ->leftJoin('users as approved_user', 'bankings.approved_by', '=', 'approved_user.id')
        ->leftJoin('shifts', 'bankings.shift_id', '=', 'shifts.id')
        ->leftJoin('sys_metas', 'bankings.deposit_method', '=', 'sys_metas.id')
        ->where('bankings.shift_id', $shiftId)
        ->where('bankings.approval_status', false)
        ->where('bankings.petrol_id', $petrolStationId)
        ->select(
          'bankings.*',
          'processed_user.fullname as processed_by_name',
          'approved_user.fullname as approved_by_name',
          'shifts.description as shift_description',
          'sys_metas.meta_value as deposit_method_name'
        )
        ->orderBy('processed_user.fullname', 'asc')
        ->paginate(10);

      return response()->json([
        'ok' => true,
        'status' => 'success',
        'transactions' => $unapprovedBankings,
      ]);
    } catch (\Throwable $th) {
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Fetch failed: ' . $th->getMessage(),
      ], 404);
    }
  }

  public function getUnApprovedBankedTransactions(Request $request, $petrolStationId)
  {
    try {
      // Get the search query parameter (can be reference, name, or amount)
      $searchQuery = $request->input('query');

      // Create a query builder for Banking model where processed_by and shift_id are null
      $query = Banking::whereNull('processed_by')
        ->whereNull('shift_id')
        ->where('petrol_id', $petrolStationId)
        ->with('depositMethod'); // Eager load the depositMethod relationship

      // Check if there is a search query and search across reference, name, or amount
      if ($searchQuery) {
        $query->where(function ($q) use ($searchQuery) {
          $q->where('reference', 'like', '%' . $searchQuery . '%')
            ->orWhere('name', 'like', '%' . $searchQuery . '%')
            ->orWhere('amount', 'like', '%' . $searchQuery . '%');
        });
      }

      // Limit the results to 5
      $unapprovedBankedTransactions = $query->limit(5)->get();

      return response()->json([
        'ok' => true,
        'status' => 'success',
        'data' => $unapprovedBankedTransactions,
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Fetch failed: ' . $th->getMessage(),
      ], 404);
    }
  }
  public function cashierApprovals(CahierApprovalRequest $request, $userId)
  {
    try {
      // Find the banking record by ID
      $banking = Banking::findOrFail($request->banking_id);

      // Update the approval status and approved_by fields
      $banking->approval_status = $request->approval_status;
      $banking->approved_by = $userId;

      // Save the changes to the database
      $banking->save();
      $message = "Transaction Accepted Successfully";
      if ($request->approval_status == 2) {
        $message = "Transaction Rejected Successfully";
      }

      return response()->json([
        'ok' => true,
        'status' => 'success',
        'message' => $message,
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Update failed: ' . $th->getMessage(),
      ], 400);
    }
  }


  public function recordBankings(BankingRequest $request, $petrolStationId)
  {
    try {
      $bankingMethod = SysMeta::where('meta_value', $request->input('deposit_method'))->where('petrol_id', $petrolStationId)->first();

      if ($request->input('reference') != null) {

        $transactionExist = Banking::where('reference', $request->input('reference'))
          ->where('amount', $request->input('amount'))
          ->first();
        if ($transactionExist != null) {
          //let's proceed to updating the record
          $transactionExist->processed_by = $request->input('processed_by');
          $transactionExist->shift_id = $request->input('shift_id');
          $transactionExist->save();
        } else {
          $bank = Banking::create([
            "reference" => $request->input('reference') ?? NULL,
            "amount" => $request->input('amount'),
            "name" => $request->input('name') ?? NULL,
            "phone" => $request->input('phone') ?? NULL,
            "processed_by" => $request->input('processed_by') ?? NULL,
            "approval_status" => false,
            "shift_id" => $request->input('shift_id'),
            'petrol_id' => $petrolStationId,
            "deposit_method" => $bankingMethod->id,
          ]);
        }
      } else {
        //now we can have the banking that do not have references inserted
        //let's create a  new record
        $bank = Banking::create([
          "amount" => $request->input('amount'),
          "processed_by" => $request->input('processed_by') ?? NULL,
          "approval_status" => false,
          "shift_id" => $request->input('shift_id'),
          'petrol_id' => $petrolStationId,
          "deposit_method" => $bankingMethod->id,
        ]);
      }


      return response()->json([
        'ok' => true,
        'status' => 'success',
        'message' => "Banking done Successfully",
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Banking failed: ' . $th->getMessage(),
      ], 400);
    }
  }

  public function recordBankingsAutomatic(Request $request)
  {
    try {
      DB::beginTransaction();
      $bankingMethod = SysMeta::where('meta_value', $request->input('deposit_method'))->first();

      // Create a new banking record using the default approval_status (assumed to be 0 or false by default)
      $bank = Banking::create([
        "reference" => $request->input('reference') ?? NULL,
        "amount" => $request->input('amount'),
        "name" => $request->input('name') ?? NULL,
        "phone" => $request->input('phone') ?? NULL,
        "approval_status" => true,
        "deposit_method" => $bankingMethod->id,
      ]);

      DB::commit();
      return response()->json([
        'ok' => true,
        'status' => 'success',
        'message' => "Banking done Successfully",
      ], 200);
    } catch (\Throwable $th) {
      DB::rollBack();
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Banking failed: ' . $th->getMessage(),
      ], 400);
    }
  }

  public function fetchShiftBanking(Request $request)
  {
    try {
      // Validate the request
      $request->validate([
        'shift_id' => 'required|integer',
        'user_id' => 'required|integer',
      ]);

      $shiftId = $request->query('shift_id');
      $userId = $request->query('user_id');

      // Fetch user and role
      $user = User::find($userId);
      if (!$user) {
        return response()->json([
          'ok' => false,
          'status' => 'error',
          'message' => 'User not found.',
        ], 404);
      }

      $role = RolesModel::find($user->role_id);
      if (!$role) {
        return response()->json([
          'ok' => false,
          'status' => 'error',
          'message' => 'Role not found for the user.',
        ], 404);
      }

      // Initialize the query builder
      $bankingQuery = Banking::where('shift_id', $shiftId)
        ->where('approval_status', true)
        ->leftJoin('users as processed_user', 'bankings.processed_by', '=', 'processed_user.id')
        ->leftJoin('users as approved_user', 'bankings.approved_by', '=', 'approved_user.id')
        ->leftJoin('sys_metas', 'bankings.deposit_method', '=', 'sys_metas.id')
        ->select(
          'bankings.amount',
          'bankings.name',
          'bankings.reference',
          'processed_user.fullname as processed_by_name',
          'approved_user.fullname as approved_by_name',
          'sys_metas.meta_value as deposit_method'
        );

      // Apply role-based filtering
      if ($role->slug === 'salesman') {
        // Filter bankings to show only those processed by the salesman
        $bankingQuery->where('bankings.processed_by', $userId);
      }

      // Paginate the results
      $bankings = $bankingQuery->paginate(5);

      // Calculate totals by deposit method (including role-based filtering)
      $totalsByDepositMethodQuery = Banking::where('shift_id', $shiftId)
        ->where('approval_status', true)
        ->leftJoin('sys_metas', 'bankings.deposit_method', '=', 'sys_metas.id')
        ->select('sys_metas.meta_value as deposit_method', \DB::raw('SUM(bankings.amount) as total_amount'))
        ->groupBy('sys_metas.meta_value');

      if ($role->slug === 'salesman') {
        $totalsByDepositMethodQuery->where('bankings.processed_by', $userId);
      }

      $totalsByDepositMethod = $totalsByDepositMethodQuery->get();

      // Calculate overall total banking amount (including role-based filtering)
      $totalBankingsAmountQuery = Banking::where('shift_id', $shiftId)
        ->where('approval_status', true);

      if ($role->slug === 'salesman') {
        $totalBankingsAmountQuery->where('bankings.processed_by', $userId);
      }

      $totalBankingsAmount = $totalBankingsAmountQuery->sum('bankings.amount');

      // Return the response
      return response()->json([
        'ok' => true,
        'status' => 'success',
        'message' => 'Banking data retrieved successfully.',
        'data' => [
          'bankings' => $bankings, // Paginated banking details
          'totals_by_method' => $totalsByDepositMethod, // Totals grouped by deposit method
          'overall_total' => $totalBankingsAmount, // Overall total amount
        ],
      ], 200);
    } catch (\Throwable $th) {

      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Retrieving bankings failed. Please try again later.',
      ], 500);
    }
  }

  public function resetBankings(ResetBankingRequest $request)
  {
    try {
      // Get the transaction based on banking_id and shift_id
      $transactionId = $request->input('banking_id');
      $shiftId = $request->input('shift_id');
      $transaction = Banking::where('id', $transactionId)->where('shift_id', $shiftId)->first();

      if (!$transaction) {
        return response()->json([
          'ok' => false,
          'status' => 'error',
          'message' => 'Transaction not found.',
        ], 404);  // 404 is more appropriate for "not found"
      }

      // Get the deposit method associated with this transaction
      $method = SysMeta::find($transaction->deposit_method); // Changed for efficiency
      if (!$method) {
        return response()->json([
          'ok' => false,
          'status' => 'error',
          'message' => 'Deposit method not found.',
        ], 404); // Return 404 if method is not found
      }

      // If the method is not 'cash', reset additional fields
      if ($method->meta_shortcode != 'cash') {
        $transaction->approval_status = false;
        $transaction->processed_by = null;
        $transaction->approved_by = null;
        $transaction->shift_id = null;
      } else {
        // If it's 'cash', just reset the approval status
        $transaction->approval_status = false;
      }

      // Save the changes to the transaction
      $transaction->save();

      return response()->json([
        'ok' => true,
        'status' => 'success',
        'message' => 'Banking transaction reset successfully.',
      ]);
    } catch (\Throwable $th) {
      // Log the exception for debugging
      Log::error('Error resetting banking transaction', [
        'error' => $th->getMessage(),
        'banking_id' => $transactionId,
        'shift_id' => $shiftId,
      ]);

      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Banking reset failed. Please try again.',
        'error' => $th->getMessage(),
      ], 500);
    }
  }

  public function getStationSessionDetails(StationSessionDetails $request)
  {
    try {
      $stationId = $request->input('station_id');
      $userId = $request->input('user_id');
      $shiftId = $request->input('shift_id');
      $petrolStationId = $request->input('petrol_id');

      // Fetch user and role
      $user = User::where('id', $userId)->first();
      $role = RolesModel::where('id', $user->role_id)->first();

      if (!$user || !$role) {
        return response()->json([
          'ok' => false,
          'status' => 'error',
          'message' => 'User or Role not found.'
        ]);
      }

      $stationDetails = [];

      // Handle different roles
      if ($role->slug === 'salesman') {
        // For 'salesman' role, fetch assignments
        $assignedStation = StationShiftModel::with(['station', 'shift', 'assignedBy', 'assignedTo'])
          ->where('assigned_to', $userId)
          ->where('shift_id', $shiftId)
          ->where('station_id', $stationId)
          ->first();

        $stationDetails[] = [
          'station' => $assignedStation ? $assignedStation->station : null,
          'assignment' => $assignedStation ? $assignedStation : null,
        ];

        return response()->json([
          'ok' => true,
          'status' => 'success',
          'message' => 'Station assignment retrieved successfully.',
          'data' => $stationDetails
        ]);
      } else {
        // For other roles, fetch stations
        $stations = Stations::where('petrol_id', $petrolStationId)
          ->where('id', $stationId)
          ->get();  // Fetch stations

        foreach ($stations as $station) {
          $assignment = StationShiftModel::with(['station', 'assignedBy', 'assignedTo', 'shift'])
            ->where('station_id', $station->id)
            ->where('shift_id', $shiftId)
            ->first(); // Fetch first assignment

          $stationDetails[] = [
            'station' => $station,
            'assignment' => $assignment ? $assignment : null,
          ];
        }

        return response()->json([
          'ok' => true,
          'status' => 'success',
          'message' => 'All stations retrieved successfully.',
          'data' => $stationDetails
        ]);
      }
    } catch (\Throwable $th) {
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Banking reset failed. Please try again.',
        'error' => $th->getMessage(),
      ], 500);
    }
  }

  public function fetchPaymentMethods($category)
  {
    try {
      $bankingMethods = SysMeta::where('meta_key', $category)->get();
      if ($bankingMethods->isEmpty()) {
        return response()->json([
          'ok' => true,
          'status' => 'success',
          'message' => "No banking methods found",
          'bankings' => []
        ], 200);
      }
      return response()->json([
        'ok' => true,
        'message' => "Banking methods fetched successfully",
        'status' => 'success',
        'bankings' => $bankingMethods
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Banking methods fetching failed: ' . $th->getMessage(),
      ], 500);
    }
  }
 







}
