<?php

namespace App\Services\Google;

use App\Services\Google\Email\FetchService;
use App\Services\Google\Email\ProcessService;
use App\Services\Google\Email\SendingService;
use App\Services\Google\Email\StoringService;
use App\Services\Google\Email\TokenService;
use App\Services\UniversalLogService;
use Google_Service_Gmail;
use Google_Service_Gmail_ModifyMessageRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Google Services Facade - Unified interface for Google API integration
 * 
 * This facade demonstrates the Facade design pattern by providing a simple,
 * clean interface to complex Google API services including Gmail, Calendar,
 * and authentication. It abstracts service initialization, error handling,
 * and provides fallback mechanisms for reliable email communication.
 * 
 * Key Features:
 * - Lazy service initialization to optimize performance
 * - Comprehensive error handling with fallback mechanisms  
 * - Unified logging across all Google services
 * - Email threading and reply handling
 * - Automatic service health monitoring
 */
class GoogleServicesFacade
{
    protected $fetchService;
    protected $processService; 
    protected $sendingService;
    protected $storingService;
    protected $tokenService;
    protected $logService;
    protected $gmailService;

    public function __construct(
        TokenService $tokenService,
        UniversalLogService $logService
    ) {
        $this->tokenService = $tokenService;
        $this->logService = $logService;
    }

    /**
     * Initialize Gmail service with comprehensive error handling
     * Implements lazy loading for optimal performance
     */
    protected function initializeGmailService(): ?Google_Service_Gmail
    {
        if ($this->gmailService === null) {
            $this->logService->googleAuth('Initializing Gmail Service', 'info', [
                'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? 'unknown',
            ]);

            $this->gmailService = $this->tokenService->getGmailService();

            if (!$this->gmailService instanceof Google_Service_Gmail) {
                $this->logService->googleAuth(
                    'Gmail service not available. Some features may be limited.', 
                    'warning'
                );
            }
        }

        return $this->gmailService;
    }

    /**
     * Generic service initialization with dependency injection
     * Demonstrates service container pattern implementation
     */
    protected function initializeService($serviceName)
    {
        if ($this->{$serviceName} === null) {
            $gmailService = $this->initializeGmailService();
            if ($gmailService) {
                switch ($serviceName) {
                    case 'fetchService':
                        $this->fetchService = new FetchService($gmailService, $this->logService);
                        break;
                    case 'processService':
                        $this->processService = new ProcessService($gmailService, $this->logService);
                        break;
                    case 'sendingService':
                        $this->sendingService = new SendingService($gmailService, $this->logService);
                        break;
                    case 'storingService':
                        $this->storingService = new StoringService(
                            $this->fetchService, 
                            $this->processService, 
                            $this->logService
                        );
                        break;
                }
                $this->logService->googleAuth("$serviceName initialized", 'info');
            } else {
                $this->logService->googleAuth(
                    "Failed to initialize $serviceName: Gmail service not available", 
                    'error'
                );
            }
        }

        return $this->{$serviceName};
    }

    public function getGmailService(): ?Google_Service_Gmail
    {
        return $this->initializeGmailService();
    }

    // Authentication & Token Management

    public function createAuthUrl()
    {
        return $this->tokenService->createAuthUrl();
    }

    public function fetchAccessTokenWithAuthCode($code)
    {
        return $this->tokenService->fetchAccessTokenWithAuthCode($code);
    }

    public function isAuthenticated(): bool
    {
        return $this->tokenService->isAuthenticated();
    }

    public function refreshAuthStatus(): bool
    {
        return $this->tokenService->refreshAuthStatus();
    }

    // Email Fetching Methods

    /**
     * Fetch emails in batches for efficient processing
     * Implements batch processing pattern for scalability
     */
    public function fetchNewEmailsInBatches($startTime = null): array
    {
        $fetchService = $this->initializeService('fetchService');
        if (!$fetchService) {
            $this->logService->googleProcessing('Fetch service not available', 'warning');
            return [];
        }

        return $fetchService->fetchNewEmailsInBatches($startTime);
    }

    public function fetchEmailsForThread($threadId): array
    {
        $fetchService = $this->initializeService('fetchService');
        if (!$fetchService) {
            $this->logService->googleProcessing('Fetch service not available', 'warning');
            return [];
        }

        return $fetchService->fetchEmailsForThread($threadId);
    }

    public function fetchSingleEmail($messageId)
    {
        $fetchService = $this->initializeService('fetchService');
        if (!$fetchService) {
            $this->logService->googleProcessing('Fetch service not available', 'warning');
            return null;
        }

        return $fetchService->fetchSingleEmail($messageId);
    }

    public function getMessageHeaders($messageId): array
    {
        $fetchService = $this->initializeService('fetchService');
        if (!$fetchService) {
            $this->logService->googleProcessing('Fetch service not available', 'warning');
            return [];
        }

        return $fetchService->getMessageHeaders($messageId);
    }

    // Email Processing Methods

    public function processEmails($messages): array
    {
        $processService = $this->initializeService('processService');
        if (!$processService) {
            $this->logService->googleProcessing('Process service not available', 'warning');
            return ['processed' => 0, 'failed' => 0];
        }

        return $processService->processEmails($messages);
    }

    public function processEmail($message)
    {
        $processService = $this->initializeService('processService');
        if (!$processService) {
            $this->logService->googleProcessing('Process service not available', 'warning');
            return null;
        }

        return $processService->processEmail($message);
    }

    /**
     * Advanced email sending with threading and fallback mechanisms
     * Handles complex reply scenarios and ensures delivery reliability
     */
    public function sendEmail(array $emailData)
    {
        $sendingService = $this->initializeService('sendingService');
        if (!$sendingService) {
            $this->logService->googleSending(
                'Gmail sending service not available, using fallback', 
                'warning'
            );
            return $this->sendEmailFallback($emailData);
        }

        try {
            $headers = [];
            
            // Handle email threading for replies
            if (isset($emailData['reply_to_message_id'])) {
                $originalHeaders = $this->getMessageHeaders($emailData['reply_to_message_id']);
                $headers = $this->prepareReplyHeaders($originalHeaders);
            }

            // Format reply subject lines
            if (isset($emailData['gmail_thread_id'])) {
                $emailData['subject'] = $this->getReplySubject($emailData['subject'] ?? 'No Subject');
            }

            // Send via Gmail API
            $sentMessage = $sendingService->sendEmail($emailData, $headers);

            // Fetch the sent message from Gmail for record keeping
            $fetchedMessage = $this->fetchSingleEmail($sentMessage->getId());

            // Process the fetched message to create CustomerEmail record
            return $this->processEmail($fetchedMessage);

        } catch (\Exception $e) {
            $this->logService->googleSending('Failed to send email: ' . $e->getMessage(), 'error', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'emailData' => $emailData,
            ]);
            throw $e;
        }
    }

    /**
     * Fallback email sending mechanism for reliability
     * Ensures business continuity when Gmail API is unavailable
     */
    protected function sendEmailFallback(array $emailData): bool
    {
        $this->logService->googleSending('Attempting to send email via fallback method', 'info');

        try {
            Mail::send([], [], function ($message) use ($emailData) {
                $message->to($emailData['to'])
                    ->subject($emailData['subject'] ?? 'No Subject')
                    ->html($emailData['body']);

                if (!empty($emailData['from'])) {
                    $message->from($emailData['from']);
                }

                if (!empty($emailData['cc'])) {
                    $message->cc($emailData['cc']);
                }

                if (!empty($emailData['bcc'])) {
                    $message->bcc($emailData['bcc']);
                }

                // Handle file attachments
                if (!empty($emailData['attachments'])) {
                    foreach ($emailData['attachments'] as $attachment) {
                        if (Storage::disk('public')->exists($attachment)) {
                            $fullPath = Storage::disk('public')->path($attachment);
                            $fileName = basename($attachment);
                            $message->attach($fullPath, ['as' => $fileName]);
                            $this->logService->googleSending("Attaching file: $fileName", 'info');
                        } else {
                            $this->logService->googleSending("Attachment not found: $attachment", 'warning');
                        }
                    }
                }
            });

            $this->logService->googleSending('Email sent successfully via fallback method', 'info');
            return true;

        } catch (\Exception $e) {
            $this->logService->googleSending(
                'Failed to send email via fallback method: ' . $e->getMessage(), 
                'error', 
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'emailData' => $emailData,
                ]
            );

            throw $e; // Re-throw if even fallback fails
        }
    }

    /**
     * Prepare email headers for proper threading
     * Ensures replies maintain conversation context
     */
    protected function prepareReplyHeaders($originalHeaders): array
    {
        $headers = [];
        if (isset($originalHeaders['Message-ID'])) {
            $headers[] = "In-Reply-To: {$originalHeaders['Message-ID']}";
            $headers[] = "References: {$originalHeaders['Message-ID']}";
        }
        if (isset($originalHeaders['Thread-Topic'])) {
            $headers[] = "Thread-Topic: {$originalHeaders['Thread-Topic']}";
        }
        if (isset($originalHeaders['Thread-Index'])) {
            $headers[] = "Thread-Index: {$originalHeaders['Thread-Index']}";
        }

        return $headers;
    }

    /**
     * Format reply subject lines properly
     */
    protected function getReplySubject($originalSubject): string
    {
        if (stripos($originalSubject, 're:') === 0) {
            return $originalSubject;
        }

        return 'Re: ' . $originalSubject;
    }

    /**
     * Comprehensive email synchronization with batch processing
     * Implements robust error handling for production reliability
     */
    public function syncNewEmails($startTime = null): array
    {
        try {
            $processedCount = 0;
            $failedCount = 0;

            foreach ($this->fetchNewEmailsInBatches($startTime) as $batchMessages) {
                $this->logService->googleProcessing('Processing batch of ' . count($batchMessages) . ' messages.');

                foreach ($batchMessages as $message) {
                    try {
                        $this->processEmail($message);
                        $processedCount++;
                        $this->logService->googleProcessing("Processed email: {$message->getId()}");
                    } catch (\Exception $e) {
                        $failedCount++;
                        $this->logService->googleProcessing(
                            'Failed to process email: ' . $e->getMessage(), 
                            'error', 
                            [
                                'exception' => get_class($e),
                                'message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]
                        );
                    }
                }

                $this->logService->googleProcessing(
                    "Batch processing completed. Total processed: $processedCount, Failed: $failedCount"
                );
            }

            $this->logService->googleProcessing(
                "Sync completed. Total processed: $processedCount, Failed: $failedCount"
            );

            return [
                'processed' => $processedCount,
                'failed' => $failedCount,
            ];

        } catch (\Exception $e) {
            $this->logService->googleProcessing(
                'Error in email sync process: ' . $e->getMessage(), 
                'error', 
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return [
                'processed' => 0,
                'failed' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mark entire email thread as read
     * Useful for CRM workflow automation
     */
    public function markThreadAsRead(string $threadId): void
    {
        try {
            $gmail = $this->getGmailService();

            // Fetch all messages in the thread
            $thread = $gmail->users_threads->get('me', $threadId);
            $messages = $thread->getMessages();

            foreach ($messages as $message) {
                $mods = new Google_Service_Gmail_ModifyMessageRequest;
                $mods->setRemoveLabelIds(['UNREAD']);

                $gmail->users_messages->modify('me', $message->getId(), $mods);
            }

            $this->logService->googleProcessing("Thread marked as read: $threadId", 'info');

        } catch (\Exception $e) {
            $this->logService->googleProcessing(
                "Failed to mark thread as read: $threadId", 
                'error', 
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw $e;
        }
    }

    /**
     * Unified logging interface
     */
    public function log($message, $type = null, $level = 'info', $context = []): void
    {
        $this->logService->log($message, $type, $level, $context);
    }
}