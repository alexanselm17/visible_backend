<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Personal Report</title>
  <style>
        body {
            font-family: Arial, sans-serif;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            padding: 20px;
        }
        .download-button {
            position: absolute;
            top: 0.5;
            right: 0;
            background-color: #007bff;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        @media (max-width: 768px) {
            .header {
                padding-top: 50px;
            }

            .download-button {
                position: absolute;
                top: 10px;
                 right: 10px;
            }
      }
        .content {
            margin-top: 20px;
        }
        .table-responsive {
            overflow-x: auto;  /* Enables horizontal scrolling if needed */
            -webkit-overflow-scrolling: touch;  /* Smooth scrolling on touch devices */
            scrollbar-width: none;  /* Hide scrollbar for Firefox */
            -ms-overflow-style: none;  /* Hide scrollbar for Internet Explorer/Edge */
            margin-bottom: 20px;  /* Add spacing between tables */
        }


        .table-responsive::-webkit-scrollbar {
            display: none;
        }
        table {
            width: 100%;
            min-width: 800px;  /* Adjust this based on your table content */
            border-collapse: collapse;
            scroll-behavior: smooth;

        }
        table, th, td {
            border: 1px solid black;

        }
        th, td {
            padding: 8px;
            text-align: left;
            white-space: nowrap;

        }
        th {
            background-color: #f2f2f2;
        }
        .pagination {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            margin-top: 20px;
            list-style: none;
            font-size: 12px;
        }
        .pagination li {
            display: inline;
        }
        .pagination li a {
            padding: 3px 8px;
            text-decoration: none;
            color: #007bff;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            margin: 0 2px;
            font-size: 12px;
            line-height: 1.2;
            display: inline-block;
            text-align: center;
        }
        .pagination li a.prev, .pagination li a.next {
            font-weight: bold;
        }
        .overal-report {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-top: 10px;
            text-align: right;
            padding-right:10px;
        }
        .footer {
            clear: both;
            text-align: center;
            margin-top: 40px;
        }
    </style>
</head>

<body>
  <div class="header">
    <h1>Sales Report</h1>
    <h3>Account : {{$user->fullname}}</h3>
    <p><b>Report For</b>: {{ \Carbon\Carbon::parse($from)->format('jS F Y') }} - {{ \Carbon\Carbon::parse($to)->format('jS F Y') }}</p>


  </div>

  <div class="content">
    <h2>Purchases List</h2>
       <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Product</th>
          <th>Quantity Sold</th>
          <th>Price per Unit</th>
          <th>Gross Total</th>
          <th>Processed By</th>
          <th>Shift</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sales as $index => $sale)
        <tr>
          <td>{{ $sales instanceof \Illuminate\Pagination\LengthAwarePaginator ? $sales->firstItem() + $index : $index + 1 }}</td>
          <td>{{ $sale->product_name ?? 'N/A' }}</td>
          <td>{{ $sale->product_quantity ?? 0 }}</td>
          <td>{{ $sale->product_price ? number_format($sale->product_price, 2) : 'N/A' }}</td>
          <td>{{ $sale->product_total ? number_format($sale->product_total, 2) : 'N/A' }}</td>
          <td>{{ $sale->processed_by_name ?? 'N/A' }}</td>
          <td>{{ $sale->shift_description ?? 'N/A' }}</td>
          <td>{{ $sale->created_at ? \Carbon\Carbon::parse($sale->created_at)->format('d-m-Y H:i') : 'N/A' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
</div>
    <div class="pagination">
      <ul class="pagination">
        <li>
          <a href="{{ $sales->previousPageUrl() }}" class="prev" {{ $sales->onFirstPage() ? 'style=pointer-events:none;color:gray;' : '' }}>
            Previous
          </a>
        </li>
        <li>
          <a href="{{ $sales->nextPageUrl() }}" class="next" {{ !$sales->hasMorePages() ? 'style=pointer-events:none;color:gray;' : '' }}>
            Next
          </a>
        </li>
      </ul>
    </div>
  </div>



  <div class="overal-report">
    <div>
      <h2>Overall Report</h2>
      <p>Total Quantity: {{ $totalQuantity }} Litres</p>
      <p>Total: Ksh. {{ number_format($totalCash, 2) }}</p>
    </div>
  </div>

 <!-- PUMP Summary -->
 <div class="content">
    <h2>Pump Summary</h2>
       <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Pump Name</th>
                <th>Shift</th>
                <th>In Charge</th>
                <th>Start Volume (Litres)</th>
                <th>End Volume (Litres)</th>
                <th>Start Cash (Litres)</th>
                <th>End Cash (Litres)</th>
                <th>Quantity Sold</th>
                <th>Cash (cash-cash)</th>
                <th>Cash (price*ltrs)</th>
            </tr>
        </thead>
        <tbody>

            @foreach($pumps as $pump)


                @php
                    $totalPumpQuantity = $pump->allSales->sum(function ($sale) {
                        return $sale->transactionProduct->quantity ?? 0;
                    });
                    $totalPumpCash = $pump->allSales->sum(function ($sale) {
                        return $sale->gross_total ?? 0;
                    });
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{  $pump->my_pump->name }}</td>
                   <td> {{ $pump->shift->description ?? 'N/A' }}</td>
                    <td>{{  $pump->in_charge->fullname  ?? 'N/A' }}</td>
                    <td>{{$pump->start_volume ?? 'N/A' }}</td>
                    <td>{{$pump->ended_volume ?? 'N/A' }}</td>
                    <td>{{ is_numeric($pump->start_cash) ? number_format($pump->start_cash, 2) : $pump->start_cash ?? 'N/A' }}</td>
                    <td>{{ is_numeric($pump->ended_cash) ? number_format($pump->ended_cash, 2) : $pump->ended_cash  ?? 'N/A'}}</td>
                    <td>{{ $pump->ended_volume != null
        ? number_format($pump->ended_volume  - $pump->start_volume, 2)
        : '0.00' }}</td>

                    <td>{{$pump->ended_cash != null
        ? number_format($pump->ended_cash - $pump->start_cash, 2)
        : '0.00' }}</td>
                    <td>{{$pump->ended_cash != null
        ? number_format(($pump->ended_volume - $pump->start_volume)*$pump->price, 2)
        : '0.00'}}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>

  <div class="content">
    <h2>Invoice Sales</h2>
       <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Customer</th>
          <th>Invoice Number</th>
          <th>Invoice Note</th>
          <th>Product</th>
          <th>Total</th>
          <th>Processed By</th>
          <th>Shift</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoiceSales as $index => $invoice)
        <tr>

          <td>{{ $invoiceSales->firstItem() + $index }}</td>
          <td>{{ $invoice->customerName ?? 'N/A' }}</td>
          <td>{{ $invoice->invoice_number }}</td>
          <td>{{ $invoice->invoice_note }}</td>
          <td>{{ $invoice->productName ?? 'N/A' }}</td>
          <td>{{ number_format($invoice->amount, 2) }}</td>
          <td>{{ $invoice->fullname ?? 'N/A' }}</td>
          <td>{{ $invoice->shift }}</td>
          <td>{{ $invoice->created_at->format('d-m-Y H:i') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
</div>

    <div class="pagination">
      <ul class="pagination">
        {{-- Previous Page Link for Invoice Sales --}}
        <li>
          <a href="{{ $invoiceSales->previousPageUrl() }}" class="prev"
            {{ $invoiceSales->onFirstPage() ? 'style=pointer-events:none;color:gray;' : '' }}>
            Previous
          </a>
        </li>

        {{-- Next Page Link for Invoice Sales --}}
        <li>
          <a href="{{ $invoiceSales->nextPageUrl() }}" class="next"
            {{ !$invoiceSales->hasMorePages() ? 'style=pointer-events:none;color:gray;' : '' }}>
            Next
          </a>
        </li>
      </ul>
    </div>





    <!-- Invoice Repayments -->
    <div class="content">
      <h2>Invoice Repayments</h2>
         <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Amount</th>
            <th>Processed By</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          @foreach($invoiceRepayments as $index => $invoice)
          <tr>
            <td>{{ $invoiceSales->firstItem() + $index }}</td>
            <td>{{ $invoice->customerName ?? 'N/A' }}</td>
            <td>{{ number_format($invoice->amount, 2) }}</td>
            <td>{{ $invoice->fullname ?? 'N/A' }}</td>
            <td>{{ $invoice->created_at->format('d-m-Y H:i') }}</td>


          </tr>
          @endforeach
        </tbody>
      </table>
</div>
      <div class="pagination">
        <ul class="pagination">
          <li>
            <a href="{{ $invoiceSales->previousPageUrl() }}" class="prev" {{ $invoiceRepayments->onFirstPage() ? 'style=pointer-events:none;color:gray;' : '' }}>
              Previous
            </a>
          </li>
          <li>
            <a href="{{ $invoiceSales->nextPageUrl() }}" class="next" {{ !$invoiceRepayments->hasMorePages() ? 'style=pointer-events:none;color:gray;' : '' }}>
              Next
            </a>
          </li>
        </ul>
      </div>
    </div>
    <div class="overal-report">
      <div>
        <h2>Invoice Repayment</h2>
        <p>Total: Ksh. {{ number_format($totalAmountRepaid, 2) }}</p>
      </div>
    </div>

    <!-- Bankings -->

    <div class="content">
      <h2>Bankings</h2>
         <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Account</th>
            <th>Amount</th>
            <th>Reference</th>
            <th>Name</th>
            <th>Processed By</th>
            <th>Approved By</th>
          </tr>
        </thead>
        <tbody>
          @foreach($bankings as $index => $banking)
          <tr>
            <td>{{ $bankings->firstItem() + $index }}</td>
            <td>{{ $banking->deposit_method ?? 'N/A' }}</td>
            <td>{{ number_format($banking->amount, 2) }}</td>
            <td>{{ $banking->reference ?? 'N/A'  }}</td>
            <td>{{ $banking->name ?? 'N/A' }}</td>
            <td>{{ $banking->processed_by_name ?? 'N/A' }}</td>
            <td>{{ $banking->approved_by_name ?? 'N/A' }}</td>

          </tr>
          @endforeach
        </tbody>
      </table>
</div>
      <div class="pagination">
        <ul class="pagination">
          {{-- Previous Page Link --}}
          <li>
            <a href="{{ $bankings->previousPageUrl() }}" class="prev"
              {{ $bankings->onFirstPage() ? 'style=pointer-events:none;color:gray;' : '' }}>
              Previous
            </a>
          </li>

          {{-- Next Page Link --}}
          <li>
            <a href="{{ $bankings->nextPageUrl() }}" class="next"
              {{ !$bankings->hasMorePages() ? 'style=pointer-events:none;color:gray;' : '' }}>
              Next
            </a>
          </li>
        </ul>
      </div>


      <div class="overal-report">
        <div>
          <h2>Overall Bankings</h2>
          <p>Total: Ksh. {{ number_format($totalBankingsAmount, 2) }}</p>
          <div>
            @foreach($totalsByDepositMethod as $index => $banking)
            <p><b>{{$banking->deposit_method}}</b>: Ksh. {{ number_format($banking->total_amount, 2) }}</p>
            @endforeach
          </div>
        </div>
      </div>







      <div class="content">
        <h2>Station Sales Details</h2>

        @foreach($stations as $station)
        <div class="station-summary">

          <h3>Station: {{ $station->station_name }} - {{$station->shift_name}}</h3>
          <div class="shift-details" style="position: relative; padding-bottom: 80px;">

            @if($station->products->isNotEmpty())
               <div class="table-responsive">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Product</th>
                  <th>Opening Stock</th>
                  <th>Closing Stock</th>
                  <th>Sold</th>
                  <th>Price</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach($station->products as $index => $product)
                <tr>
                  <td>{{ $index + 1 }}</td>
                  <td>{{ $product->product->name ?? 'N/A' }}</td>
                  <td>{{ $product->opening_stock ?? 'N/A' }}</td>
                  <td>{{ $product->closing_stock ?? $product->opening_stock }}</td>
                  <td>
                    @if(isset($product->closing_stock))
                    {{ $product->opening_stock - $product->closing_stock }}
                    @else
                    0
                    @endif
                  </td>
                  <td>{{ isset($product->price) ? number_format($product->price, 2) : 'N/A' }}</td>
                  <td>
                    @if(isset($product->closing_stock))
                    {{ number_format(($product->opening_stock - $product->closing_stock) * $product->price, 2) }}
                    @else
                    {{ number_format(0, 2) }}
                    @endif
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
</div>
            <!-- Station-specific Pagination -->
            <div class="pagination">
              <ul class="pagination">
                {{-- Previous Page Link --}}
                <li>
                  <a href="{{ $station->products->previousPageUrl() }}" class="prev"
                    {{ $station->products->onFirstPage() ? 'style=pointer-events:none;color:gray;' : '' }}>
                    Previous
                  </a>
                </li>

                {{-- Next Page Link --}}
                <li>
                  <a href="{{ $station->products->nextPageUrl() }}" class="next"
                    {{ !$station->products->hasMorePages() ? 'style=pointer-events:none;color:gray;' : '' }}>
                    Next
                  </a>
                </li>
              </ul>
            </div>

            @else
            <p>No product data available for this station.</p>
            @endif
          </div>

          <div class="overal-report">
            <div class="station-summary">
              <h3>Station: {{ $station->station_name }}</h3>
              <p><strong>Total Sales:</strong> Ksh. {{ number_format($station->total_sale_station, 2) }}</p>
            </div>



            <p><strong>Shift:</strong> {{ $station->shift_name }}</p>
            <p><strong>Assigned By:</strong> {{ $station->assigned_by_name ?? 'N/A' }}</p>
            <p><strong>Assigned To:</strong> {{ $station->assigned_to_name ?? 'N/A' }}</p>
          </div>
        </div>
        @endforeach
      </div>

      <br><br>
      <br><br>
      <br><br>


<!--Expenses table  -->

<div class="content">
    <h2>Expenses</h2>
    <div class="table-responsive">
       <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Amount</th>
                <th>Description</th>
                <th>Posted By</th>
                <th>Approved by</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
        @foreach($expenses as $index =>  $expense)
            <tr>
                <td>{{ $expenses->firstItem() + $index }}</td>
                <td>{{ number_format($expense->amount, 2) }}</td>
                <td>{{ $expenses->description ?? 'N/A'  }}</td>
                <td>{{ $expense->processed_by_name ?? 'N/A' }}</td>
                <td>{{ $expense->approved_by_name ?? 'N/A' }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>

    <div class="pagination">
<ul class="pagination">
    {{-- Previous Page Link --}}
    <li>
        <a href="{{ $expenses->previousPageUrl() }}" class="prev"
           {{ $expenses->onFirstPage() ? 'style=pointer-events:none;color:gray;' : '' }}>
            Previous
        </a>
    </li>

    {{-- Next Page Link --}}
    <li>
        <a href="{{ $expenses->nextPageUrl() }}" class="next"
           {{ !$expenses->hasMorePages() ? 'style=pointer-events:none;color:gray;' : '' }}>
            Next
        </a>
    </li>
</ul>
</div>


<div class="overal-report">
    <div>
        <h2>Overal Expenses</h2>
        <p>Total: Ksh. {{ number_format($totalExpenses, 2) }}</p>
    </div>
</div>



<!-- END of Expenses  -->

<!--  Discounts -->

 <div class="content">
    <h2>Discounts</h2>
    <div class="table-responsive">
       <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Amount</th>
                <th>Total Purchased</th>
                <th>Customer</th>
                <th>Posted By</th>
                <th>Approved by</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
        @foreach($discounts as $index =>  $discount)
            <tr>
                <td>{{ $discounts->firstItem() + $index }}</td>
                <td>{{ number_format($discount->amount, 2) }}</td>
                <td>{{ $discount->total_purchased ?? 'N/A'  }}</td>
                <td>{{ $discount->customer_name ?? 'N/A' }}</td>
                <td>{{ $discount->processed_by_name ?? 'N/A' }}</td>
                <td>{{ $discount->approved_by_name ?? 'N/A' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>

    <div class="pagination">
<ul class="pagination">
    {{-- Previous Page Link --}}
    <li>
        <a href="{{ $discounts->previousPageUrl() }}" class="prev"
           {{ $discounts->onFirstPage() ? 'style=pointer-events:none;color:gray;' : '' }}>
            Previous
        </a>
    </li>

    {{-- Next Page Link --}}
    <li>
        <a href="{{ $discounts->nextPageUrl() }}" class="next"
           {{ !$discounts->hasMorePages() ? 'style=pointer-events:none;color:gray;' : '' }}>
            Next
        </a>
    </li>
</ul>
</div>


<div class="overal-report">
    <div>
        <h2>Overal Discounts</h2>
        <p>Total: Ksh. {{ number_format($totalDiscounts, 2) }}</p>
    </div>
</div>
<!-- End of Discounts -->



      <div class="overal-report">
        <div>
          <h2>Shift Summary</h2>
          <p>Sales: Ksh. {{ number_format($totalSales, 2) }}</p>
          <p>Invoices: Ksh. {{ number_format($totalInvoiceAmount, 2) }}</p>
          <p>Invoice Repayment: Ksh. {{ number_format($totalAmountRepaid, 2) }}</p>
          <p>Expected: Ksh. {{ number_format($totalSales+$totalAmountRepaid-$totalInvoiceAmount, 2) }}</p>
          <p>Received: Ksh. {{ number_format($totalBankingsAmount, 2) }}</p>
          <p>Diff: Ksh. {{ number_format($totalBankingsAmount - ($totalSales+$totalAmountRepaid-$totalInvoiceAmount), 2) }}</p>
        </div>
      </div>
    </div>


</body>

</html>
