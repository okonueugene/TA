<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Attendance Sync Restored</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-color: #27ae60; /* A vibrant green for success */
            --secondary-color: #2c3e50; /* Dark blue/grey for text */
            --light-bg: #f5f7fa; /* A very light blue-grey background */
            --font-color: #34495e; /* Slightly darker body font color */
            --muted-color: #7f8c8d; /* Muted color for footer/secondary text */
            --success-bg: #e6ffee; /* Light green for success box background */
            --success-border: #82e0aa; /* Slightly darker green for success box border */
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--light-bg);
            font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: var(--font-color);
            line-height: 1.6;
        }

        .email-wrapper {
            max-width: 600px;
            margin: 40px auto;
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }

        .logo-container {
            text-align: center;
            padding: 20px;
            background-color: #ffffff;
            border-bottom: 1px solid #f0f0f0;
        }

        .logo-container img {
            max-height: 50px;
            width: auto;
            display: block;
            margin: 0 auto;
        }

        .email-header {
            background-color: var(--primary-color); /* Green header */
            color: white;
            padding: 25px 20px;
            text-align: center;
        }

        .email-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .email-header h2 span {
            font-size: 28px;
            line-height: 1;
        }

        .email-body {
            padding: 30px;
        }

        .email-body p {
            margin-bottom: 15px;
            font-size: 16px;
        }

        .email-body p:last-of-type {
            margin-bottom: 0;
        }

        .success-box { /* Renamed from .error-box */
            background-color: var(--success-bg); /* Light green background */
            border-left: 5px solid var(--primary-color); /* Green left border */
            border-radius: 5px;
            padding: 20px;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
            font-size: 14px;
            color: #21613d; /* Darker green text for success details */
            white-space: pre-wrap;
            word-break: break-word;
            margin-top: 20px;
            margin-bottom: 25px;
            line-height: 1.5;
            overflow: auto;
            max-height: 400px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); /* Lighter shadow for success */
            text-align: left;
        }

        .email-footer {
            font-size: 13px;
            color: #616161;
            text-align: center;
            padding: 20px;
            background-color: #eceff1;
            border-top: 1px solid #e0e0e0;
        }

        .email-footer a {
            color: var(--muted-color);
            text-decoration: none;
        }

        .email-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                margin: 20px auto;
                border-radius: 0;
                box-shadow: none;
                border: none;
            }

            .email-body,
            .email-header,
            .email-footer,
            .logo-container {
                padding: 15px;
            }

            .email-header h2 {
                font-size: 20px;
            }

            .email-header h2 span {
                font-size: 24px;
            }

            .email-body p {
                font-size: 15px;
            }

            .success-box { /* Apply responsive styles to success-box */
                padding: 15px;
                font-size: 13px;
            }

            .logo-container img {
                max-height: 40px;
            }
        }
    </style>
</head>

<body>
    <div class="email-wrapper">
        <div class="logo-container">
            <img src="https://raw.githubusercontent.com/okonueugene/TA/86e1e59cab915106be31c37ce9f8d1b38368c591/public/theme/images/logo.png"
                alt="McDave Holdings LLC Logo">
        </div>

        <div class="email-header" style="background-color: #27ae60; color: white;">
            <h2><span>&#x2705;</span> Attendance Sync Restored</h2>
        </div>
        <div class="email-body">
            <p>The scheduled attendance sync operation has been successfully restored and completed.</p><br>
            <p>All attendance records are now up-to-date. No further action is required at this time.</p>
        </div>
        <div class="email-footer">
            This is a system-generated notification. Please do not reply.<br>
            &copy; {{ date('Y') }} McDave Holdings LLC. All rights reserved.
        </div>
    </div>
</body>

</html>