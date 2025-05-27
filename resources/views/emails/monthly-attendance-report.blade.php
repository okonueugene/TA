<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Attendance Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #0056b3;
            font-size: 24px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        p {
            margin-bottom: 10px;
        }
        .highlight {
            font-weight: bold;
            color: #007bff;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        ul li {
            margin-bottom: 5px;
        }
        .warning {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Monthly Attendance Report</h1>

        <p>Dear Team,</p>

        <p>Please find attached the monthly attendance report for <span class="highlight">{{ $month }} {{ $year }}</span>.</p>

        <ul>
            <li><strong>Report Period:</strong> {{ $reportStartDate }} to {{ $reportEndDate }}</li>
            <li><strong>Total Days in Period:</strong> {{ $totalDays }}</li>
            <li><strong>Generated On:</strong> {{ $generatedAt }}</li>
            {{-- Removed searchFilter condition --}}
            <li><strong>Attachment Name:</strong> <span class="highlight">{{ $fileName }}</span></li>
        </ul>

        @if($isCurrentMonth)
            <p class="warning">Note: This report covers attendance up to the previous day ({{ $reportEndDate }}), as it was generated on {{ \Carbon\Carbon::now()->format('F j, Y') }}.</p>
        @else
            <p>This report covers the full month of {{ $month }} {{ $year }}.</p>
        @endif

        <p>If you have any questions or require further details, please do not hesitate to contact us.</p>

        <p>Thank you,</p>
        <p>Your HR/Admin Team</p>

        <div class="footer">
            <p>This is an automated email. Please do not reply.</p>
            <p>&copy; {{ date('Y') }} McDave Holdings Limited. All rights reserved.</p>
        </div>
    </div>
</body>
</html>