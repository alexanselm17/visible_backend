<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Sales Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .content {
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table,
        th,
        td {
            border: 1px solid black;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 10px;
            list-style: none;
            padding: 0;
        }

        .pagination li {
            margin: 0 5px;
        }

        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 6px 12px;
            font-size: 14px;
            color: #007bff;
            text-decoration: none;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .pagination a:hover {
            background-color: #007bff;
            color: white;
        }

        .pagination .disabled span {
            color: #6c757d;
            pointer-events: none;
            background-color: #e9ecef;
        }

        .overal-report {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-top: 10px;
            text-align: right;
            padding-right: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Customer Sales Report</h1>
        <p>
        <h3>{{ $customer['name'] }} ({{ $customer['phone'] }})</h3>
        </p>
        <p></p>
        <p><b>Report For</b>: {{ \Carbon\Carbon::parse($from)->format('jS F Y') }} - {{ \Carbon\Carbon::parse($to)->format('jS F Y') }}</p>
       
    </div>
   
    <div class="content">
        <h2>Sales Invoices</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Invoice Number</th>
                    <th>Invoice Note</th>
                    <th>Product Name</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                    <th>Invoice Total</th>
                    <th>Customer Balance</th>
                    <th>Processed By</th>
                    <th>Date</th>
                </tr>
            </thead>
         
            <tbody>
                @foreach($sales_invoices as $index => $invoice)
                @php
                $productCount = $invoice->products->count();
                $counter = $index + 1; 
                
                @endphp
              
          
                <tr>
                    <td rowspan="{{ $productCount }}">{{ $counter }}</td>
                    <td rowspan="{{ $productCount }}">{{ $invoice->invoice_number }}</td>
                    <td rowspan="{{ $productCount }}">{{ $invoice->invoice_note }}</td>

                    @if($invoice->products->isNotEmpty())
                    @php $firstProduct = $invoice->products->first(); @endphp
                    <td>{{ $firstProduct->product_name }}</td>
                    <td>{{ $firstProduct->quantity }}</td>
                    <td>{{ number_format($firstProduct->price, 2) }}</td>
                    <td>{{ number_format($firstProduct->quantity * $firstProduct->price, 2) }}</td>
                    @else
                    <td colspan="4">No products</td>
                    @endif
                    <td rowspan="{{ $productCount }}">{{ $invoice->invoice_total }}</td>

                    <td rowspan="{{ $productCount }}">{{ $invoice->customer_balance ?? 'N/A' }}</td>
                    <td rowspan="{{ $productCount }}">{{ $invoice->posted_by_name }}</td>
                    <td rowspan="{{ $productCount }}">{{ \Carbon\Carbon::parse($invoice->created_at)->format('d-m-Y') }}</td>
                </tr>
               
                @foreach($invoice->products->skip(1) as $product)
                <tr>
                    <td>{{ $product->product_name }}</td>
                    <td>{{ $product->quantity }}</td>
                    <td>{{ number_format($product->price, 2) }}</td>
                    <td>{{ number_format($product->quantity * $product->price, 2) }}</td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
      
        <!-- Sales Invoices Pagination -->
       

    <div class="content">
    <h2>Transactions</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Banking</th>
                    <th>Customer Balance</th>
                    <th>Processed By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
        
                
            @foreach($repayments as $index => $repayment)
    <tr>
        <td>{{ $index + 1 }}</td> 
        <td>{{ $repayment->type ?? 'N/A' }}</td>
        <td>{{ number_format($repayment->amount, 2) }}</td>
     
        <td>{{ $repayment->deposit_method ?? 'N/A' }}</td>
        <td>{{ number_format($repayment->customer_balance, 2) }}</td>
        <td>{{ $repayment->posted_by_name ?? 'N/A' }}</td>
        <td>{{ \Carbon\Carbon::parse($repayment->created_at)->format('d-m-Y') }}</td>
    </tr>
@endforeach


           

            </tbody>
        </table>

      
        
    <div class="overal-report">
        <div>
            <h2>Current Customer Balance: Ksh. {{ number_format($currentCustomerBalance->customer_balance ?? 0, 2) }}</h2>
            <h2>Balance As at {{ \Carbon\Carbon::parse($to)->format('jS F Y') }}: Ksh. {{ number_format($balanceAsAtSelectedDate->customer_balance ?? 0, 2) }}</h2>
        </div>
    </div>

    <div class="footer">
        <p>&copy; {{ \Carbon\Carbon::now()->year }} {{ $petrolStation->name }}. All rights reserved.</p>
    </div>
</body>

</html>