# 🚛 Removals CRM - Business Automation Platform

> Complete lead-to-cash automation system built with Laravel & Filament that transformed manual sales processes into streamlined digital workflows.

![Laravel](https://img.shields.io/badge/Laravel-10.x-red?logo=laravel)
![Filament](https://img.shields.io/badge/Filament-3.x-yellow?logo=filament)
![PHP](https://img.shields.io/badge/PHP-8.2+-blue?logo=php)

## 🎯 Business Impact

- **⚡ 95% reduction** in quote generation time (30+ minutes → 5 minutes)
- **🎯 Zero data entry errors** through complete automation
- **💰 Faster cash flow** with automated invoice delivery
- **📈 Improved lead conversion** through immediate response times

## 🚀 What I Built

A complete business automation platform that handles the entire customer journey from webhook lead capture through invoice generation and payment collection. This system runs a real removals business, processing thousands of leads monthly.

### Core Features

**🔗 Multi-Provider Webhook Integration**

- Secure signature validation for CompareMymove, ReallyMoving, PinLocal
- Solved complex payload encoding issues causing production failures
- Comprehensive error handling with failsafe storage system

**⚡ Automated Business Workflows**

- Lead → Customer → Order → Invoice pipeline
- Status-driven Google Calendar integration
- Template-based email automation with dynamic content
- Document generation (quotes, invoices, deposits, receipts)

**💼 Filament Admin Interface**

- Real-time dashboard with business metrics
- Advanced customer lifecycle management
- Integrated communication history and follow-up tracking
- Bulk operations for efficient data management

**📧 Intelligent Email System**

- Gmail API integration with SMTP fallback
- Dynamic template compilation with placeholder system
- Email threading and reply handling
- Professional document attachment automation

## 🛠️ Technical Highlights

### **Multi-Provider Webhook Security**

Implemented sophisticated signature validation supporting different providers with varying authentication methods. Solved critical production issue where CompareMymove's inconsistent JSON encoding was causing legitimate leads to be rejected.

**Key Achievement:** Debugged and resolved double-escaping issue that was causing 15% lead loss.

### **Service Facade Architecture**

Built comprehensive Google Services integration using Facade pattern, providing unified interface to Gmail, Calendar, and authentication APIs with intelligent fallback mechanisms.

### **Business Process Automation**

Designed event-driven architecture where order status changes automatically trigger:

- Google Calendar event creation/updates with rich details
- Email automation based on customer lifecycle stage
- Document generation with type-specific business rules
- Customer follow-up scheduling

### **Document Generation**

Created sophisticated PDF generation system supporting multiple document types (quotes, invoices, deposits, receipts) with dynamic content, professional formatting, and business rule implementation.

### **Modern Admin Interface with Filament**

Built comprehensive business management interface featuring:

- **Interactive calendar widget** with multi-day event visualization and status-based color coding
- **Advanced order management** with complex filtering, bulk operations, and status workflow
- **Integrated email composer** with template selection, live preview, and automatic PDF attachment
- **PDF generator with live preview** showing real-time document formatting before generation

## 📁 Code Examples

### [**Models**](examples/models/) - Business Entity Management

- [`Order.php`](examples/models/Order.php) - Core business entity with automated lifecycle management
- [`Customer.php`](examples/models/Customer.php) - CRM customer entity with smart relationship handling

### [**Services**](examples/services/) - Business Logic Layer

- [`GoogleServicesFacade.php`](examples/services/GoogleServicesFacade.php) - Facade pattern for API integration
- [`PDFGenerator.php`](examples/services/PDFGenerator.php) - Document automation with business rules
- [`TemplateCompilerService.php`](examples/services/TemplateCompilerService.php) - Dynamic email content generation

### [**Commands & Jobs**](examples/commands/) - Automated Processing

- [`FetchEmails.php`](examples/commands/FetchEmails.php) - Scheduled Gmail synchronization with error handling
- [`ProcessWebhook.php`](examples/jobs/ProcessWebhook.php) - Asynchronous webhook processing pipeline

### [**Data Seeding**](examples/seeders/) - Business Template Management

- [`TemplateSeeder.php`](examples/seeders/TemplateSeeder.php) - Professional email template library with dynamic placeholders

### [**Webhook Integration**](examples/webhook/) - External System Integration

- [`SignatureValidator.php`](examples/webhook/SignatureValidator.php) - Multi-provider webhook security

### [**Filament Admin Interface**](examples/filament/) - Modern Business Management

- [`README.md`](examples/filament/README.md) - Comprehensive documentation of Filament components and patterns
- [`OrderResource.php`](examples/filament/resources/OrderResource.php) - Advanced order management with filtering & bulk operations
- [`BookingCalendarWidget.php`](examples/filament/widgets/BookingCalendarWidget.php) - Interactive calendar with multi-day event handling
- [`EmailComposer.php`](examples/filament/components/EmailComposer.php) - Template-based email composition with PDF automation
- [`PdfComposer.php`](examples/filament/components/PdfComposer.php) - Document generation with live preview

## 🖥️ System Screenshots

### Dashboard Overview

![Dashboard View](https://api.quickdigital.co.uk/storage/blog-content/8c26440a-c1c6-4b2a-bc64-7cdca3b9053d.jpg)
_Real-time business dashboard with key metrics and workflow status_

### Lead Management

![Customer Leads Table](https://api.quickdigital.co.uk/storage/blog-content/718f6ce0-e05c-4881-a459-a0088bb518e5.png)
_Advanced lead table with filtering, search, and bulk operations_

### Order Management

![Order Details View](https://api.quickdigital.co.uk/storage/blog-content/1663efa5-96ae-431d-b288-1b3ff311b205.png)
_Comprehensive order view with customer details and workflow management_

### Document Generation

![Invoice Generator with Preview](https://api.quickdigital.co.uk/storage/blog-content/26891ed0-4df1-4588-a2d1-9655a9c48ccd.jpg)
_Live PDF preview system showing real-time document generation_

## 🏗️ Technical Stack

- **Backend:** Laravel 10.x with modern PHP 8.2+ features
- **Admin Interface:** Filament 3.x for powerful business management
- **Database:** MySQL with optimized indexing and relationship design
- **Integrations:** Google APIs (Gmail, Calendar), webhook providers, PDF generation
- **Infrastructure:** Queue processing, caching, comprehensive logging

## 📞 Business Context

Built for a UK removals company to automate their entire sales process. The system handles real business operations including:

- Lead processing from multiple sources
- Customer relationship management
- Quote and invoice generation
- Payment tracking and follow-up
- Job scheduling and calendar management

---

> **Note:** This repository contains sanitized code examples and documentation from a production business system. Sensitive business logic, API keys, and customer data have been removed while preserving technical architecture and implementation patterns.
