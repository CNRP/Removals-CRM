<?php

namespace App\Models\CRM;

use App\Models\CRM\Traits\HasReminders;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Customer Model - Core CRM Entity
 * 
 * Central customer management model demonstrating sophisticated relationship 
 * handling, computed attributes with intelligent fallback logic, and optimized 
 * query patterns for CRM operations. This model serves as the foundation for 
 * customer lifecycle management and business intelligence.
 * 
 * Key Technical Features:
 * - Smart relationship management with primary/fallback logic
 * - Computed attributes using Laravel's modern Attribute casting
 * - Optimized single-record relationships using latestOfMany()
 * - Polymorphic relationships for flexible reminder system
 * - Intelligent address handling with graceful degradation
 */
class Customer extends Model
{
    use HasFactory, HasReminders;

    /**
     * Mass assignable attributes for customer data
     * Follows Laravel best practices for security
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'notes',
    ];

    // =============================================================================
    // CORE RELATIONSHIPS - Foundation of CRM functionality
    // =============================================================================

    /**
     * Customer orders relationship
     * 
     * One-to-many relationship tracking complete order history.
     * Essential for customer lifecycle analysis and business intelligence.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Customer addresses relationship
     * 
     * Supports multiple addresses per customer with flexible management.
     * Handles both pickup and delivery addresses for removals business.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Primary address relationship with intelligent fallback
     * 
     * Uses dedicated relationship for performance optimization.
     * Implements business rule: customers should have one primary address.
     */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_primary', true);
    }

    /**
     * Customer reminder automation relationship
     * 
     * Polymorphic relationship supporting various reminder types:
     * - Follow-up emails
     * - Quote reminders  
     * - Booking confirmations
     * - Anniversary messages
     */
    public function reminders()
    {
        return $this->morphMany(Utility\Reminder::class, 'recipient');
    }

    /**
     * Email communication history
     * 
     * Tracks all customer email interactions for:
     * - Communication audit trail
     * - Response tracking
     * - Engagement analysis
     */
    public function emails()
    {
        return $this->hasMany(CustomerEmail::class);
    }

    // =============================================================================
    // OPTIMIZED SINGLE RECORD RELATIONSHIPS - Performance optimization
    // =============================================================================

    /**
     * Most recent order for quick access
     * 
     * Uses Laravel's latestOfMany() for optimal performance.
     * Critical for dashboard widgets and customer status determination.
     */
    public function latestOrder(): HasOne
    {
        return $this->hasOne(Order::class)->latestOfMany('updated_at');
    }

    /**
     * Most recent email for communication tracking
     * 
     * Enables quick access to last customer interaction without
     * loading entire email history. Used in customer listings.
     */
    public function lastEmail(): HasOne
    {
        return $this->hasOne(CustomerEmail::class)->latestOfMany('email_date');
    }

    // =============================================================================
    // COMPUTED ATTRIBUTES - Business logic encapsulation
    // =============================================================================

    /**
     * Full name computed attribute
     * 
     * Provides consistent name formatting across the entire application.
     * Uses Laravel's modern Attribute casting for clean implementation.
     * 
     * @return Attribute<string, never>
     */
    public function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}",
        );
    }

    /**
     * Primary address with intelligent fallback logic
     * 
     * Business rule implementation: Always return a usable address
     * 1. Try primary address first (business preference)
     * 2. Fall back to first available address (graceful degradation)
     * 3. Return null if no addresses exist (safe handling)
     * 
     * This pattern prevents UI errors when displaying customer information.
     * 
     * @return Attribute<Address|null, never>
     */
    public function primaryOrFirstAddress(): Attribute
    {
        return Attribute::make(
            get: function () {
                // First preference: designated primary address
                $primary = $this->primaryAddress;
                if ($primary) {
                    return $primary;
                }
                
                // Fallback: use first available address
                return $this->addresses()->first();
            }
        );
    }

    /**
     * Safe address string with null protection
     * 
     * Provides human-readable address string with graceful handling
     * of missing data. Essential for reliable UI display across
     * customer listings, quotes, and invoices.
     * 
     * @return Attribute<string, never>
     */
    public function primaryOrFirstAddressFullAddress(): Attribute
    {
        return Attribute::make(
            get: function () {
                $address = $this->primaryOrFirstAddress;
                
                // Safe access with business-appropriate fallback message
                return $address ? $address->full_address : 'No address available';
            }
        );
    }
}