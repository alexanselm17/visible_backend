
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        .download-button {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #007bff;
            color: white;
            padding: 3px 5px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        .content {
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 4px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .pagination {
            display: flex;
            justify-content: space-between;
            padding: 5px;
            margin-top: 5px;
            list-style: none;
            font-size: 12px;
        }
        .pagination li {
            display: inline;
        }
        .pagination li a {
            padding: 3px 4px;
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
            padding-top: 5px;
            text-align: right;
            padding-right: 5px;
        }
        .footer {
            clear: both;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
    <h1>{{ $petrolStation->name }}</h1>
        <h2>Sales Report</h2>
        <p>Shift: {{ $shift->description }}</p>
        <p>Date: {{ $shift->created_at->format('d-m-Y') }}</p>
        <p>Status: {{ $shift->ended_at ? 'Success at ' . $shift->ended_at->format('H:i:s') : 'Ongoing' }}</p>

 </div>

    <div class="content" style="font-family: Times New Roman; font-size: 12px;"> 
  
        <h2>Sales List</h2>
        <table>
    <thead>
        <tr>
            <th>#</th>
            <th>Product</th>
            <th>Quantity Sold</th>
            <th>Price per Unit</th>
            <th>Gross Total</th>
            <th>Processed By</th>
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
            <td>{{ $sale->created_at ? \Carbon\Carbon::parse($sale->created_at)->format('d-m-Y H:i') : 'N/A' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>



    <div class="overal-report">
        <div>
            <h2>Overall Report</h2>
            <p>Total: Ksh. {{ number_format($totalCash, 2) }}</p>
        </div>
    </div>
   
    <!-- Drum Stock Summary Table -->
    <div class="content">
        <h2>Tank Stock Summary</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tank Name</th>
                    <th>Product</th>
                    <th>Start Volume (Litres)</th>
                    <th>End Volume (Litres)</th>
                    <th>Quantity Sold</th>
                    <th>Cash</th>
                </tr>
            </thead>
            <tbody>
                @foreach($drumSessionDetails as $index => $drumDetail)
               
             
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $drumDetail->drum->name }}</td>
                        <td>{{ optional($drumDetail->drum->product)->name ?? 'N/A' }}</td>
                        <td>{{ number_format($drumDetail->start_volume,2) ?? '0.00' }}</td>
                        <td>{{ number_format($drumDetail->ended_volume,2) ?? 'N\A' }}</td>
                        <td>
    {{ $drumDetail->ended_volume != null 
        ? number_format(-$drumDetail->ended_volume + $drumDetail->start_volume, 2) 
        : '0.00' }} 
                        </td>
                        <td>{{ number_format((-$drumDetail->ended_volume + $drumDetail->start_volume)*$drumDetail->price,2) ?? 'N\A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
   

  <div class="content">
    <h2>Pump Summary</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Pump Name</th>
                <th>Product</th>
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
                    <td>{{ $pump->name }}</td>
                    <td>{{ $pump->drum->product->name ?? 'N/A' }}</td>
                    <td>{{ $pump->sessionDetails->fullname ?? 'N/A' }}</td>
                    <td>{{ $pump->sessionDetails->start_volume ?? 'N/A' }}</td>
                    <td>{{ $pump->sessionDetails->ended_volume ?? 'N/A' }}</td>
                    <td>{{ isset($pump->sessionDetails->start_cash) ? number_format($pump->sessionDetails->start_cash, 2) : 'N/A' }}</td>
                    <td>{{ isset($pump->sessionDetails->ended_cash) ? number_format($pump->sessionDetails->ended_cash, 2) : 'N/A' }}</td>
                    <td>{{ $pump->sessionDetails && $pump->sessionDetails->ended_volume != null 
        ? number_format($pump->sessionDetails->ended_volume - $pump->sessionDetails->start_volume, 2) 
        : '0.00' }}</td> 
        
                    <td>{{$pump->sessionDetails && $pump->sessionDetails->ended_cash != null 
        ? number_format($pump->sessionDetails->ended_cash - $pump->sessionDetails->start_cash, 2) 
        : '0.00' }}</td> 
                    <td>{{$pump->sessionDetails && $pump->sessionDetails->ended_cash != null 
        ? number_format(($pump->sessionDetails->ended_volume - $pump->sessionDetails->start_volume)*$pump->drum->product->selling_price, 2) 
        : '0.00'}}</td> 
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

   


    <div class="content">
        <h2>Invoice Sales</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Invoice Number</th>
                    <th>Product</th>
                    <th>Total</th>
                    <th>Processed By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
    
            @foreach($invoiceSales as $index => $invoice)

            <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $invoice->customerName ?? 'N/A' }}</td>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->productName ?? 'N/A' }}</td>
                    <td>{{ number_format($invoice->amount, 2) }}</td>
                    <td>{{ $invoice->fullname ?? 'N/A' }}</td>
                    <td>{{ $invoice->created_at->format('d-m-Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>


     

      
    <div class="overal-report">
        <div>
            <h2>Overall Invoice Sales</h2>
            <p>Total: Ksh. {{ number_format($totalInvoiceAmount, 2) }}</p>
        </div>
    </div>

    

      <!-- Invoice Repayments -->
      <div class="content">
        <h2>Invoice Repayments</h2>
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
                     <td>{{ $index + 1 }}</td>
                    <td>{{ $invoice->customerName ?? 'N/A' }}</td>
                    <td>{{ number_format($invoice->amount, 2) }}</td>
                    <td>{{ $invoice->fullname ?? 'N/A' }}</td>
                    <td>{{ $invoice->created_at->format('d-m-Y H:i') }}</td>
                 
                    
                </tr>
            @endforeach
            </tbody>
        </table>
       

       
    <div class="overal-report">
        <div>
            <h2>Invoice Repayment</h2>
            <p>Total: Ksh. {{ number_format($totalAmountRepaid, 2) }}</p>
        </div>
    </div>

     <!-- Bankings -->

    <div class="content">
        <h2>Bankings</h2>
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
            @foreach($bankings as $index =>  $banking)
                <tr>
                <td>{{ $index + 1 }}</td>
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



    <div class="overal-report">
        <div>
            <h2>Overall Bankings</h2>
            <p>Total: Ksh. {{ number_format($totalBankingsAmount, 2) }}</p>
            <div>
            @foreach($totalsByDepositMethod as $index =>  $banking)
            <p><b>{{$banking->deposit_method}}</b>: Ksh. {{ number_format($banking->total_amount, 2) }}</p>
            @endforeach
            </div>
        </div>
    </div>





    <div class="content">
    <h2>Station Sales Details</h2>

    @foreach($stations as $station)
    <div class="station-summary">
    
    <h3>Station: {{ $station->station_name }}</h3>
        <div class="shift-details" style="position: relative; padding-bottom: 80px;">

            @if($station->products->isNotEmpty())
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





<div>
    <h2>General Sales Summary</h2>
<table border="1" cellpadding="10" cellspacing="0">
    <thead>
        <tr>
            <th>Product</th>
            <th>Total Cash(Cash Diff)</th>
            <th>Total Volume</th>
            <th>Volume * Price</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($productPumpSales as $product => $sales)
            <tr>
                <td>{{ $product }}</td>
                <td>{{ number_format($sales['total_cash'], 2, '.', ',') }}</td>
                <td>{{ number_format($sales['total_volume'], 2, '.', ',') }}</td>
                <td>{{ number_format($sales['selling_price']*$sales['total_volume'], 2, '.', ',') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>






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

    <div class="footer">
        <p>&copy; {{ \Carbon\Carbon::now()->year }} {{ $petrolStation->name }}. All rights reserved.</p>
    </div>
</body>
</html>