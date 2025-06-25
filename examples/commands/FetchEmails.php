<?php

namespace App\Console\Commands;

use App\Services\Google\GoogleServicesFacade;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fetch Emails Command - Automated Gmail Integration
 * 
 * This Artisan command demonstrates sophisticated email synchronization
 * automation, showcasing enterprise-level integration with Google Gmail API
 * for complete customer communication tracking. It provides scheduled
 * automation capabilities for maintaining synchronized email records
 * across the CRM system.
 * 
 * Key Technical Achievements:
 * - Automated Gmail API synchronization with configurable time windows
 * - Robust authentication checking and error handling
 * - Comprehensive logging for operational monitoring
 * - Flexible time-based filtering for efficient processing
 * - Production-ready command-line interface with proper exit codes
 * 
 * Business Features:
 * - Automatic email capture for complete customer communication history
 * - Scheduled execution via Laravel's task scheduler
 * - Configurable synchronization periods for optimal performance
 * - Complete audit trail of email processing for compliance
 * - Error handling ensures reliable automated execution
 * 
 * Operational Usage:
 * - Manual execution: php artisan emails:fetch
 * - Custom time window: php artisan emails:fetch --hours=48
 * - Scheduled execution: Configure in Laravel's task scheduler
 * - Monitoring: Integrated logging for operational visibility
 */
class FetchEmails extends Command
{
    /**
     * Command signature with configurable options
     * 
     * Provides flexible email fetching with:
     * - Default 24-hour window for daily automation
     * - Configurable hours option for custom synchronization periods
     * - Clear command naming for operational understanding
     */
    protected $signature = 'emails:fetch {--hours=24}';

    /**
     * Human-readable command description for operational clarity
     */
    protected $description = 'Fetch new emails from the last specified hours';

    /**
     * Google Services Facade for Gmail API integration
     * Injected dependency providing unified interface to Google services
     */
    protected GoogleServicesFacade $googleServicesFacade;

    /**
     * Initialize command with Google Services integration
     * 
     * Demonstrates dependency injection pattern for clean service integration
     * and testable command architecture.
     * 
     * @param GoogleServicesFacade $googleServicesFacade Unified Google API interface
     */
    public function __construct(GoogleServicesFacade $googleServicesFacade)
    {
        parent::__construct();
        $this->googleServicesFacade = $googleServicesFacade;
    }

    /**
     * Execute email synchronization with comprehensive error handling
     * 
     * Implements robust email fetching workflow:
     * 1. Parse command options for time window configuration
     * 2. Verify Google account authentication status
     * 3. Execute synchronization with detailed progress reporting
     * 4. Handle errors gracefully with appropriate exit codes
     * 5. Provide operational feedback through console output
     * 
     * @return int Exit code (0 = success, 1 = error) for automated monitoring
     */
    public function handle(): int
    {
        // CONFIGURATION: Parse command options for flexible time windows
        $hours = $this->option('hours');
        $startTime = Carbon::now()->subHours($hours);

        // USER FEEDBACK: Clear operational messaging
        $this->info("Fetching emails from the last {$hours} hours...");

        try {
            // AUTHENTICATION CHECK: Verify Google account connectivity
            if (!$this->googleServicesFacade->isAuthenticated()) {
                $this->error('Google account is not connected. Please connect the account first.');
                return 1; // Exit code 1 indicates error for monitoring systems
            }

            // CORE SYNCHRONIZATION: Execute email fetching with time window
            // This triggers the complete email processing workflow:
            // - Batch fetching of emails from Gmail API
            // - Email content processing and normalization
            // - Customer relationship matching and updates
            // - CRM record creation and synchronization
            // - Thread management and organization
            $result = $this->googleServicesFacade->syncNewEmails($startTime);

            // SUCCESS REPORTING: Provide detailed processing statistics
            $this->info("Processed {$result['processed']} emails. Failed: {$result['failed']}.");

            return 0; // Exit code 0 indicates successful completion

        } catch (\Exception $e) {
            // COMPREHENSIVE ERROR HANDLING: User feedback and operational logging
            $this->error('Error fetching emails: ' . $e->getMessage());
            
            // OPERATIONAL LOGGING: Detailed error information for debugging
            Log::error('Failed to fetch emails: ' . $e->getMessage(), [
                'command' => 'emails:fetch',
                'hours' => $hours,
                'start_time' => $startTime->toISOString(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1; // Exit code 1 indicates error for automated monitoring
        }
    }
}

/*
 * INTEGRATION WITH BUSINESS AUTOMATION:
 * 
 * This command integrates with several key system components:
 * 
 * 1. **GoogleServicesFacade**: Unified interface to Google APIs
 *    - Gmail API authentication and token management
 *    - Email fetching with batch processing optimization
 *    - Thread management and organization
 *    - Error handling and fallback mechanisms
 * 
 * 2. **Customer Communication Tracking**: CRM Integration
 *    - Automatic customer email history creation
 *    - Thread-based conversation organization
 *    - Customer engagement analytics and tracking
 *    - Response time monitoring for service quality
 * 
 * 3. **Laravel Task Scheduler**: Automated Execution
 *    - Scheduled daily email synchronization
 *    - Configurable execution timing for business needs
 *    - Error monitoring and alerting integration
 *    - Performance tracking and optimization
 * 
 * OPERATIONAL BENEFITS:
 * 
 * - **Complete Communication History**: Every customer email captured automatically
 * - **Business Intelligence**: Email engagement tracking and analytics
 * - **Compliance**: Complete audit trail of customer communications
 * - **Operational Efficiency**: Automated synchronization reduces manual effort
 * - **Customer Service**: Representatives have complete communication context
 * 
 * SCHEDULING EXAMPLE:
 * 
 * In Laravel's TaskScheduler (app/Console/Kernel.php):
 * 
 * $schedule->command('emails:fetch --hours=1')
 *          ->hourly()
 *          ->withoutOverlapping()
 *          ->onFailure(function () {
 *              // Send alert notification
 *          });
 * 
 * This ensures continuous email synchronization with overlap protection
 * and automatic error notification for operational reliability.
 */