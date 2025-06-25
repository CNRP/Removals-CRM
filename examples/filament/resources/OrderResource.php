<?php

namespace App\Filament\CRM\Resources;

use App\Filament\CRM\Resources\OrderResource\Pages;
use App\Models\CRM\Order;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Order Resource - Advanced Business Management Interface
 * 
 * This Filament resource demonstrates sophisticated business management
 * capabilities including complex filtering, intelligent search, bulk operations,
 * and integrated workflow management. It showcases enterprise-level admin
 * interface development with comprehensive business logic integration.
 * 
 * Key Technical Achievements:
 * - Advanced searchable columns with relationship traversal
 * - Status-based visual management with dynamic badge coloring  
 * - Complex filtering system with date ranges and multi-select options
 * - Intelligent bulk operations with custom detail formatting
 * - Permission-based interface with role-specific functionality
 * - Performance optimization through query optimization and eager loading
 * - Status workflow management with grouped business-logical transitions
 * 
 * Business Features:
 * - Complete order lifecycle management from draft to completion
 * - Visual status indicators for immediate priority identification
 * - Bulk detail copying for external communication (calls, emails)
 * - Advanced search across customer names, addresses, and order details
 * - Date-range filtering for operational planning and reporting
 * - Revenue visibility with financial totals and currency formatting
 * 
 * Advanced Patterns Demonstrated:
 * - Custom query builders for complex search logic
 * - Dynamic state formatting with business rule implementation
 * - JavaScript integration for clipboard operations
 * - Modal forms with grouped status workflows
 * - Comprehensive permission integration using Filament Shield
 */
class OrderResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-square-2-stack';
    protected static ?string $navigationGroup = 'Removals CRM';

    // =============================================================================
    // PERMISSION SYSTEM - Role-based access control
    // =============================================================================

    /**
     * Define granular permissions for order management
     * 
     * Implements business-specific permission structure allowing
     * fine-grained control over order management capabilities.
     */
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',                    // Basic order viewing
            'edit',                    // Order modification
            'manage_order_items',      // Line item management
            'manage_status',           // Status workflow control
        ];
    }

    /**
     * Control navigation visibility based on user permissions
     * Ensures clean UI by hiding inaccessible resources
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('view_order');
    }

    // =============================================================================
    // TABLE CONFIGURATION - Advanced data presentation and interaction
    // =============================================================================

    /**
     * Configure comprehensive order management table
     * 
     * Implements sophisticated table with:
     * - Complex searchable relationships
     * - Dynamic status visualization
     * - Financial data formatting
     * - Interactive filtering and sorting
     * - Bulk operations for efficiency
     * - Integrated workflow management
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Order identification with friendly IDs
                Tables\Columns\TextColumn::make('friendly_id')
                    ->label('Order ID')
                    ->icon('heroicon-o-hashtag')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Hidden by default for space

                // Customer information with advanced search
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->icon('heroicon-o-user')
                    ->getStateUsing(fn (Order $record): string => 
                        "{$record->customer->first_name} {$record->customer->last_name}"
                    )
                    // ADVANCED FEATURE: Search across relationship fields
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    // Custom sorting through relationships
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('customer_id', $direction);
                    })
                    ->limit(30) // Prevent overflow in narrow columns
                    ->toggleable(),

                // Status visualization with business-logic color coding
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        // Color coding reflects business workflow priorities
                        'gray' => fn ($state): bool => $state === Order::STATUS_DRAFT,
                        'warning' => fn ($state): bool => in_array($state, [
                            Order::STATUS_PENDING,
                            Order::STATUS_PENDING_DATE,
                            Order::STATUS_PENDING_DEPOSIT,
                            Order::STATUS_ATTEMPTED_CONTACT,
                            Order::STATUS_AWAITING_RESPONSE,
                        ]),
                        'success' => fn ($state): bool => in_array($state, [
                            Order::STATUS_COMPLETED,
                            Order::STATUS_BOOKED,
                            Order::STATUS_CONTACTED,
                        ]),
                        'danger' => fn ($state): bool => $state === Order::STATUS_CANCELLED,
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)) // Human-readable formatting
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                // Financial information with proper currency formatting
                Tables\Columns\TextColumn::make('total')
                    ->money('gbp') // Automatic GBP formatting
                    ->sortable()
                    ->toggleable(),

                // Date columns with business-relevant icons
                Tables\Columns\TextColumn::make('order_date')
                    ->icon('heroicon-o-calendar')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('move_date')
                    ->icon('heroicon-o-truck')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                // Contact information for operational use
                Tables\Columns\TextColumn::make('customer.phone')
                    ->label('Phone')
                    ->icon('heroicon-o-phone')
                    ->toggleable(isToggledHiddenByDefault: true), // Available but hidden by default

                // Address information with complex relationship display
                Tables\Columns\TextColumn::make('from_address')
                    ->label('From')
                    ->icon('heroicon-o-map-pin')
                    ->getStateUsing(function (Order $record): string {
                        // Complex address formatting with null safety
                        return $record->fromAddress ? implode(', ', array_filter([
                            $record->fromAddress->address_line_1,
                            $record->fromAddress->address_line_2,
                            $record->fromAddress->address_line_3,
                            $record->fromAddress->postcode,
                        ])) : 'N/A';
                    })
                    // ADVANCED FEATURE: Search across nested relationship fields
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('fromAddress', function (Builder $query) use ($search) {
                            $query->where('address_line_1', 'like', "%{$search}%")
                                ->orWhere('address_line_2', 'like', "%{$search}%")
                                ->orWhere('address_line_3', 'like', "%{$search}%")
                                ->orWhere('postcode', 'like', "%{$search}%");
                        });
                    })
                    ->wrap() // Allow text wrapping for long addresses
                    ->limit(50)
                    ->toggleable(),
            ])

            // =============================================================================
            // ADVANCED FILTERING SYSTEM - Complex data filtering
            // =============================================================================

            ->filters([
                // Multi-select status filter with business grouping
                SelectFilter::make('status')
                    ->multiple() // Allow multiple status selection
                    ->options([
                        // Options reflect complete business workflow
                        Order::STATUS_DRAFT => 'Draft',
                        Order::STATUS_ATTEMPTED_CONTACT => 'Attempted Contact',
                        Order::STATUS_CONTACTED => 'Contacted',
                        Order::STATUS_PENDING => 'Pending',
                        Order::STATUS_AWAITING_RESPONSE => 'Awaiting Response',
                        Order::STATUS_PENDING_DEPOSIT => 'Pending Deposit',
                        Order::STATUS_PENDING_DATE => 'Pending Date',
                        Order::STATUS_BOOKED => 'Booked',
                        Order::STATUS_COMPLETED => 'Completed',
                        Order::STATUS_CANCELLED => 'Cancelled',
                    ])
                    ->label('Status')
                    ->placeholder('Select Statuses'),

                // Date range filters for operational planning
                Filter::make('order_date')
                    ->form([
                        Forms\Components\DatePicker::make('order_date_from'),
                        Forms\Components\DatePicker::make('order_date_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['order_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('order_date', '>=', $date),
                            )
                            ->when(
                                $data['order_date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('order_date', '<=', $date),
                            );
                    }),

                Filter::make('move_date')
                    ->form([
                        Forms\Components\DatePicker::make('move_date_from'),
                        Forms\Components\DatePicker::make('move_date_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['move_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('move_date', '>=', $date),
                            )
                            ->when(
                                $data['move_date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('move_date', '<=', $date),
                            );
                    }),
            ])

            // =============================================================================
            // ROW ACTIONS - Individual record management
            // =============================================================================

            ->actions([
                // Status management with business workflow logic
                Tables\Actions\Action::make('change_status')
                    ->label('Change Status')
                    ->icon('heroicon-o-arrow-path')
                    ->modalHeading('Update Order Status')
                    ->modalDescription('Select the new status for this order.')
                    ->visible(fn (): bool => auth()->user()->can('manage_status_order'))
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->required()
                            // BUSINESS LOGIC: Grouped status options reflecting workflow stages
                            ->options([
                                'Initial' => [
                                    Order::STATUS_DRAFT => 'Draft',
                                    Order::STATUS_ATTEMPTED_CONTACT => 'Attempted Contact',
                                    Order::STATUS_CONTACTED => 'Contacted',
                                ],
                                'Quoting' => [
                                    Order::STATUS_PENDING => 'Pending',
                                    Order::STATUS_AWAITING_RESPONSE => 'Awaiting Response',
                                ],
                                'Booking Process' => [
                                    Order::STATUS_PENDING_DEPOSIT => 'Pending Deposit',
                                    Order::STATUS_PENDING_DATE => 'Pending Date',
                                    Order::STATUS_BOOKED => 'Booked',
                                ],
                                'Final Statuses' => [
                                    Order::STATUS_COMPLETED => 'Completed',
                                    Order::STATUS_CANCELLED => 'Cancelled',
                                ],
                            ])
                            ->default(fn (Order $record) => $record->status)
                            ->selectablePlaceholder(false)
                            ->searchable()
                            ->columnSpanFull()
                            ->native(false),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $record->update(['status' => $data['status']]);
                    }),

                // Standard CRUD actions with permission checking
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (): bool => auth()->user()->can('edit_order')),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('edit_order')),
                ])
                    ->visible(fn (): bool => auth()->user()->can('view_order')),
            ])

            // =============================================================================
            // BULK OPERATIONS - Efficient mass data management
            // =============================================================================

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Standard bulk delete with permission checking
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('edit_order')),

                    // ADVANCED FEATURE: Intelligent detail copying for external communication
                    Tables\Actions\BulkAction::make('copy_details')
                        ->label('Copy Details')
                        ->icon('heroicon-o-clipboard')
                        ->form([
                            Forms\Components\CheckboxList::make('fields')
                                ->label('Select fields to copy')
                                ->options([
                                    'customer_name' => 'Customer Name',
                                    'phone' => 'Phone Number',
                                    'from_address' => 'From Address',
                                    'order_date' => 'Order Date',
                                    'move_date' => 'Move Date',
                                    'total' => 'Total Amount',
                                    'status' => 'Status',
                                ])
                                ->required()
                                ->columns(2),
                        ])
                        ->action(function (Collection $records, array $data, $livewire): void {
                            // SOPHISTICATED BULK PROCESSING: Custom detail formatting
                            $output = $records->map(function (Order $order) use ($data) {
                                $details = [];
                                
                                // Process each selected field with business-appropriate formatting
                                foreach ($data['fields'] as $field) {
                                    switch ($field) {
                                        case 'customer_name':
                                            $details['name'] = "{$order->customer->first_name} {$order->customer->last_name}";
                                            break;
                                        case 'phone':
                                            $details['phone'] = $order->customer->phone;
                                            break;
                                        case 'move_date':
                                            $details['moving'] = $order->move_date 
                                                ? 'Moving: ' . $order->move_date->format('D jS M Y') 
                                                : 'N/A';
                                            break;
                                        case 'from_address':
                                            $details['address'] = $order->fromAddress ? implode(', ', array_filter([
                                                $order->fromAddress->address_line_1,
                                                $order->fromAddress->address_line_2,
                                                $order->fromAddress->address_line_3,
                                                $order->fromAddress->postcode,
                                            ])) : 'N/A';
                                            break;
                                        case 'order_date':
                                            $details['ordered'] = 'Ordered: ' . $order->order_date->format('d/m/Y');
                                            break;
                                        case 'total':
                                            $details['total'] = money($order->total, 'gbp');
                                            break;
                                        case 'status':
                                            $details['status'] = ucfirst($order->status);
                                            break;
                                    }
                                }
                                
                                // BUSINESS LOGIC: Custom ordering for readability
                                $orderedDetails = array_values(array_filter([
                                    $details['name'] ?? null,
                                    $details['phone'] ?? null,
                                    $details['moving'] ?? null,
                                    $details['address'] ?? null,
                                    $details['ordered'] ?? null,
                                    $details['total'] ?? null,
                                    $details['status'] ?? null,
                                ]));
                                
                                return implode(' - ', $orderedDetails);
                            })->join("\n");

                            // ADVANCED FEATURE: Direct clipboard integration with proper escaping
                            $escapedOutput = str_replace(
                                ["\n", "\r", '"'],
                                ['\\n', '', '\\"'],
                                $output
                            );

                            // JavaScript integration for clipboard functionality
                            $livewire->js(<<<JS
                                window.navigator.clipboard.writeText("{$escapedOutput}");
                                \$tooltip("Copied to clipboard", { timeout: 1500 });
                            JS);

                            // User feedback notification
                            Notification::make()
                                ->title('Details copied to clipboard')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->modalHeading('Copy Order Details')
                        ->modalDescription('Select which details you want to copy from the selected orders.'),
                ]),
            ])
            ->defaultSort('order_date', 'desc'); // Most recent orders first for operational relevance
    }

    // =============================================================================
    // RESOURCE PAGES - Navigation structure
    // =============================================================================

    /**
     * Define resource page structure for complete order management
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}