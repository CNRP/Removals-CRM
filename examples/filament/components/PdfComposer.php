<?php

namespace App\Filament\CRM\Components;

use App\Models\CRM\Order;
use App\Services\PDFGenerator;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\View;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF Composer Component - Live Document Generation with Preview
 * 
 * This component demonstrates advanced PDF generation with real-time preview
 * capabilities, showcasing sophisticated business document automation and
 * user experience design. It integrates complex business logic with intuitive
 * interface design to provide immediate visual feedback during document creation.
 * 
 * Key Technical Achievements:
 * - Real-time PDF preview with live updates as options change
 * - Business rule visualization showing calculated totals and formatting
 * - Type-specific document generation with different business logic
 * - Memory-efficient streaming downloads for large documents
 * - Comprehensive error handling with detailed user feedback
 * - Integration with existing business services and data models
 * 
 * Business Features:
 * - Multiple document types (quotes, invoices, deposits, receipts)
 * - Live preview prevents errors and ensures professional output
 * - Automatic calculation display (subtotals, VAT, deposits)
 * - Context-aware content based on customer and order data
 * - Professional document formatting with business branding
 * 
 * Advanced Patterns Demonstrated:
 * - Custom Filament component with complex form interactions
 * - Real-time reactive updates using live wire properties
 * - Service integration for business logic separation
 * - Memory management for large file operations
 * - Streaming responses for optimal user experience
 */
class PdfComposer extends Component implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.components.pdf-composer';

    /**
     * Customer context for document personalization
     * Enables customer-specific document generation and branding
     */
    public ?int $initialCustomerId = null;

    /**
     * Order context for document content and calculations
     * Provides business data for document generation and preview
     */
    public ?int $initialOrderId = null;

    /**
     * Form state management for reactive updates
     * Stores current form values for real-time preview generation
     */
    public ?array $formState = [];

    /**
     * Live preview data cache
     * Stores processed preview information to avoid unnecessary recalculation
     */
    protected ?array $previewData = null;

    // =============================================================================
    // COMPONENT LIFECYCLE AND FORM CONFIGURATION
    // =============================================================================

    /**
     * Initialize component with form setup
     * Prepares form for immediate use with default values
     */
    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * Configure form schema with reactive preview capabilities
     * 
     * Creates sophisticated form with:
     * - Hidden context fields for business data
     * - Document type selection with live preview updates
     * - Embedded preview component showing real-time changes
     * - Professional validation and error handling
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getChildComponents())
            ->statePath('formState'); // Bind to component state
    }

    /**
     * Define form components with advanced business integration
     * 
     * Builds reactive form with live preview capabilities and
     * intelligent document type selection based on business rules.
     */
    public function getChildComponents(): array
    {
        return [
            // Hidden context fields for business integration
            Hidden::make('customer_id')
                ->default($this->initialCustomerId),
            
            Hidden::make('order_id')
                ->default($this->initialOrderId),

            // Document type selector with live preview updates
            Select::make('pdf_type')
                ->label('PDF Type')
                ->options(PDFGenerator::DOCUMENT_TYPES) // Business-defined document types
                ->live() // Enable real-time updates
                ->afterStateUpdated(function (Get $get) {
                    // Trigger preview update when type changes
                    $this->updatePreview($get('pdf_type'), $get('order_id'));
                })
                ->required(),

            // Live preview component with conditional visibility
            View::make('preview')
                ->view('filament.components.pdf-preview-wrapper')
                ->visible(fn (Get $get) => filled($get('pdf_type'))) // Show only when type selected
                ->viewData([
                    'config' => $this->previewData['config'] ?? null,
                    'items' => $this->previewData['items'] ?? [],
                    'subtotal' => $this->previewData['totals']['subtotal'] ?? 0,
                    'vatTotal' => $this->previewData['totals']['vat'] ?? 0,
                    'total' => $this->previewData['totals']['total'] ?? 0,
                ]),
        ];
    }

    // =============================================================================
    // LIVE PREVIEW SYSTEM - Real-time document visualization
    // =============================================================================

    /**
     * Update preview data in real-time based on form changes
     * 
     * Generates live preview of document content, formatting, and calculations
     * without creating the actual PDF file. This provides immediate feedback
     * and prevents errors before final document generation.
     * 
     * @param string|null $pdfType Selected document type
     * @param int|null $orderId Order context for document data
     */
    protected function updatePreview(?string $pdfType, ?int $orderId): void
    {
        // Validate required parameters
        if (!$pdfType || !$orderId) {
            $this->previewData = null;
            return;
        }

        try {
            // Load order with complete relationship data for preview
            $order = Order::with(['customer', 'orderItems', 'fromAddress', 'toAddress'])
                ->findOrFail($orderId);

            $generator = app(PDFGenerator::class);

            // Generate preview data using same business logic as final PDF
            $this->previewData = [
                'config' => $generator->prepareDocumentConfig($order, $pdfType),
                'items' => $generator->prepareDocumentItems($order, $pdfType),
                'totals' => $generator->calculateTotals($order, $pdfType),
            ];

        } catch (\Exception $e) {
            Log::error('PDF preview generation failed', [
                'error' => $e->getMessage(),
                'pdfType' => $pdfType,
                'orderId' => $orderId,
                'trace' => $e->getTraceAsString(),
            ]);

            // Clear preview data on error
            $this->previewData = null;

            // User-friendly error notification
            Notification::make()
                ->title('Error')
                ->body('Failed to generate preview: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    // =============================================================================
    // PDF GENERATION - Final document creation and download
    // =============================================================================

    /**
     * Generate final PDF document with streaming download
     * 
     * Creates professional business document using validated form data
     * and streams it directly to user for optimal performance and
     * memory management with large documents.
     * 
     * @param array $formData Validated form submission data
     * @return StreamedResponse Direct download stream
     * @throws \Exception On generation failure
     */
    public function generate(array $formData): StreamedResponse
    {
        try {
            // Load order with complete business context
            $order = Order::with(['customer', 'orderItems', 'fromAddress', 'toAddress'])
                ->findOrFail($formData['order_id']);

            // Generate PDF using business service with validated parameters
            $result = app(PDFGenerator::class)->generatePdfForOrder($order, $formData['pdf_type']);

            // Stream PDF directly to user for optimal performance
            return response()->streamDownload(
                fn () => print($result['content']),
                $result['filename'],
                ['Content-Type' => 'application/pdf']
            );

        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'formData' => $formData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Detailed error notification for troubleshooting
            Notification::make()
                ->title('Error')
                ->body("Failed to generate {$formData['pdf_type']} PDF: " . $e->getMessage())
                ->danger()
                ->send();

            throw $e; // Re-throw for proper error handling
        }
    }

    // =============================================================================
    // COMPONENT FACTORY AND CONFIGURATION
    // =============================================================================

    /**
     * Create new PDF composer instance
     * Follows Filament component pattern for clean instantiation
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Set customer context for document personalization
     * Enables fluent configuration: PdfComposer::make()->customerId(123)
     */
    public function customerId(?int $customerId): static
    {
        $this->initialCustomerId = $customerId;
        return $this;
    }

    /**
     * Set order context for business integration
     * Enables order-specific document generation and preview
     */
    public function orderId(?int $orderId): static
    {
        $this->initialOrderId = $orderId;
        return $this;
    }
}