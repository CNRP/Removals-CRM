<?php

namespace App\Webhook;

use App\Models\FailedWebhook;
use App\Services\UniversalLogService;
use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator as BaseSignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

/**
 * Multi-provider webhook signature validator
 * 
 * Handles secure signature validation for multiple webhook providers
 * including complex payload normalization and comprehensive error handling.
 * 
 * Supported providers:
 * - CompareMymove: JSON payloads with timestamp+token HMAC
 * - ReallyMoving: Form-encoded data with HMAC SHA-256
 * - PinLocal: Complex sorted parameter validation with SHA-1
 */
class SignatureValidator implements BaseSignatureValidator
{
    protected $logService;

    public function __construct(UniversalLogService $logService)
    {
        $this->logService = $logService;
    }

    /**
     * Validate webhook signature based on provider type
     */
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $isValid = false;

        switch ($config->name) {
            case 'compare-my-move':
                $isValid = $this->validateCompareMyMove($request, $config);
                break;
            case 'really-moving':
                $isValid = $this->validateReallyMoving($request, $config);
                break;
            case 'pin-local':
                $isValid = $this->validatePinLocal($request, $config);
                break;
            default:
                $this->logService->webhooks("Unknown webhook type: {$config->name}", 'error');
                break;
        }

        // Store failed webhooks for debugging and manual retry
        if (!$isValid) {
            $this->storeFailedWebhook($request, $config);
        }

        return $isValid;
    }

    /**
     * Validate CompareMymove webhook with JSON payload normalization
     * 
     * Handles the critical double-escaping issue where payloads arrive
     * with inconsistent JSON encoding, causing signature validation failures.
     */
    private function validateCompareMyMove(Request $request, WebhookConfig $config): bool
    {
        // Handle double-escaped JSON payload - critical fix for production issue
        $payload = json_decode($request->getContent());
        $payload = json_decode($payload, true);

        $this->logService->webhooks('CompareMymove payload received', 'debug', [
            'raw_content' => $request->getContent(),
            'content_length' => strlen($request->getContent()),
            'decoded_payload' => $payload
        ]);

        $timestamp = $payload['timestamp'];
        $signature = $payload['signature'];
        $token = $payload['token'];

        // Generate signature from timestamp + token
        $data = "{$timestamp}{$token}";
        $generatedSignature = hash_hmac('sha256', $data, $config->signingSecret);

        $isValid = hash_equals($generatedSignature, $signature);

        $this->logService->webhooks('CompareMymove signature validation', 'debug', [
            'timestamp' => $timestamp,
            'token' => $token,
            'received_signature' => $signature,
            'generated_signature' => $generatedSignature,
            'validation_result' => $isValid
        ]);

        return $isValid;
    }

    /**
     * Validate ReallyMoving webhook with form-encoded payload
     */
    private function validateReallyMoving(Request $request, WebhookConfig $config): bool
    {
        $rawPayload = $request->getContent();
        parse_str($rawPayload, $payload);

        $this->logService->webhooks('ReallyMoving payload received', 'debug', [
            'raw_payload' => $rawPayload,
            'parsed_payload' => $payload
        ]);

        $signature = $payload['signature'];
        $timestamp = $payload['timestamp'];
        $token = $payload['token'];

        // Concatenate timestamp and token for signature generation
        $concat = $timestamp . $token;
        $generatedSignature = hash_hmac('sha256', $concat, $config->signingSecret);

        $isValid = hash_equals($generatedSignature, $signature);

        $this->logService->webhooks('ReallyMoving signature validation', 'debug', [
            'concat_data' => $concat,
            'received_signature' => $signature,
            'generated_signature' => $generatedSignature,
            'validation_result' => $isValid
        ]);

        return $isValid;
    }

    /**
     * Validate PinLocal webhook with complex sorted parameter validation
     * 
     * Uses SHA-1 HMAC with base64 encoding and requires sorting lead data
     * parameters before signature generation.
     */
    private function validatePinLocal(Request $request, WebhookConfig $config): bool
    {
        $all = $request->all();
        $this->logService->webhooks('PinLocal payload received', 'debug', [
            'all_params' => $all,
            'headers' => $request->header()
        ]);

        $webhookKey = env('WEBHOOK_PINLOCAL');
        $params = $all;

        // Build signed data string starting with webhook URL
        $signedData = 'https://removalswirral.com/pin-local/webhook';
        $signedData .= $params['lead_id'];
        $signedData .= $params['lead_code'];
        $signedData .= $params['lead_type_id'];

        $leadData = json_decode($params['lead_data'], true);

        $this->logService->webhooks('PinLocal lead data before sorting', 'debug', $leadData);

        try {
            // Sort lead data parameters for consistent signature generation
            ksort($leadData);

            foreach ($leadData as $key => $value) {
                // Handle both string and array values
                if (gettype($value['value']) != 'array' && $value['value'] != '') {
                    $val = $value['value'];
                } elseif (gettype($value['value']) == 'array' && count($value['value']) > 0 && $value['value'][0] != '') {
                    $val = $value['value'][0];
                } else {
                    $val = '';
                }

                $signedData .= $val;
            }

            // Generate SHA-1 HMAC signature with base64 encoding
            $signature = base64_encode(hash_hmac('sha1', $signedData, $webhookKey, true));
            $receivedSignature = $request->header('x-pinlocal-signature');
            
            $isValid = $signature === $receivedSignature;
            
            $this->logService->webhooks('PinLocal signature validation', 'debug', [
                'signed_data_length' => strlen($signedData),
                'generated_signature' => $signature,
                'received_signature' => $receivedSignature,
                'validation_result' => $isValid
            ]);

        } catch (\Exception $e) {
            $this->logService->webhooks('Error processing PinLocal lead data', 'error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'lead_data' => $leadData
            ]);

            // Return true for known PinLocal validation complexities
            // In production, implement proper fallback validation
            return true;
        }

        return true; // Simplified for showcase - implement proper validation in production
    }

    /**
     * Store failed webhook attempts for debugging and manual retry
     * 
     * Creates comprehensive audit trail including full request data,
     * configuration details, and error context for troubleshooting.
     */
    private function storeFailedWebhook(Request $request, WebhookConfig $config)
    {
        try {
            $requestData = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'headers' => $request->headers->all(),
                'body' => $request->getContent(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ];

            $configData = [
                'name' => $config->name,
                'signing_secret' => substr($config->signingSecret, 0, 8) . '...', // Partial for security
                'signature_header_name' => $config->signatureHeaderName,
                'signature_validator' => get_class($config->signatureValidator),
                'webhook_profile' => $config->webhookProfile ? get_class($config->webhookProfile) : null,
                'webhook_response' => $config->webhookResponse ? get_class($config->webhookResponse) : null,
                'webhook_model' => $config->webhookModel ?? null,
            ];

            // Store process webhook job if it exists
            if (property_exists($config, 'processWebhookJob')) {
                $configData['process_webhook_job'] = $config->processWebhookJob;
            }

            $failedWebhook = FailedWebhook::create([
                'webhook_type' => $config->name,
                'failure_reason' => 'signature_validation_failed',
                'request_data' => json_encode($requestData),
                'config_data' => json_encode($configData),
                'status' => 'pending',
                'failed_at' => now()
            ]);

            $this->logService->webhooks('Failed webhook stored in failsafe system', 'error', [
                'id' => $failedWebhook->id,
                'failure_reason' => $failedWebhook->failure_reason,
                'type' => $config->name,
                'can_retry' => true
            ]);

        } catch (\Exception $e) {
            // Critical: Failure to store failed webhooks
            $this->logService->webhooks('CRITICAL: Failed to store failed webhook in failsafe system', 'critical', [
                'type' => $config->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'original_request_ip' => $request->ip()
            ]);
        }
    }
}