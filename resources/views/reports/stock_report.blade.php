<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Report</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid black; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
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
        .pagination a, .pagination span {
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
        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        .footer {
            clear: both;
            text-align: center;
            margin-top: 40px;
        }
    </style>
    <head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Stock Report</title>
    <style>
        /* Additional custom styles */
        table { width: 100%; margin: 20px 0; }
        th { background-color: #f8f9fa; }
        .footer { margin-top: 20px; font-size: 14px; }
    </style>
</head>

</head>
<body>
    <div class="header">
    <h1>Stock Report</h1>
    <h1>{{$station_name}}</h1>
    </div>
  
    <table>
    <thead>
        <tr>
        <th>#</th> 
            <th>Product Name</th>
            <th>Current Stock</th>
        </tr>
    </thead>
    <tbody>
    @foreach($stocks as $index => $stock)
            <tr>
                <td>{{ $stocks->firstItem() + $index }}</td> 
                <td>{{ $stock->product_name ?? 'N/A' }}</td> 
                <td>{{ number_format($stock->stock_quantity) }}</td>
            </tr>
            @endforeach
    </tbody>
</table>

<!-- Pagination Links -->


<div class="d-flex justify-content-center">
    {{ $stocks->appends(request()->query())->links('pagination::bootstrap-4') }}
</div>


    <div class="footer">
    <p>&copy; {{ \Carbon\Carbon::now()->year }} {{ $petrolStation->name }}. All rights reserved.</p>
    </div>
</body>
</html>
