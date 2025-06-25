<?php

namespace App\Jobs;

use App\Services\WebhookProcessor;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

/**
 * Process Webhook Job - The Missing Link in Lead Automation
 * 
 * This job represents the critical connection between external webhook sources
 * and the complete business automation system. It demonstrates how incoming
 * leads are processed asynchronously with comprehensive error handling and
 * detailed logging for production reliability.
 * 
 * Key Technical Achievements:
 * - Asynchronous webhook processing for optimal performance
 * - Comprehensive error handling with detailed logging
 * - Integration with Spatie WebhookClient for robust webhook management
 * - Production-grade monitoring and debugging capabilities
 * - Graceful failure handling with exception propagation
 * 
 * Business Impact:
 * - Ensures reliable lead processing even during high traffic
 * - Prevents webhook timeouts by processing in background
 * - Maintains complete audit trail of webhook processing
 * - Enables automatic retry of failed webhook processing
 * - Provides operational visibility into lead capture success rates
 * 
 * This is the **keystone component** that transforms external webhook calls
 * into the sophisticated business automation demonstrated throughout the
 * CRM system. Without this job, webhooks would be processed synchronously,
 * creating potential bottlenecks and timeout issues.
 */
class ProcessWebhook extends SpatieProcessWebhookJob
{
    /**
     * Process webhook with comprehensive error handling and logging
     * 
     * This method orchestrates the complete webhook processing workflow:
     * 1. Logs webhook receipt for audit trail
     * 2. Delegates processing to specialized WebhookProcessor service
     * 3. Handles success/failure scenarios with appropriate logging
     * 4. Propagates exceptions for retry mechanisms
     * 
     * The background processing ensures that webhook sources receive
     * immediate HTTP responses while complex business logic (customer
     * creation, order generation, email automation) occurs asynchronously.
     * 
     * @param WebhookProcessor $processor Injected service for webhook business logic
     * @throws \Exception Re-thrown for automatic retry handling
     */
    public function handle(WebhookProcessor $processor)
    {
        // AUDIT TRAIL: Log webhook processing start for operational monitoring
        Log::info('Processing webhook', [
            'webhook_type' => $this->webhookCall->name,        // Provider identification
            'webhook_id' => $this->webhookCall->id,            // Unique processing ID
            'created_at' => $this->webhookCall->created_at->toDateTimeString(), // Timing analysis
        ]);

        try {
            // CORE BUSINESS LOGIC: Delegate to specialized processor service
            // This is where the magic happens:
            // - Signature validation (already handled by middleware)
            // - Payload transformation and normalization
            // - Customer creation/matching
            // - Order generation with business rules
            // - Email automation triggers
            // - Calendar integration
            // - CRM data updates
            $result = $processor->process($this->webhookCall);

            if ($result) {
                // SUCCESS LOGGING: Webhook processed successfully
                Log::info('Webhook processed successfully', [
                    'webhook_type' => $this->webhookCall->name,
                    'webhook_id' => $this->webhookCall->id,
                ]);
            } else {
                // WARNING LOGGING: Processing completed but with warnings
                // This might indicate business rule rejections (duplicate leads, etc.)
                Log::warning('Webhook processing failed', [
                    'webhook_type' => $this->webhookCall->name,
                    'webhook_id' => $this->webhookCall->id,
                ]);
            }

        } catch (\Exception $e) {
            // COMPREHENSIVE ERROR LOGGING: Capture complete failure context
            Log::error('Error processing webhook', [
                'webhook_type' => $this->webhookCall->name,
                'webhook_id' => $this->webhookCall->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),  // Full stack trace for debugging
            ]);

            // CRITICAL: Re-throw exception to trigger Laravel's automatic retry mechanism
            // This ensures failed webhooks are retried according to queue configuration
            throw $e;
        }
    }
}

/*
 * INTEGRATION NOTES:
 * 
 * This job integrates with several key system components:
 * 
 * 1. **Spatie WebhookClient**: Provides robust webhook handling infrastructure
 *    - Automatic signature validation via SignatureValidator
 *    - Webhook call storage and management
 *    - Retry mechanisms for failed processing
 * 
 * 2. **WebhookProcessor Service**: Contains core business logic
 *    - Customer creation/matching algorithms
 *    - Order generation with provider-specific rules
 *    - Email automation trigger logic
 *    - Calendar integration management
 * 
 * 3. **Laravel Queue System**: Enables asynchronous processing
 *    - Background processing prevents webhook timeouts
 *    - Automatic retry handling for transient failures
 *    - Dead letter queue for permanent failures
 * 
 * 4. **Comprehensive Logging**: Provides operational visibility
 *    - Success/failure tracking for business metrics
 *    - Detailed error information for debugging
 *    - Performance monitoring and optimization
 * 
 * BUSINESS WORKFLOW TRIGGERED:
 * 
 * When this job processes a webhook successfully, it triggers:
 * - Customer record creation/update in CRM
 * - Order creation with pre-populated lead data
 * - Welcome email automation (provider-specific rules)
 * - Google Calendar event creation (status-dependent)
 * - Business intelligence data updates
 * - Follow-up reminder scheduling
 * 
 * This single job is responsible for transforming external lead capture
 * into a complete, automated business process that requires minimal
 * manual intervention while maintaining professional customer experience.
 */