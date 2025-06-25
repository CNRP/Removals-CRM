<?php

namespace App\Filament\CRM\Widgets;

use App\Filament\CRM\Resources\OrderResource;
use App\Filament\CRM\Resources\TaskResource;
use App\Models\CRM\Order;
use App\Models\TaskManagement\Task;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

/**
 * Booking Calendar Widget - Advanced Interactive Business Calendar
 * 
 * This widget demonstrates sophisticated calendar visualization with intelligent
 * multi-day event handling, real-time filtering, and comprehensive business
 * integration. It showcases advanced Filament widget development including
 * custom event positioning algorithms, modal interactions, and responsive
 * data management.
 * 
 * Key Technical Achievements:
 * - Intelligent event positioning preventing overlaps on busy days
 * - Multi-day event spanning with consistent visual representation
 * - Real-time status filtering with automatic calendar updates
 * - Mixed event types (orders + surveys) in unified calendar view
 * - Complex date range calculations for optimal performance
 * - Interactive day detail modals with contextual information
 * 
 * Business Features:
 * - Visual workload management for operations team
 * - Status-based color coding for quick priority identification
 * - Integrated links to detailed order/task management
 * - Revenue visibility directly in calendar interface
 * 
 * Advanced Patterns Demonstrated:
 * - Event-driven component communication via Livewire events
 * - Sophisticated state management with reactive properties
 * - Performance optimization through intelligent query building
 * - Complex UI interactions with modal forms and info lists
 */
class BookingCalendarWidget extends Widget implements HasActions, HasForms
{
    use HasWidgetShield;      // Permission-based widget visibility
    use InteractsWithActions; // Enable modal actions and forms
    use InteractsWithForms;   // Form handling capabilities

    protected static ?int $sort = 1;
    protected static string $view = 'filament.c-r-m.resources.c-r-m-resource.widgets.booking-calendar-widget';
    protected int|string|array $columnSpan = 'full'; // Full-width widget

    // =============================================================================
    // COMPONENT STATE MANAGEMENT - Real-time reactive properties
    // =============================================================================

    /**
     * Current calendar date focus
     * Controls which month/week is displayed
     */
    public $currentDate;

    /**
     * Day detail modal data
     * Stores detailed information for day view modal
     */
    public ?array $dayViewData = null;

    /**
     * Processed calendar weeks data
     * Contains fully processed calendar grid with positioned events
     */
    public $calendarWeeks;

    /**
     * Active status filters
     * Controls which order statuses are visible in calendar
     */
    public $selectedStatuses = [];

    // =============================================================================
    // COMPONENT LIFECYCLE - Initialization and setup
    // =============================================================================

    /**
     * Initialize widget with default business-focused settings
     * Sets up calendar for immediate operational use
     */
    public function mount(): void
    {
        $this->currentDate = Carbon::now();
        
        // Default to most operationally relevant statuses
        $this->selectedStatuses = [
            Order::STATUS_BOOKED,        // Confirmed jobs
            Order::STATUS_PENDING_DEPOSIT, // Needs attention
        ];
        
        $this->loadAndProcessEvents();
    }

    /**
     * Real-time filter updates via Livewire events
     * Enables dynamic calendar filtering without page reload
     * 
     * @param array $statuses Updated status filter selection
     */
    #[On('update-calendar-filters')]
    public function updateFilters($statuses): void
    {
        $this->selectedStatuses = $statuses;
        $this->loadAndProcessEvents(); // Rebuild calendar with new filters
    }

    // =============================================================================
    // MODAL ACTIONS - Interactive day detail system
    // =============================================================================

    /**
     * Day detail modal action configuration
     * 
     * Creates comprehensive modal showing all events for selected day.
     * Demonstrates advanced Filament modal techniques with dynamic content
     * and contextual business information.
     */
    public function viewDay(): Action
    {
        return Action::make('viewDay')
            ->modalWidth(MaxWidth::ThreeExtraLarge) // Large modal for detailed info
            ->modalHeading(fn () => $this->dayViewData['date'] ?? 'View Day')
            ->infolist(fn (Infolist $infolist): Infolist => $infolist
                ->state([
                    'date' => $this->dayViewData['date'] ?? '',
                    'moves' => $this->dayViewData['moves'] ?? collect(),
                    'surveys' => $this->dayViewData['surveys'] ?? collect(),
                    'moves_count' => $this->dayViewData['moves'] ? $this->dayViewData['moves']->count() : 0,
                    'surveys_count' => $this->dayViewData['surveys'] ? $this->dayViewData['surveys']->count() : 0,
                ])
                ->schema([
                    Grid::make(1)->schema([
                        // Moves section with business-critical information
                        TextEntry::make('moves')
                            ->label('Moves')
                            ->state(fn () => $this->dayViewData['moves']->isEmpty()
                                ? 'No moves scheduled for this date'
                                : $this->dayViewData['moves']->map(function ($move) {
                                    // Rich HTML formatting with integrated navigation
                                    return sprintf(
                                        '<a href="%s" class="block p-1 rounded text-primary-600 hover:underline hover:bg-gray-50">%s: %s → %s (£%s)</a>',
                                        OrderResource::getUrl('view', ['record' => $move->id]),
                                        e($move->customer?->full_name ?? 'N/A'),
                                        e($move->fromAddress?->full_address ?? 'N/A'),
                                        e($move->toAddress?->full_address ?? 'N/A'),
                                        number_format($move->total ?? 0, 2)
                                    );
                                })->join('<br>'))
                            ->html(), // Enable HTML for rich formatting

                        // Surveys section with task management integration
                        TextEntry::make('surveys')
                            ->label('Surveys')
                            ->state(fn () => $this->dayViewData['surveys']->isEmpty()
                                ? 'No surveys scheduled for this date'
                                : $this->dayViewData['surveys']->map(function ($survey) {
                                    return sprintf(
                                        '<a href="%s" class="block p-1 rounded text-primary-600 hover:underline hover:bg-gray-50">%s (%s) at %s</a>',
                                        TaskResource::getUrl('view', ['record' => $survey->id]),
                                        e($survey->client?->full_name ?? 'N/A'),
                                        e($survey->status ?? 'N/A'),
                                        e($survey->start_date?->format('g:i A') ?? 'N/A')
                                    );
                                })->join('<br>'))
                            ->html(),
                    ]),
                ])
            );
    }

    /**
     * Show detailed day information in modal
     * 
     * Loads comprehensive day data including moves and surveys,
     * then triggers the modal display. Demonstrates efficient
     * data loading with relationship eager loading.
     * 
     * @param string $date Date to display details for
     */
    public function showDayDetails(string $date): void
    {
        $dateCarbon = Carbon::parse($date);

        // Load moves with optimized relationships for performance
        $moves = Order::query()
            ->with(['customer', 'fromAddress', 'toAddress']) // Eager load for efficiency
            ->whereIn('status', $this->selectedStatuses)
            ->where(function (Builder $query) use ($dateCarbon) {
                // Complex date logic handling both single-day and multi-day moves
                $query->whereDate('move_date', $dateCarbon)
                    ->when(fn ($q) => $q->whereNull('end_date')
                        ->orWhere(function ($q) use ($dateCarbon) {
                            $q->whereDate('move_date', '<=', $dateCarbon)
                                ->whereDate('end_date', '>=', $dateCarbon);
                        }));
            })
            ->get();

        // Load surveys for comprehensive day view
        $surveys = Task::query()
            ->where('category', 'survey')
            ->whereDate('start_date', $dateCarbon)
            ->with(['order', 'client']) // Relationship optimization
            ->get();

        // Prepare modal data with formatted information
        $this->dayViewData = [
            'date' => $dateCarbon->format('l, jS F Y'), // Human-readable date
            'moves' => $moves,
            'surveys' => $surveys,
        ];

        $this->mountAction('viewDay'); // Trigger modal display
    }

    // =============================================================================
    // CALENDAR DATA PROCESSING - Core calendar rendering logic
    // =============================================================================

    /**
     * Load and process calendar events with advanced positioning
     * 
     * This method implements sophisticated calendar rendering including:
     * - Multi-day event spanning and positioning
     * - Conflict detection and visual separation
     * - Performance optimization through date-range queries
     * - Mixed event type handling (orders + surveys)
     * 
     * Key Algorithm: Event Positioning
     * - Tracks event positions across multiple days to prevent overlaps
     * - Maintains visual consistency for multi-day events
     * - Handles complex date range scenarios efficiently
     */
    protected function loadAndProcessEvents(): void
    {
        // Calculate optimal date range for calendar view
        $startOfMonth = $this->currentDate->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $endOfMonth = $this->currentDate->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        // Optimized query for moves within calendar range
        $moves = Order::query()
            ->with(['customer', 'fromAddress', 'toAddress'])
            ->whereIn('status', $this->selectedStatuses)
            ->where(function (Builder $query) use ($startOfMonth, $endOfMonth) {
                $query->where('move_date', '<=', $endOfMonth->format('Y-m-d'))
                    ->where('move_date', '>=', $startOfMonth->format('Y-m-d'));
            })
            ->get();

        // Load surveys for mixed event calendar
        $surveys = Task::query()
            ->where('category', 'survey')
            ->whereBetween('start_date', [
                $startOfMonth->format('Y-m-d'),
                $endOfMonth->format('Y-m-d'),
            ])
            ->with(['order', 'client'])
            ->get();

        $weeks = collect();
        $currentDate = $startOfMonth->copy();
        $eventPositions = collect(); // Critical: Track positions by event ID

        // Build calendar grid week by week
        while ($currentDate <= $endOfMonth) {
            $week = collect();

            // Process each day in the week
            for ($i = 0; $i < 7; $i++) {
                $currentDateStr = $currentDate->format('Y-m-d');

                // Filter moves for current day with complex date logic
                $dateMoves = $moves->filter(function ($move) use ($currentDateStr) {
                    $moveStart = Carbon::parse($move->move_date);
                    $moveStartStr = $moveStart->format('Y-m-d');

                    // Handle single-day moves
                    if (!$move->end_date) {
                        return $currentDateStr === $moveStartStr;
                    }

                    // Handle multi-day moves with date range logic
                    $moveEnd = Carbon::parse($move->end_date);
                    $moveEndStr = $moveEnd->format('Y-m-d');

                    return $currentDateStr >= $moveStartStr && $currentDateStr <= $moveEndStr;
                })->values();

                // Filter surveys for current day
                $dateSurveys = $surveys->filter(function ($survey) use ($currentDateStr) {
                    return $survey->start_date->format('Y-m-d') === $currentDateStr;
                })->values();

                $processedMoves = collect();
                $usedPositions = [];

                // ADVANCED ALGORITHM: Event positioning for visual clarity
                foreach ($dateMoves as $move) {
                    $moveStart = Carbon::parse($move->move_date);
                    $moveStartStr = $moveStart->format('Y-m-d');
                    $moveEnd = $move->end_date ? Carbon::parse($move->end_date) : $moveStart;
                    $moveEndStr = $moveEnd->format('Y-m-d');

                    if ($currentDateStr !== $moveStartStr) {
                        // Continuing event: use stored position for consistency
                        $position = $eventPositions->get($move->id);
                    } else {
                        // New event: find first available position
                        $position = 0;
                        while (in_array($position, $usedPositions)) {
                            $position++;
                        }
                        // Store position for multi-day consistency
                        $eventPositions->put($move->id, $position);
                    }

                    $usedPositions[] = $position;

                    // Build processed move data with positioning information
                    $processedMoves->push([
                        'id' => $move->id,
                        'order' => $move,
                        'isStart' => $currentDateStr === $moveStartStr,
                        'isEnd' => $currentDateStr === $moveEndStr,
                        'position' => $position,
                        'totalPositions' => max(count($usedPositions), $dateMoves->count()),
                        'color' => $this->getStatusColor($move->status),
                    ]);
                }

                // Build day data structure for calendar rendering
                $week->push([
                    'date' => $currentDate->copy(),
                    'isToday' => $currentDate->isToday(),
                    'isCurrentMonth' => $currentDate->month === $this->currentDate->month,
                    'moves' => $processedMoves,
                    'surveys' => $dateSurveys,
                ]);

                $currentDate->addDay();
            }

            $weeks->push($week);
        }

        $this->calendarWeeks = $weeks; // Store for template rendering
    }

    /**
     * Status-based color coding for visual workflow management
     * 
     * Provides immediate visual feedback about order status priorities.
     * Colors chosen for accessibility and business workflow understanding.
     * 
     * @param string $status Order status
     * @return string RGB color value
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            Order::STATUS_BOOKED => 'rgb(34, 197, 94)',          // green-500: confirmed
            Order::STATUS_PENDING_DEPOSIT => 'rgb(234, 179, 8)', // yellow-500: needs attention
            Order::STATUS_PENDING => 'rgb(59, 130, 246)',        // blue-500: in progress
            Order::STATUS_DRAFT => 'rgb(156, 163, 175)',         // gray-400: preliminary
            Order::STATUS_COMPLETED => 'rgb(147, 51, 234)',      // purple-500: finished
            Order::STATUS_CANCELLED => 'rgb(239, 68, 68)',       // red-500: cancelled
            default => 'rgb(107, 114, 128)',                     // gray-500: unknown
        };
    }

    // =============================================================================
    // NAVIGATION CONTROLS - Calendar interaction methods
    // =============================================================================

    /**
     * Navigate to next month with automatic data refresh
     */
    public function nextMonth(): void
    {
        $this->currentDate = $this->currentDate->addMonth()->startOfMonth();
        $this->loadAndProcessEvents();
    }

    /**
     * Navigate to previous month with automatic data refresh
     */
    public function previousMonth(): void
    {
        $this->currentDate = $this->currentDate->subMonth()->startOfMonth();
        $this->loadAndProcessEvents();
    }

    /**
     * Return to today with automatic data refresh
     * Provides quick navigation back to current operations
     */
    public function today(): void
    {
        $this->currentDate = Carbon::today();
        $this->loadAndProcessEvents();
    }
}