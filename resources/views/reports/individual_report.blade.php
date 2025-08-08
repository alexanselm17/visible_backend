<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Personal Timely Report</title>
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
<body>
  <div class="container">
    <div class="header">
      <h1>Account: {{ $user['fullname'] }}</h1>
      <p><strong>For:</strong> {{ \Carbon\Carbon::parse($startDate)->format('jS F Y, h:i A') }} - {{ \Carbon\Carbon::parse($upto)->format('jS F Y, h:i A') }}</p>
    </div>

    <div class="content">
      <h2>Ongoing Campaign</h2>
      <div class="table-wrapper">
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
            @foreach($summary['user_ongoing'] as $index => $ongoing)
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
      <h2>Incomplete Campaigns</h2>
      <div class="table-wrapper">
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
            @foreach($summary['user_incomplete'] as $index => $incomplete)
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
      <h2>Completed Campaigns</h2>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Fullname</th>
              <th>Campaign</th>
              <th>Phone</th>
              <th>Views</th>
              <th>Rewards (KES)</th>
            </tr>
          </thead>
          <tbody>
            @foreach($summary['user_completed'] as $index => $complete)
            <tr>
              <td>{{ $index + 1 }}</td>
              <td>{{ $complete['full_name'] }}</td>
              <td>{{ $complete['campaign_name'] }}</td>
              <td>{{ $complete['phone'] }}</td>
              <td>{{ $complete['views'] }}</td>
              <td>{{ $complete['reward'] }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="content">
      <h2>Account Activity</h2>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Balance</th>
              <th>References</th>
              <th>Campaign</th>
            </tr>
          </thead>
          <tbody>
            @foreach($invoicingActivity as $index => $billing)
            <tr>
              <td>{{ $index + 1 }}</td>
              <td>{{ $billing['type'] }}</td>
              <td>{{ $billing['amount'] }}</td>
              <td>{{ $billing['customer_balance'] }}</td>
              <td>{{ $billing['reference'] ?? 'N/A' }}</td>
              <td>{{ $billing['advert_name'] ?? 'N/A' }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="overal-report">
      <h2>Completed:  {{ number_format($summary['total_completed'] ?? 0) }}</h2>
      <h2>Incompletes:  {{ number_format($summary['total_incomplete'] ?? 0) }}</h2>
      <h2>Still Ongoing:  {{ number_format($summary['total_ongoing'] ?? 0) }}</h2>
      <h2>Total Reward: {{ number_format($total_reward ?? 0) }}</h2>
      <h2>Total Payment: {{ number_format(($total_reward - $total_payment) ?? 0) }}</h2>
      <h2>Pending Payments: Ksh.{{ number_format($accountBalance ?? 0) }}</h2>
      <h2>Total Views: {{ number_format($summary['total_views_all_users'] ?? 0) }}</h2>
      <h2>Account Balance (Curr): Ksh. {{ number_format($accountBalance ?? 0, 2) }}</h2>
    </div>

    <div class="footer">
      <p>&copy; {{ \Carbon\Carbon::now()->year }} Visible. All rights reserved.</p>
    </div>
  </div>
</body>
</html>
