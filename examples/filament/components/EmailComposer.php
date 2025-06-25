<?php

namespace App\Filament\CRM\Components;

use App\Models\CRM\Customer;
use App\Models\CRM\Order;
use App\Models\CRM\Utility\Template;
use App\Services\Google\GoogleServicesFacade;
use App\Services\PDFGenerator;
use App\Services\TemplateCompilerService;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use FilamentTiptapEditor\TiptapEditor;
use Illuminate\Support\Facades\Storage;

/**
 * Email Composer Component - Advanced Template-Based Email System
 * 
 * This component demonstrates sophisticated email composition with integrated
 * business automation, template compilation, and intelligent PDF attachment.
 * It showcases advanced Filament form composition, real-time template processing,
 * and seamless integration with business services.
 * 
 * Key Technical Achievements:
 * - Dynamic template compilation with business context
 * - Automatic PDF generation and attachment based on email type
 * - Gmail API integration with SMTP fallback mechanisms
 * - Real-time form updates using reactive components
 * - Intelligent file management with automatic cleanup
 * - Context-aware customer and order integration
 * 
 * Business Features:
 * - Template-driven consistent communication
 * - Automatic document attachment (quotes→quote PDF, invoice→invoice PDF)
 * - Customer portal integration through dynamic links
 * - Professional email formatting with rich text editing
 * - File attachment support with validation and storage management
 * 
 * Advanced Patterns Demonstrated:
 * - Custom Filament component with complex business logic
 * - Service integration through dependency injection
 * - Real-time reactive form updates with afterStateUpdated
 * - Error handling with user-friendly notifications
 * - File lifecycle management with automatic cleanup
 */
class EmailComposer extends Component
{
    protected string $view = 'filament.components.email-composer';

    /**
     * Initial customer context for email composition
     * Enables customer-specific template compilation and addressing
     */
    public ?int $initialCustomerId = null;

    /**
     * Initial order context for business document integration
     * Enables order-specific template compilation and PDF generation
     */
    public ?int $initialOrderId = null;

    /**
     * Email type to PDF type mapping for automatic attachments
     * 
     * Business rule: Certain email templates automatically include
     * corresponding business documents to streamline communication.
     */
    protected array $emailTypeToPdfType = [
        'email-quote' => 'quote',      // Quote emails include quote PDF
        'email-invoice' => 'invoice',  // Invoice emails include invoice PDF
        'email-booking' => 'deposit',  // Booking emails include deposit invoice
    ];

    // =============================================================================
    // COMPONENT FACTORY AND CONFIGURATION
    // =============================================================================

    /**
     * Create new email composer instance
     * Follows Filament component pattern for clean instantiation
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Set customer context for email composition
     * Enables fluent configuration: EmailComposer::make()->customerId(123)
     */
    public function customerId(?int $customerId): static
    {
        $this->initialCustomerId = $customerId;
        return $this;
    }

    /**
     * Set order context for business integration
     * Enables order-specific template compilation and PDF attachment
     */
    public function orderId(?int $orderId): static
    {
        $this->initialOrderId = $orderId;
        return $this;
    }

    // =============================================================================
    // EMAIL SENDING LOGIC - Core business functionality
    // =============================================================================

    /**
     * Send email with comprehensive business integration
     * 
     * Handles complete email sending workflow including:
     * - Customer validation and context loading
     * - Gmail API integration with fallback to SMTP
     * - File attachment processing and cleanup
     * - Success/error notification with detailed feedback
     * 
     * @param array $formData Complete form submission data
     */
    public function send(array $formData): void
    {
        $googleService = app(GoogleServicesFacade::class);
        $customer = Customer::find($formData['customer_id']);

        // Validate customer existence before proceeding
        if (!$customer) {
            Notification::make()
                ->title('Error')
                ->body('Customer not found.')
                ->danger()
                ->send();
            return;
        }

        // Prepare comprehensive email data with business context
        $emailData = [
            'to' => $customer->email,
            'subject' => $formData['subject'],
            'body' => $formData['body'],
            'attachments' => $formData['attachments'] ?? [],
            'customer_id' => $formData['customer_id'],
            'order_id' => $formData['order_id'] ?? null,
            'template_id' => $formData['template_id'] ?? null,
        ];

        try {
            // Send via Gmail API with automatic fallback
            $googleService->sendEmail($emailData);

            // Cleanup: Remove temporary attachments after successful sending
            if (!empty($emailData['attachments'])) {
                foreach ($emailData['attachments'] as $attachment) {
                    Storage::disk('public')->delete($attachment);
                }
            }

            // Success notification with customer confirmation
            Notification::make()
                ->title('Success')
                ->body("Your email has been sent successfully to {$customer->email}.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            // Detailed error notification for troubleshooting
            Notification::make()
                ->title('Error')
                ->body("Failed to send email to {$customer->email}: " . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    // =============================================================================
    // PDF AUTOMATION - Intelligent document attachment
    // =============================================================================

    /**
     * Generate and automatically attach PDF based on email type
     * 
     * Implements business rule: certain email types automatically include
     * corresponding business documents. This eliminates manual steps and
     * ensures consistent professional communication.
     * 
     * @param string $emailType Template type triggering PDF generation
     * @param Set $set Form state setter for updating attachments
     */
    protected function generateAndAttachPdf(string $emailType, Set $set): void
    {
        if (!$this->initialOrderId) {
            Notification::make()
                ->title('Warning')
                ->body('No order ID provided for PDF generation.')
                ->warning()
                ->send();
            return;
        }

        try {
            // Map email type to corresponding PDF document type
            $pdfType = $this->emailTypeToPdfType[$emailType];

            logger()->debug('Generating PDF', [
                'emailType' => $emailType,
                'pdfType' => $pdfType,
                'orderId' => $this->initialOrderId,
            ]);

            // Load order with all relationships for PDF generation
            $order = Order::with(['customer', 'orderItems', 'fromAddress', 'toAddress'])
                ->findOrFail($this->initialOrderId);

            // Generate PDF using business service
            $result = app(PDFGenerator::class)->generatePdfForOrder($order, $pdfType);

            // Intelligent file path construction with customer organization
            $fileUploadComponent = collect($this->getChildComponentContainer()->getComponents())
                ->first(function ($component) {
                    return $component instanceof FileUpload;
                });

            $customerId = $this->getChildComponentContainer()->getState()['customer_id'] ?? null;

            // Organize files by customer for better file management
            $directory = $fileUploadComponent?->getDirectory() ?? 'email-attachments';
            if ($customerId) {
                $directory .= "/{$customerId}";
            }

            $path = "{$directory}/{$result['filename']}";

            // Ensure directory exists for file storage
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            // Store generated PDF
            Storage::disk('public')->put($path, $result['content']);

            // Update form state with new attachment
            $attachments = $this->getChildComponentContainer()->getState()['attachments'] ?? [];
            $attachments[] = $path;
            $set('attachments', $attachments);

            // Success notification with PDF type confirmation
            Notification::make()
                ->title('Success')
                ->body(ucfirst($pdfType) . ' PDF automatically attached.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            logger()->error('PDF Generation failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Error')
                ->body('Failed to generate PDF: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    // =============================================================================
    // FORM COMPONENT CONFIGURATION - Advanced form building
    // =============================================================================

    /**
     * Define email composer form components with business integration
     * 
     * Creates sophisticated form with:
     * - Hidden context fields for business data
     * - Dynamic template selection with real-time compilation
     * - File upload with validation and organization
     * - Rich text editor for professional email formatting
     * - Reactive updates based on template selection
     */
    public function getChildComponents(): array
    {
        return [
            // Hidden context fields for business integration
            Hidden::make('customer_id')
                ->default($this->initialCustomerId),
            
            Hidden::make('order_id')
                ->default($this->initialOrderId),

            // Auto-populated recipient field with customer email
            TextInput::make('to')
                ->label('To')
                ->disabled()
                ->default(function () {
                    $customer = Customer::find($this->initialCustomerId);
                    return $customer ? $customer->email : '';
                }),

            // Dynamic template selection with business context
            Select::make('template_id')
                ->label('Template')
                ->options(function () {
                    return Template::where('type', 'like', 'email-%')
                        ->orWhere('type', 'email')
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->reactive()
                ->afterStateUpdated(function ($state, Set $set) {
                    if ($state) {
                        $this->loadTemplate($state, $set);
                    }
                }),

            // File attachment with business-appropriate validation
            FileUpload::make('attachments')
                ->multiple()
                ->directory('email-attachments')
                ->preserveFilenames()
                ->maxSize(5120) // 5MB limit for business documents
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/*',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ]),

            // Email subject with validation
            TextInput::make('subject')
                ->required()
                ->maxLength(255),

            // Rich text editor for professional email formatting
            TiptapEditor::make('body')
                ->required()
                ->columnSpan('full'),
        ];
    }

    // =============================================================================
    // TEMPLATE PROCESSING - Dynamic content compilation
    // =============================================================================

    /**
     * Load and process template with business context
     * 
     * Handles complete template workflow:
     * - Template loading and validation
     * - Dynamic compilation with customer and order data
     * - Automatic PDF attachment for business document emails
     * - Real-time form updates with compiled content
     * 
     * @param mixed $templateId Selected template ID
     * @param Set $set Form state setter for updates
     */
    protected function loadTemplate($templateId, Set $set)
    {
        $template = Template::find($templateId);
        if (!$template) {
            return;
        }

        // Compile template with current business context
        $compiledTemplate = $this->compileTemplate($template);
        $set('subject', $compiledTemplate['subject']);
        $set('body', $compiledTemplate['body']);

        // Check for automatic PDF attachment requirement
        if (isset($this->emailTypeToPdfType[$template->type])) {
            $this->generateAndAttachPdf($template->type, $set);
        }
    }

    /**
     * Compile template with business context data
     * 
     * Integrates with TemplateCompilerService to process dynamic content
     * including customer information, order details, and business-specific
     * placeholders like shareable portal links.
     * 
     * @param Template $template Template to compile
     * @return array Compiled subject and body content
     */
    protected function compileTemplate(Template $template)
    {
        $compiler = app(TemplateCompilerService::class);
        $data = [
            'customer_id' => $this->initialCustomerId,
            'order_id' => $this->initialOrderId,
        ];

        return $compiler->compile($template, $data);
    }
}