<?php

namespace App\Models\CRM;

use App\Models\CRM\Traits\HasFriendlyId;
use App\Models\CRM\Traits\HasNotes;
use App\Models\CRM\Traits\HasReminders;
use App\Models\CRM\Utility\Reminder;
use App\Models\CRM\Utility\Template;
use App\Services\GoogleCalendarService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Order Model - Core business entity with advanced automation
 * 
 * This model demonstrates enterprise-level business process automation including:
 * - Automatic Google Calendar integration with status-based event management
 * - Dynamic reminder and email automation based on lead sources
 * - Complex status workflow management
 * - Shareable customer links with signed URLs
 * - Automatic total calculations and VAT handling
 * - Comprehensive audit trail and lifecycle management
 */
class Order extends Model
{
    use HasFactory, HasFriendlyId, HasNotes, HasReminders, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'from_address_id', 
        'to_address_id',
        'order_date',
        'move_date',
        'end_date',
        'status',
        'total',
        'subtotal', 
        'vat_total',
        'description',
        'notes',
        'provider',
        'accepted',
        'completed_at',
        'cancelled_at',
        'google_id',
    ];

    protected $casts = [
        'order_date' => 'date',
        'move_date' => 'date', 
        'end_date' => 'date',
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'vat_total' => 'decimal:2',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'accepted' => 'boolean',
    ];

    // Business status constants for workflow management
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PENDING_DEPOSIT = 'pending_deposit';
    public const STATUS_PENDING_DATE = 'pending_date';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_ATTEMPTED_CONTACT = 'attempted_contact';
    public const STATUS_AWAITING_RESPONSE = 'awaiting_response';

    /**
     * Model event handlers for business process automation
     */
    protected static function booted()
    {
        // Automatically create reminders when order is created
        static::created(function ($order) {
            $order->createReminders();
        });

        // Handle Google Calendar integration on status changes
        static::updated(function ($order) {
            if (!$order->wasChanged([
                'status', 'move_date', 'end_date', 'description', 
                'notes', 'from_address_id', 'to_address_id', 
                'total', 'customer_id'
            ])) {
                return;
            }

            $calendarService = new GoogleCalendarService;
            $newStatus = $order->status;
            $oldStatus = $order->getOriginal('status');
            
            // Define which statuses should have calendar events
            $calendarStatuses = [self::STATUS_PENDING_DEPOSIT, self::STATUS_BOOKED];
            $shouldHaveCalendarEvent = in_array($newStatus, $calendarStatuses);
            $hadCalendarEvent = in_array($oldStatus, $calendarStatuses);

            try {
                if ($shouldHaveCalendarEvent) {
                    // Create or update calendar event
                    $event = $calendarService->createOrUpdateEvent($order);
                    if ($event && isset($event->id)) {
                        $order->google_id = $event->id;
                        $order->saveQuietly(); // Prevent infinite loop
                    }
                } elseif ($hadCalendarEvent && !$shouldHaveCalendarEvent) {
                    // Remove calendar event when status no longer requires it
                    $calendarService->deleteBooking($order);
                    $order->google_id = null;
                    $order->saveQuietly();
                }
            } catch (\Exception $e) {
                Log::error("Error managing calendar event for order {$order->id}: " . $e->getMessage());
            }
        });

        // Clean up Google Calendar events when order is deleted
        static::deleting(function ($order) {
            if ($order->google_id) {
                try {
                    (new GoogleCalendarService)->deleteBooking($order);
                } catch (\Exception $e) {
                    Log::error("Error deleting calendar event for order {$order->id}: " . $e->getMessage());
                }
            }
        });
    }

    /**
     * Calculate and update order totals from line items
     * Handles complex VAT calculations and subtotal aggregation
     */
    public function updateTotal(): void
    {
        $items = $this->orderItems()->get();

        $this->subtotal = $items->sum(function ($item) {
            return $item->getSubtotalAttribute();
        });

        $this->vat_total = $items->sum('vat_total');
        $this->total = $this->subtotal + $this->vat_total;

        $this->saveQuietly(); // Prevent triggering model events
    }

    /**
     * Generate secure shareable link for customer access
     * Creates temporary signed URL valid for 30 days
     */
    public function getShareableLink(): string
    {
        return URL::temporarySignedRoute(
            'orders.clientView',
            now()->addDays(30),
            ['order' => $this->id]
        );
    }

    /**
     * Get human-readable status options for UI
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ATTEMPTED_CONTACT => 'Attempted Contact',
            self::STATUS_CONTACTED => 'Contacted', 
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PENDING_DEPOSIT => 'Pending Deposit',
            self::STATUS_PENDING_DATE => 'Pending Date',
            self::STATUS_BOOKED => 'Booked',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    // Eloquent Relationships
    
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fromAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'from_address_id');
    }

    public function toAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'to_address_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function notable_notes()
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    // Business Logic Methods

    /**
     * Mark order as completed with timestamp
     */
    public function complete(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Cancel order with timestamp and cleanup
     */
    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->save();
    }

    /**
     * Update acceptance status based on order items
     */
    public function updateAcceptedStatus(): void
    {
        $this->accepted = $this->orderItems()->where('accepted', true)->exists();
        $this->saveQuietly();
    }

    /**
     * Get start time for calendar integration
     */
    public function getStartsAtAttribute()
    {
        return $this->move_date;
    }

    /**
     * Create automated reminders and welcome emails based on lead source
     * Implements business rules for different lead providers
     */
    protected function createReminders(): void
    {
        $allowedProviders = [
            'ReallyMoving', 
            'CompareMyMove', 
            'PinLocal', 
            'RemovalsIndex', 
            'Contact Form'
        ];

        // Only create automated reminders for specific lead sources
        if (Str::contains($this->provider, $allowedProviders)) {
            $welcomeTemplate = Template::where('name', 'Welcome Email')->first();

            if ($welcomeTemplate) {
                $welcomeReminder = $this->createWelcomeReminder($welcomeTemplate->id);
                if ($welcomeReminder) {
                    try {
                        // Automatically send welcome email
                        $welcomeReminder->send();
                    } catch (\Exception $e) {
                        Log::error("Failed to send Welcome Email for order {$this->id}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Create welcome email reminder with order context
     */
    protected function createWelcomeReminder($templateId): ?Reminder
    {
        return Reminder::create([
            'name' => 'Welcome Email',
            'template_id' => $templateId,
            'type' => 'email',
            'recipient_type' => Customer::class,
            'recipient_id' => $this->customer_id,
            'due_date' => now(),
            'sent' => false,
            'additional_data' => ['order_id' => $this->id],
        ]);
    }

    /**
     * Complex date-based query scope for reporting and calendar views
     * Handles multiple date fields with fallback logic
     */
    public function scopeForDate($query, $month, $year)
    {
        return $query->where(function ($query) use ($month, $year) {
            $query->where(function ($q) use ($month, $year) {
                // First priority: end_date if available
                $q->whereNotNull('end_date')
                  ->whereMonth('end_date', $month)
                  ->whereYear('end_date', $year);
            })->orWhere(function ($q) use ($month, $year) {
                // Second priority: move_date if no end_date
                $q->whereNull('end_date')
                  ->whereNotNull('move_date')
                  ->whereMonth('move_date', $month)
                  ->whereYear('move_date', $year);
            })->orWhere(function ($q) use ($month, $year) {
                // Third priority: order_date if neither end_date nor move_date
                $q->whereNull('end_date')
                  ->whereNull('move_date')
                  ->whereMonth('order_date', $month)
                  ->whereYear('order_date', $year);
            });
        });
    }
}