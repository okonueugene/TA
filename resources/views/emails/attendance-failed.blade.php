<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Attendance Sync Failure</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-color: #e74c3c;
            /* A strong red for alerts */
            --secondary-color: #2c3e50;
            /* Dark blue/grey for text */
            --light-bg: #f5f7fa;
            /* A very light blue-grey background */
            --font-color: #34495e;
            /* Slightly darker body font color */
            --muted-color: #7f8c8d;
            /* Muted color for footer/secondary text */
            --error-bg: #ffebee;
            /* Light red for error box background */
            --error-border: #ef9a9a;
            /* Slightly darker red for error box border */
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--light-bg);
            font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
            /* Modern font stack */
            color: var(--font-color);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            /* Smoother fonts on Apple devices */
            -moz-osx-font-smoothing: grayscale;
            /* Smoother fonts on macOS */
        }

        /* Removed redundant h2 style outside the email-header context */
        /* h2 {
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 20px;
        } */

        .email-wrapper {
            max-width: 600px;
            margin: 40px auto;
            /* Increased margin for more breathing room */
            background-color: #fff;
            border-radius: 10px;
            /* Slightly more rounded corners */
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            /* More pronounced shadow */
            border: 1px solid #e0e0e0;
            /* Subtle border for definition */
        }

        /* Styles for the logo container */
        .logo-container {
            text-align: center;
            padding: 20px;
            /* Ample padding around the logo */
            background-color: #ffffff;
            /* Ensure a clean white background for the logo area */
            border-bottom: 1px solid #f0f0f0;
            /* A subtle line to separate logo from header */
        }

        .logo-container img {
            max-height: 50px;
            /* Adjust as needed, ensure it's not too big */
            width: auto;
            /* Maintain aspect ratio */
            display: block;
            /* Ensures it takes up its own line and can be centered */
            margin: 0 auto;
            /* Center the logo horizontally */
        }

        .email-header {
            background-color: var(--primary-color);
            /* This is the key fix: Ensure this red background is applied */
            color: white;
            padding: 25px 20px;
            /* Increased vertical padding */
            text-align: center;
        }

        .email-header h2 {
            margin: 0;
            font-size: 24px;
            /* Larger header text */
            font-weight: 700;
            /* Bolder header */
            letter-spacing: 0.5px;
            /* Slight letter spacing for impact */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Space between icon and text */
        }

        .email-header h2 span {
            font-size: 28px;
            /* Larger icon */
            line-height: 1;
        }

        .email-body {
            padding: 30px;
            /* Increased padding for body content */
        }

        .email-body p {
            margin-bottom: 15px;
            font-size: 16px;
        }

        .email-body p:last-of-type {
            margin-bottom: 0;
        }

        .error-box {
            background-color: var(--error-bg);
            border-left: 5px solid var(--primary-color);
            border-radius: 5px;
            padding: 20px;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
            font-size: 14px;
            color: #c0392b;
            white-space: pre-wrap;
            word-break: break-word;
            margin-top: 20px;
            margin-bottom: 25px;
            line-height: 1.5;
            overflow: auto;
            max-height: 400px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
            /* Changed from left to center */
        }

        .email-footer {
            font-size: 13px;
            /* Slightly larger footer font */
            color: #616161;
            /* Darker muted color for better readability */
            text-align: center;
            padding: 20px;
            /* Increased footer padding */
            background-color: #eceff1;
            /* Slightly darker footer background */
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
                /* No border-radius on small screens for full width */
                box-shadow: none;
                /* No shadow on small screens */
                border: none;
                /* No border on small screens */
            }

            .email-body,
            .email-header,
            .email-footer,
            .logo-container {
                /* Include logo container in responsive padding */
                padding: 15px;
                /* Adjust padding for smaller screens */
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

            .error-box {
                padding: 15px;
                font-size: 13px;
            }

            .logo-container img {
                max-height: 40px;
                /* Adjust logo size for smaller screens if needed */
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

        <div class="email-header" style="background-color: #ae2727; color: white;">
            <h2><span>&#x1F6A8;</span> Scheduled Attendance Sync Failed</h2>
        </div>
        <div class="email-body">
            <p>The scheduled attendance sync operation was not completed successfully. This indicates a critical issue
                that requires immediate attention.</p>
            <p><strong>Error Details:</strong></p>
            <div class="text-danger text-center error-box">
                {{ $error }}
            </div>
            <p>Please review the system settings, server status, or database connectivity to resolve this issue as soon
                as possible.</p>
            <p>For assistance, contact IT support.</p>
        </div>
        <div class="email-footer">
            This is a system-generated notification. Please do not reply.<br>
            &copy; {{ date('Y') }} McDave Holdings LLC. All rights reserved.
        </div>
    </div>
</body>

</html>
