<?php
namespace App\Console\Commands;

use App\Exports\AttendanceExport; // Make sure this path is correct
use App\Mail\MonthlyAttendanceReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class GenerateMonthlyAttendanceReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:monthly-attendance
                            {--month= : Month (1-12, defaults to previous month if run at start of month, otherwise current month)}
                            {--year= : Year (defaults to current year)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and email monthly attendance report up to previous day or for a full past month.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $reportMonth         = null;
        $reportYear          = null;
        $tempStorageFileName = null; // Initialize to null for finally block safety

        try {
            $this->info("--- Starting Monthly Attendance Report Generation ---");
            Log::info("Monthly attendance report command initiated.", [
                'options' => $this->options(),
            ]);

            // 1. Determine Report Month and Year
            $today        = Carbon::now();
            $defaultMonth = $today->month;
            $defaultYear  = $today->year;

            // If day is 1st of the month, default to previous month
            if ($today->day === 1) {
                $previousMonth = $today->copy()->subMonth();
                $defaultMonth  = $previousMonth->month;
                $defaultYear   = $previousMonth->year;
            }

            $reportMonth = $this->option('month') ?? $defaultMonth;
            $reportYear  = $this->option('year') ?? $defaultYear;

            // 2. Validate Month and Year
            if (! is_numeric($reportMonth) || $reportMonth < 1 || $reportMonth > 12) {
                throw new \InvalidArgumentException('Invalid month. Please provide a month between 1 and 12.');
            }
            if (! is_numeric($reportYear) || $reportYear < 2000 || $reportYear > ($today->year + 1)) { // Adjusted max year slightly
                throw new \InvalidArgumentException('Invalid year. Please provide a valid year (e.g., 2000 to ' . ($today->year + 1) . ').');
            }

            $requestedMonthDate = Carbon::createFromDate($reportYear, $reportMonth, 1);

                                                                                     // 3. Determine Actual Report Period (Start Date and End Date for the export)
            $reportStartDate = $requestedMonthDate->startOfMonth()->format('Y-m-d'); // Ensure it's always start of month
            $reportEndDate   = null;
            $isCurrentMonth  = false;

            if ($requestedMonthDate->isSameMonth($today)) {
                // If reporting for the current month, end date is yesterday
                $reportEndDate  = $today->copy()->subDay()->format('Y-m-d');
                $isCurrentMonth = true;
                // If today is 1st and we're trying to report for current month, but yesterday was previous month
                if (Carbon::parse($reportEndDate)->month != $reportMonth) {
                    throw new \RuntimeException("Cannot generate report for current month ({$reportMonth}) if today is day 1 and yesterday was previous month.");
                }
            } else {
                // For past months, end date is the last day of that month
                $reportEndDate = $requestedMonthDate->copy()->endOfMonth()->format('Y-m-d');
            }

            // Ensure start date is not after end date, handles edge cases for very early month reports
            if (Carbon::parse($reportStartDate)->greaterThan(Carbon::parse($reportEndDate))) {
                throw new \RuntimeException("Report start date ({$reportStartDate}) is after calculated end date ({$reportEndDate}). No data for this period.");
            }

            $this->info("Report period for {$requestedMonthDate->format('F Y')}: {$reportStartDate} to {$reportEndDate}");
            Log::info("Report dates determined.", ['start' => $reportStartDate, 'end' => $reportEndDate, 'is_current_month' => $isCurrentMonth]);

                                                                       // 4. Generate Excel File
                                                                       // Instantiate AttendanceExport with month, year, actual start_date, and actual end_date
            $export = new AttendanceExport($reportMonth, $reportYear); // Updated parameters

            // Define a cleaner filename for the attachment and a unique one for storage
            $monthName        = $requestedMonthDate->format('F');
            $baseFileName     = "Monthly_Attendance_Report_{$monthName}_{$reportYear}";
            $downloadFileName = $baseFileName . '.xlsx'; // This is what the recipient will see

            $tempStorageFileName = 'temp/' . $baseFileName . '_' . Carbon::now()->format('Ymd_His') . '.xlsx';
            $fullPath            = storage_path('app/' . $tempStorageFileName);

            $this->info("Exporting data to Excel: {$downloadFileName}");
            Excel::store($export, $tempStorageFileName, 'local');

            if (! file_exists($fullPath)) {
                throw new \RuntimeException('Failed to generate Excel file: ' . $fullPath);
            }
            $this->info('Excel file generated successfully.');
            Log::info("Excel file generated.", ['path' => $fullPath, 'download_name' => $downloadFileName]);

            // 5. Get Email Addresses
            $emails = config('tad.notify_emails');

            $emails = array_filter($emails);
            $this->info('Sending report to: ' . implode(', ', $emails));
            Log::info("Attempting to email recipients.", ['recipients' => $emails]);

            // 6. Prepare Email Data
            $emailData = [
                'month'           => $monthName,
                'year'            => $reportYear,
                'reportEndDate'   => Carbon::parse($reportEndDate)->format('F j, Y'),   // Format for email body
                'reportStartDate' => Carbon::parse($reportStartDate)->format('F j, Y'), // Format for email body
                'fileName'        => $downloadFileName,
                'generatedAt'     => $today->format('F j, Y \a\t g:i A'),
                'totalDays'       => Carbon::parse($reportEndDate)->day, // Total days up to end date in the report period
                'isCurrentMonth'  => $isCurrentMonth,
                // 'searchFilter' is now removed
            ];

            // 7. Queue Emails
            foreach ($emails as $email) {
                try {
                    Mail::to($email)->queue(new MonthlyAttendanceReport($emailData, $fullPath));
                    $this->info("Email queued successfully for: {$email}");
                    Log::info("Monthly attendance report email queued.", ['recipient' => $email]);
                } catch (\Exception $e) {
                    $this->error("Failed to queue email for {$email}: " . $e->getMessage());
                    Log::error("Failed to queue email for monthly attendance report.", [
                        'recipient' => $email,
                        'error'     => $e->getMessage(),
                        'trace'     => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->info('All emails have been queued. The report will be sent shortly.');

        } catch (\InvalidArgumentException $e) {
            $this->error("Input Error: " . $e->getMessage());
            Log::warning("Monthly attendance report input error: " . $e->getMessage(), ['options' => $this->options()]);
            return Command::INVALID;
        } catch (\RuntimeException $e) {
            $this->error("Report Generation Error: " . $e->getMessage());
            Log::error("Monthly attendance report generation failed: " . $e->getMessage(), [
                'month' => $reportMonth,
                'year'  => $reportYear,
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('An unexpected error occurred during report generation: ' . $e->getMessage());
            Log::critical('CRITICAL: Monthly attendance report command failed unexpectedly.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'month' => $reportMonth,
                'year'  => $reportYear,
            ]);
            return Command::FAILURE;
        } finally {
            if (isset($tempStorageFileName) && Storage::disk('local')->exists($tempStorageFileName)) {
                Storage::disk('local')->delete($tempStorageFileName);
                $this->info('Temporary file cleaned up: ' . $tempStorageFileName);
                Log::info('Temporary report file cleaned up.', ['path' => $tempStorageFileName]);
            }
            $this->info("--- Monthly Attendance Report Generation Finished ---");
        }

        return Command::SUCCESS;
    }
}
