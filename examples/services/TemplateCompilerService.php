<?php

namespace App\Services;

use App\Models\CRM\Customer;
use App\Models\CRM\Order;
use App\Models\CRM\Utility\Template;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Template Compiler Service - Dynamic Email Content Generation
 * 
 * This service demonstrates sophisticated template compilation with dynamic
 * placeholder resolution, complex data traversal, and intelligent formatting.
 * It enables business users to create rich email templates without coding
 * while providing powerful variable interpolation capabilities.
 * 
 * Key Features:
 * - Dynamic placeholder resolution with dot notation
 * - Intelligent data type formatting (dates, currencies, etc.)
 * - Complex relationship traversal across models
 * - Secure data access with null safety
 * - Special method handling (like shareable links)
 * - Business-specific formatting rules
 */
class TemplateCompilerService
{
    /**
     * Main compilation method that processes template with dynamic data
     * 
     * @param Template $template The email template with placeholders
     * @param array $data Context data including customer_id, order_id, etc.
     * @return array Compiled subject and body with placeholders replaced
     */
    public function compile(Template $template, array $data): array
    {
        $subject = $template->getSubject();
        $body = $template->getBody();
        $placeholders = $template->placeholders ?? [];

        // Load related models for placeholder resolution
        $customer = Customer::find($data['customer_id']);
        $order = isset($data['order_id']) ? Order::find($data['order_id']) : null;

        // Process each placeholder defined in the template
        foreach ($placeholders as $placeholder) {
            $value = $this->resolvePlaceholder($placeholder, $customer, $order);
            
            // Replace placeholders in both subject and body
            $subject = str_replace('{' . $placeholder . '}', $value, $subject);
            $body = str_replace('{' . $placeholder . '}', $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Resolve placeholder value with dot notation support
     * 
     * Supports complex expressions like:
     * - customer.full_name
     * - order.move_date  
     * - order.fromAddress.postcode
     * - order.shareableLink (special method)
     * 
     * @param string $placeholder The placeholder expression
     * @param Customer|null $customer Customer model instance
     * @param Order|null $order Order model instance  
     * @return string Resolved value or empty string if not found
     */
    protected function resolvePlaceholder(string $placeholder, ?Customer $customer, ?Order $order): string
    {
        $parts = explode('.', $placeholder);
        $model = array_shift($parts);

        // Handle special methods that require custom logic
        if ($placeholder === 'order.shareableLink') {
            return $order ? $order->getShareableLink() : '';
        }

        // Resolve base model from placeholder prefix
        $value = match ($model) {
            'customer' => $customer,
            'order' => $order,
            default => null,
        };

        // Traverse dot notation path safely
        foreach ($parts as $part) {
            if (is_null($value)) {
                return '';
            }

            if ($value instanceof Model && method_exists($value, 'getAttribute')) {
                // Use Eloquent's getAttribute for proper accessor handling
                $value = $value->getAttribute($part);
            } elseif (is_object($value) && isset($value->$part)) {
                // Direct property access for plain objects
                $value = $value->$part;
            } else {
                // Path not found, return empty string
                return '';
            }
        }

        return $this->formatValue($placeholder, $value);
    }

    /**
     * Format resolved values based on placeholder type and business rules
     * 
     * Provides intelligent formatting for different data types:
     * - Dates: Formatted as "Mon 15th Jan" 
     * - Currencies: Formatted with proper symbols
     * - Text: Cleaned and sanitized
     * 
     * @param string $placeholder Original placeholder for context
     * @param mixed $value The resolved value to format
     * @return string Formatted value ready for display
     */
    protected function formatValue(string $placeholder, $value): string
    {
        if (is_null($value)) {
            return '';
        }

        // Special date formatting for user-friendly display
        if (stripos($placeholder, 'date') !== false && !empty($value)) {
            try {
                return Carbon::parse($value)->format('D jS M');
            } catch (\Exception $e) {
                // If date parsing fails, return original value
                return (string) $value;
            }
        }

        // Currency formatting for price-related fields
        if (stripos($placeholder, 'price') !== false || 
            stripos($placeholder, 'total') !== false ||
            stripos($placeholder, 'cost') !== false) {
            try {
                return '£' . number_format((float) $value, 2);
            } catch (\Exception $e) {
                return (string) $value;
            }
        }

        // Phone number formatting for UK numbers
        if (stripos($placeholder, 'phone') !== false && !empty($value)) {
            return $this->formatPhoneNumber((string) $value);
        }

        // Default string conversion with HTML entity encoding for security
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format UK phone numbers for consistent display
     * 
     * @param string $phone Raw phone number
     * @return string Formatted phone number
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        
        // Handle UK numbers
        if (str_starts_with($cleaned, '+44')) {
            // Format: +44 7XXX XXX XXX
            return preg_replace('/(\+44)(\d{4})(\d{3})(\d{3})/', '$1 $2 $3 $4', $cleaned);
        }
        
        if (str_starts_with($cleaned, '07') && strlen($cleaned) === 11) {
            // Format: 07XXX XXX XXX  
            return preg_replace('/(\d{5})(\d{3})(\d{3})/', '$1 $2 $3', $cleaned);
        }
        
        // Return original if no formatting rules match
        return $phone;
    }

    /**
     * Get available placeholders for a given template context
     * Used by admin interface to show available variables
     * 
     * @return array Available placeholder options
     */
    public function getAvailablePlaceholders(): array
    {
        return [
            'Customer' => [
                'customer.first_name' => 'Customer First Name',
                'customer.last_name' => 'Customer Last Name', 
                'customer.full_name' => 'Customer Full Name',
                'customer.email' => 'Customer Email',
                'customer.phone' => 'Customer Phone',
            ],
            'Order' => [
                'order.friendly_id' => 'Order Number',
                'order.move_date' => 'Move Date',
                'order.end_date' => 'End Date',
                'order.total' => 'Order Total',
                'order.description' => 'Order Description',
                'order.shareableLink' => 'Customer Portal Link',
            ],
            'Addresses' => [
                'order.fromAddress.full_address' => 'From Address',
                'order.toAddress.full_address' => 'To Address',
                'order.fromAddress.postcode' => 'From Postcode',
                'order.toAddress.postcode' => 'To Postcode',
            ],
        ];
    }

    /**
     * Validate template placeholders to ensure they reference valid paths
     * Used during template creation to prevent runtime errors
     * 
     * @param array $placeholders Placeholders to validate
     * @return array Validation results with errors
     */
    public function validatePlaceholders(array $placeholders): array
    {
        $errors = [];
        $available = collect($this->getAvailablePlaceholders())->flatten();
        
        foreach ($placeholders as $placeholder) {
            if (!$available->has($placeholder)) {
                $errors[] = "Unknown placeholder: {$placeholder}";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Preview template compilation with sample data
     * Used by admin interface to show template preview
     * 
     * @param Template $template Template to preview
     * @return array Compiled template with sample data
     */
    public function previewTemplate(Template $template): array
    {
        $sampleData = [
            'customer_id' => 1, // Use first customer as sample
            'order_id' => 1,    // Use first order as sample
        ];
        
        try {
            return $this->compile($template, $sampleData);
        } catch (\Exception $e) {
            return [
                'subject' => 'Error: ' . $e->getMessage(),
                'body' => 'Template compilation failed. Please check your placeholders.',
            ];
        }
    }
}