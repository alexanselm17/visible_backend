<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timely Report</title>
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
                top: 10px;
                right: 10px;
            }
        }
        .content {
            margin-top: 20px;
        }
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
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
            padding-right: 10px;
        }
        .footer {
            clear: both;
            text-align: center;
            margin-top: 40px;
        }
    </style>
</head>
<!-- ... keep your <html>, <head>, and styles as they are ... -->

<body>
<div class="header">
    <h1>Campaigns Summary</h1>
    <p><b>For</b>: {{ \Carbon\Carbon::parse($startDate)->format('jS F Y, h:i A') }} - {{ \Carbon\Carbon::parse($upto)->format('jS F Y, h:i A') }}</p>
    <a href="" class="download-button">Download PDF</a>
</div>

<div class="content">
    <h2>Available Campaigns</h2>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Total Views </th>
                </tr>
            </thead>
            <tbody>
                @foreach($campaignReports as $index => $campaignReport)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $campaignReport['campaign']['name'] }}</td>
                    <td>{{ $campaignReport['total_views_all_users'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>


@if ($campaigns->hasPages())
    <div class="pagination">
        <ul class="pagination">
            {{-- Previous Page Link --}}
            <li class="{{ $campaigns->onFirstPage() ? 'disabled' : '' }}">
                <a href="{{ $campaigns->previousPageUrl() ?? '#' }}" class="prev"
                   style="{{ $campaigns->onFirstPage() ? 'pointer-events: none; color: gray;' : '' }}">
                    Previous
                </a>
            </li>

            {{-- Next Page Link --}}
            <li class="{{ !$campaigns->hasMorePages() ? 'disabled' : '' }}">
                <a href="{{ $campaigns->nextPageUrl() ?? '#' }}" class="next"
                   style="{{ !$campaigns->hasMorePages() ? 'pointer-events: none; color: gray;' : '' }}">
                    Next
                </a>
            </li>
        </ul>
    </div>
@endif


<!-- Remaining content unchanged, showing user summaries and totals -->

<div class="content">
    <h2>Ongoing Users Campaign</h2>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fullname</th>
                    <th>Campaign</th>
                    <th>Phone</th>
                    <th>Activity</th>
                </tr>
            </thead>
            <tbody>
                @foreach($summary['all_ongoing_users'] as $index => $ongoing)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $ongoing['full_name'] }}</td>
                    <td>{{ $ongoing['campaign_name'] }}</td>
                    <td>{{ $ongoing['phone'] }}</td>
                    <td>{{ $ongoing['ongoing_screenshots'] ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="content">
    <h2>Incomplete Users</h2>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fullname</th>
                    <th>Campaign</th>
                    <th>Phone</th>
                    <th>Activity</th>
                </tr>
            </thead>
            <tbody>
                @foreach($summary['all_incomplete_users'] as $index => $incomplete)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $incomplete['full_name'] }}</td>
                    <td>{{ $incomplete['campaign_name'] }}</td>
                    <td>{{ $incomplete['phone'] }}</td>
                    <td>{{ $incomplete['incomplete_screenshots'] ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="content">
    <h2>Complete Activity</h2>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fullname</th>
                    <th>Campaign</th>
                    <th>Phone</th>
                    <th>Views</th>
                </tr>
            </thead>
            <tbody>
                @foreach($summary['all_completed_users'] as $index => $complete)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $complete['full_name'] }}</td>
                    <td>{{ $complete['campaign_name'] }}</td>
                    <td>{{ $complete['phone'] }}</td>
                    <td>{{ $complete['views'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="overal-report">
    <div>
        <h2>Completed: {{ number_format($summary['total_completed'] ?? 0) }}</h2>
        <h2>Incompletes: {{ number_format($summary['total_incomplete'] ?? 0) }}</h2>
        <h2>Still Ongoing: {{ number_format($summary['total_ongoing'] ?? 0) }}</h2>
        <h2>Total Views (All Users): {{ number_format($summary['total_views_all_users']?? 0) }}</h2>
        <h2>Performance Earnings: Ksh. {{ number_format($summary['performance_earnings'] ?? 0, 2) }}</h2>
        <h2>Performance Payments: Ksh. {{ number_format($summary['performance_payments'] ?? 0, 2) }}</h2>
    </div>
</div>

<div class="footer">
    <p>&copy; {{ \Carbon\Carbon::now()->year }} {{ "Visible" }}. All rights reserved.</p>
</div>
</body>
</html>

</html>
