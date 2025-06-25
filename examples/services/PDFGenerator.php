<?php

namespace App\Services;

use App\Models\CRM\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * PDF Generator - Advanced Document Automation System
 * 
 * This service demonstrates sophisticated document generation capabilities
 * including multiple document types, dynamic content generation, template
 * inheritance, and business rule implementation. It showcases enterprise-level
 * document automation that eliminates manual document creation while ensuring
 * professional, consistent output.
 * 
 * Key Features:
 * - Multiple document types with specific business logic
 * - Dynamic content generation from order data
 * - Template-based system with inheritance
 * - Automatic calculations for deposits, VAT, totals
 * - Professional PDF generation with custom styling
 * - Comprehensive error handling and logging
 */
class PDFGenerator
{
    /**
     * Document types supported by the system
     * Each type has specific business rules and formatting
     */
    public const DOCUMENT_TYPES = [
        'invoice' => 'Invoice',
        'quote' => 'Quote', 
        'deposit' => 'Deposit',
        'receipt' => 'Payment Receipt',
    ];

    protected array $config;
    protected array $items = [];
    protected float $subtotal = 0;
    protected float $vatTotal = 0;
    protected float $total = 0;

    /**
     * Main PDF generation method with comprehensive error handling
     * Returns both filename and content for flexible usage
     */
    public function generatePdfForOrder(Order $order, string $type): array
    {
        try {
            // Generate meaningful filename with customer and order identifiers
            $firstName = substr($order->customer->first_name ?? 'Unknown', 0, 3);
            $lastName = substr($order->customer->last_name ?? 'Unknown', 0, 3);
            $orderNumber = str_pad($order->id, 3, '0', STR_PAD_LEFT);
            $filename = sprintf('%s-%s%s-%s.pdf', ucfirst($type), $firstName, $lastName, $orderNumber);

            // Prepare all document data with business logic
            $this->config = $this->prepareDocumentConfig($order, $type);
            $this->items = $this->prepareDocumentItems($order, $type);
            $totals = $this->calculateTotals($order, $type);
            $this->subtotal = $totals['subtotal'];
            $this->vatTotal = $totals['vat'];
            $this->total = $totals['total'];

            return [
                'filename' => $filename,
                'content' => $this->generate(),
            ];

        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'order_id' => $order->id,
                'document_type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Calculate totals based on document type business rules
     * Handles complex scenarios like deposit calculations and VAT
     */
    public function calculateTotals(Order $order, string $type): array
    {
        if ($type === 'deposit') {
            // Deposit is 10% of total order value
            return [
                'subtotal' => $order->subtotal * 0.10,
                'vat' => $order->vat_total * 0.10,
                'total' => $order->total * 0.10,
            ];
        }

        return [
            'subtotal' => $order->subtotal,
            'vat' => $order->vat_total,
            'total' => $order->total,
        ];
    }

    /**
     * Prepare document items with type-specific business logic
     * Transforms order items based on document purpose
     */
    public function prepareDocumentItems(Order $order, string $type): array
    {
        if ($type === 'deposit') {
            // Deposit documents show simplified line item
            return [[
                'name' => 'Booking Deposit (10%)',
                'quantity' => 1,
                'price' => $order->total * 0.10,
                'vat' => 0,
            ]];
        }

        if ($type === 'receipt') {
            // Receipt shows payment received
            return [[
                'name' => 'Payment Received',
                'quantity' => 1,
                'price' => $order->subtotal,
                'vat' => $order->vat_total,
            ]];
        }

        // Standard documents show all order items
        return $order->orderItems->map(function ($item) {
            return [
                'name' => $item->title,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'vat' => $item->has_vat ? ($item->vat_total / $item->quantity) : 0,
            ];
        })->all();
    }

    /**
     * Prepare comprehensive document configuration
     * Merges base config with type-specific settings
     */
    public function prepareDocumentConfig(Order $order, string $type): array
    {
        $baseConfig = [
            'title' => strtoupper($type),
            'date' => Carbon::today()->format('d/m/Y'),
            'from' => [
                'name' => 'Removals',
                'phone' => '07XXXXXXXXX',
                'email' => 'hello@example.co.uk',
                'address' => 'XXX XXX XXX',
            ],
            'to' => [
                'name' => $order->customer->first_name . ' ' . $order->customer->last_name,
                'phone' => $order->customer->phone,
                'email' => $order->customer->email,
            ],
            'description' => $order->description,
            'invoice_number' => $order->friendly_id,
            'addresses' => [
                'from' => $order->fromAddress?->full_address,
                'to' => $order->toAddress?->full_address,
            ],
            'footer' => 'For a comprehensive overview of our Terms of Service, please feel welcome to visit our website',
            'template_specific' => $this->getTemplateSpecificConfig($type),
        ];

        return array_merge($baseConfig, $this->getTypeSpecificConfig($type));
    }

    /**
     * Template-specific configuration for document features
     * Controls which sections appear in each document type
     */
    protected function getTemplateSpecificConfig(string $type): array
    {
        return match ($type) {
            'invoice' => [
                'show_vat' => true,
                'show_payment_details' => true,
                'show_totals' => true,
            ],
            'quote' => [
                'show_vat' => false,
                'show_payment_details' => false,
                'show_terms' => true,
            ],
            'deposit' => [
                'show_vat' => true,
                'show_payment_details' => true,
                'show_terms' => true,
            ],
            'receipt' => [
                'show_vat' => true,
                'show_payment_details' => true,
                'show_thank_you' => true,
            ],
            default => [],
        };
    }

    /**
     * Type-specific configuration with business terms and payment details
     * Each document type has specific legal and business requirements
     */
    protected function getTypeSpecificConfig(string $type): array
    {
        return match ($type) {
            'quote' => [
                'payment_terms' => '- Payment is kindly requested to be settled within 24 hours following the conclusion of your service.
                    <br>- A 10% deposit is necessary to make a booking and secure your chosen date.',
            ],
            'deposit' => [
                'payment_details' => 'XXX',
                'payment_terms' => '<strong>Deposit Terms:</strong><br>
                    Booking Deposit is 10% of the total fee and is deductible from the final amount. Is non-refundable.
                    Deposit locks in your chosen date and secures availability.',
            ],
            'invoice' => [
                'payment_details' => 'XXX',
            ],
            'receipt' => [
                'payment_details' => '<strong>Thank you for your payment!</strong><br>
                    We appreciate your business and best of luck in your new home.',
            ],
            default => [],
        ];
    }

    /**
     * Generate PDF content with comprehensive error handling
     */
    protected function generate(): string
    {
        try {
            return $this->generatePdfContent();
        } catch (\Exception $e) {
            Log::error('PDF content generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'config_title' => $this->config['title'] ?? 'unknown',
            ]);
            throw new \Exception('Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Advanced PDF generation with custom styling and professional formatting
     * Uses Spatie Laravel PDF with Browsershot for high-quality output
     */
    protected function generatePdfContent(): string
    {
        $pdf = Pdf::view('pdf.document', [
            'config' => $this->config,
            'items' => $this->items,
            'subtotal' => $this->subtotal,
            'vatTotal' => $this->vatTotal,
            'total' => $this->total,
        ])
        ->footerView('pdf.document-footer', [
            'config' => $this->config,
        ])
        ->format('a4')
        ->margins(15, 15, 25, 15) // Professional margins
        ->withBrowsershot(function ($browsershot) {
            $browsershot->setNodeBinary(env('NODE_BINARY', 'node'))
                ->noSandbox()
                ->format('A4')
                ->timeout(120)
                ->waitUntilNetworkIdle(); // Ensure all content loads
        });

        // Use temporary file system for memory management
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . '/' . uniqid() . '.pdf';
        $pdf->save($tempPath);

        $content = file_get_contents($tempPath);
        unlink($tempPath); // Clean up temporary file

        return $content;
    }
}