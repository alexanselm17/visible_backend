<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Personal Timely Report</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background: #fff;
    }

    .container {
      max-width: 950px;
      margin: 0 auto;
      padding: 30px;
    }

    .header {
      text-align: center;
      margin-bottom: 30px;
    }

    h1, h2 {
      margin: 10px 0;
    }

    .content {
      margin-bottom: 30px;
    }

    .table-wrapper {
      overflow-x: auto;
      margin-top: 10px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 10px;
    }

    th, td {
      border: 1px solid #000;
      padding: 6px 10px;
      font-size: 13px;
      text-align: left;
      white-space: nowrap;
    }

    th {
      background-color: #f2f2f2;
    }

    .overal-report {
      text-align: right;
      margin-top: 40px;
      line-height: 1.6;
    }

    .footer {
      text-align: center;
      margin-top: 60px;
      font-size: 12px;
      color: #555;
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
      <h2>Completed: Ksh. {{ number_format($summary['total_completed'] ?? 0) }}</h2>
      <h2>Incompletes: Ksh. {{ number_format($summary['total_incomplete'] ?? 0) }}</h2>
      <h2>Still Ongoing: Ksh. {{ number_format($summary['total_ongoing'] ?? 0) }}</h2>
      <h2>Total Reward: {{ number_format($total_reward ?? 0) }}</h2>
      <h2>Total Payment: {{ number_format(($total_reward - $total_payment) ?? 0) }}</h2>
      <h2>Pending Payments: {{ number_format($total_payment ?? 0) }}</h2>
      <h2>Total Views: {{ number_format($summary['total_views_all_users'] ?? 0) }}</h2>
      <h2>Total Reward Awarded: Ksh. {{ number_format($summary['total_invoices'] ?? 0, 2) }}</h2>
      <h2>Account Balance (Curr): Ksh. {{ number_format($accountBalance ?? 0, 2) }}</h2>
    </div>

    <div class="footer">
      <p>&copy; {{ \Carbon\Carbon::now()->year }} Visible. All rights reserved.</p>
    </div>
  </div>
</body>
</html>
