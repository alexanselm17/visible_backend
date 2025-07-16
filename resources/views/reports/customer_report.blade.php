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
        <h1>Customer Sales Report</h1>
        <p>
        <h3>{{ $customer['name'] }} ({{ $customer['phone'] }})</h3>
        </p>
        <p></p>
        <p><b>Report For</b>: {{ \Carbon\Carbon::parse($from)->format('jS F Y') }} - {{ \Carbon\Carbon::parse($to)->format('jS F Y') }}</p>
       
        <a href="{{ route('download.customer_report', ['customer_id' => $customerId, 'from' => $from, 'to' => $to, 'petrol_id' => $petrolId]) }}" class="download-button">Download PDF</a>

       
    </div>

    <div class="content">
        <h2>Sales Invoices</h2>
        <div class="table-responsive">
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
                $counter = $sales_invoices->firstItem() + $index;
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
</div>
        <!-- Sales Invoices Pagination -->
        <ul class="pagination">
            @if ($sales_invoices->onFirstPage())
            <li class="disabled"><span>&laquo; Previous</span></li>
            @else
            <li><a href="{{ $sales_invoices->appends(['customer_id' => request('customer_id'), 'from' => request('from'), 'to' => request('to')])->previousPageUrl() }}" rel="prev">&laquo; Previous</a></li>
            @endif

            @if ($sales_invoices->hasMorePages())
            <li><a href="{{ $sales_invoices->appends(['customer_id' => request('customer_id'), 'from' => request('from'), 'to' => request('to')])->nextPageUrl() }}" rel="next">Next &raquo;</a></li>
            @else
            <li class="disabled"><span>Next &raquo;</span></li>
            @endif
        </ul>

    </div>

    <div class="content">
        <h2>Transactions</h2>
        <div class="table-responsive">
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
                    <td>{{ $repayments->firstItem() + $index }}</td>
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
</div>
        <!-- Repayments Pagination -->
        <ul class="pagination">
            @if ($repayments->onFirstPage())
            <li class="disabled"><span>&laquo; Previous</span></li>
            @else
            <li><a href="{{ $repayments->previousPageUrl() }}" rel="prev">&laquo; Previous</a></li>
            @endif

            @if ($repayments->hasMorePages())
            <li><a href="{{ $repayments->nextPageUrl() }}" rel="next">Next &raquo;</a></li>
            @else
            <li class="disabled"><span>Next &raquo;</span></li>
            @endif
        </ul>
    </div>

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