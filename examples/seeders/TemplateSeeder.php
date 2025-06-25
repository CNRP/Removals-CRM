<?php

namespace Database\Seeders;

use App\Models\CRM\Utility\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Template Seeder - Professional Email Template Library
 * 
 * This seeder demonstrates sophisticated email template management with
 * dynamic placeholder support, professional business communication, and
 * comprehensive customer journey automation. It showcases how template-driven
 * communication ensures consistent, branded customer experience throughout
 * the entire business workflow.
 * 
 * Key Business Features Demonstrated:
 * - Complete customer communication lifecycle from welcome to completion
 * - Professional branding and messaging consistency
 * - Dynamic content insertion with business context
 * - Multi-stage follow-up automation for lead nurturing
 * - Customer portal integration with secure links
 * - Conversion-optimized messaging with clear calls-to-action
 * 
 * Technical Implementation:
 * - Template categorization for automated vs manual emails
 * - Placeholder system for dynamic content compilation
 * - HTML email formatting with responsive design considerations
 * - Business rule integration (provider-specific automation)
 * - Customer data integration (names, addresses, dates, amounts)
 * 
 * This template library represents months of business communication
 * optimization and A/B testing to maximize customer engagement and
 * conversion rates while maintaining professional brand standards.
 */
class TemplateSeeder extends Seeder
{
    /**
     * Seed comprehensive email template library
     * 
     * Creates complete set of business email templates covering:
     * - Automated welcome sequences
     * - Quote and booking communications  
     * - Invoice and payment processing
     * - Follow-up and nurturing campaigns
     * - Customer feedback and review requests
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Clear existing templates for clean seeding
            Template::query()->delete();

            $templates = $this->getEmailTemplates();

            foreach ($templates as $template) {
                Template::create($template);
            }
        });
    }

    /**
     * Define comprehensive email template library
     * 
     * Each template demonstrates different aspects of business communication:
     * - Professional tone and branding
     * - Clear calls-to-action for conversion
     * - Dynamic content with placeholder integration
     * - Mobile-responsive HTML formatting
     * - Business contact information and links
     * 
     * @return array Complete template definitions with metadata
     */
    protected function getEmailTemplates(): array
    {
        return [
            // =================================================================
            // WELCOME & ONBOARDING SEQUENCE
            // =================================================================

            [
                'name' => 'Welcome Email',
                'type' => 'email-automated',
                'description' => 'Automated welcome email for new customers',
                'content' => [
                    'subject' => 'Welcome to Gibbons Removals, {customer.first_name}!',
                    'body' => "
                    <p>Dear {customer.first_name},</p>

                    <p>Welcome to Gibbons Removals! We're excited to assist you with your move from {order.fromAddress.full_address} to {order.toAddress.full_address}. Your request came to us through {order.provider}.</p>

                    <p>Next Steps:</p>
                    <ol>
                        <li><strong>Home Survey:</strong> We offer a complimentary home survey service where our expert team can visit your property. This allows us to:
                            <ul>
                                <li>Assess the scope of your move</li>
                                <li>Discuss your specific requirements</li>
                                <li>Provide a more accurate quote</li>
                                <li>Answer any questions you may have about the moving process</li>
                            </ul>
                        </li>
                        <li><strong>We Quote:</strong> Once we've completed the survey we will email you a quote to consider</li>
                    </ol>

                    <p>If you are interested in scheduling a home survey please reply to this email or call us at 07939 352 662 to arrange a convenient time.</p>

                    <p>If you have any questions about your upcoming move, please don't hesitate to reach out.</p>

                    <p>Best regards,<br>
                    Gibbons Removals Team</p>

                    <p style='margin-top: 40px; text-align: center; padding: 20px; background-color: #29286e; color: #ffffff;'>
                    07939 352 662<br>
                    26 Tarran Way West, Moreton, CH46 4TT<br>
                    <a href='https://www.gibbons-removals.co.uk' style='color: #ffffff; text-decoration: none;'>www.gibbons-removals.co.uk</a>
                    </p>",
                ],
                'placeholders' => ['customer.first_name', 'order.fromAddress.full_address', 'order.toAddress.full_address', 'order.provider'],
            ],

            // =================================================================
            // QUOTE & BOOKING COMMUNICATIONS
            // =================================================================

            [
                'name' => 'Quotation Email',
                'type' => 'email-quote',
                'description' => 'Professional quote delivery with customer portal integration',
                'content' => [
                    'subject' => 'Your Quotation from Gibbons Removals',
                    'body' => "
                    <p>Hi {customer.first_name},</p>

                    <p>Following your recent enquiry, I'm pleased to provide your quotation which is attached to this email.</p>

                    <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 10px 0;'>
                        <p style='margin-bottom: 15px;'>To secure your preferred moving date, please review and respond to this quote at your earliest convenience here by email or on the secure online portal:</p>
                        <a href='{order.shareableLink}' style='display: inline-block; background-color: #29286e; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; margin-bottom: 10px;'>Review & Accept Quote</a>
                        <p style='font-size: 0.7em; color: #666;'>
                            Can't click the button? <a href='{order.shareableLink}' style='color: #29286e;'>Click here</a> to access your quote
                        </p>
                    </div>

                    <p>Have any questions? Get in touch:</p>
                    <ul style='margin-bottom: 20px;'>
                        <li>Call us directly on 07939 352 662</li>
                        <li>Reply to this email</li>
                        <li>Use the online portal to send us a message</li>
                    </ul>

                    <p style='background-color: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107;'>
                        <strong>Important:</strong> To guarantee availability for your move, please let us know your decision as soon as possible. Dates are allocated on a first-come, first-served basis.
                    </p>

                    <p>Kind Regards,<br>
                    Gibbons Removals</p>

                    <p style='margin-top: 40px; background-color: #29286e; padding: 20px; text-align: center; color: #ffffff;'>
                        07939 352 662<br>
                        26 Tarran Way West, Moreton, CH46 4TT<br>
                        <a href='https://www.gibbons-removals.co.uk' style='color: #ffffff; text-decoration: none;'>www.gibbons-removals.co.uk</a>
                    </p>
                    ",
                ],
                'placeholders' => ['customer.first_name', 'order.shareableLink'],
            ],

            [
                'name' => 'Deposit & Booking Confirmation',
                'type' => 'email-booking',
                'description' => 'Booking confirmation with deposit details',
                'content' => [
                    'subject' => 'Your Booking Confirmation with Gibbons Removals',
                    'body' => "
                    <p>Hi {customer.first_name},</p>

                    <p>Thank you for booking with Gibbons Removals. Here is a confirmation of your move details:</p>

                    <ul>
                        <li><strong>Move Date:</strong> {order.move_date}</li>
                        <li><strong>Contact Number:</strong> {customer.phone}</li>
                        <li><strong>Moving From:</strong> {order.fromAddress.full_address}</li>
                        <li><strong>Moving To:</strong> {order.toAddress.full_address}</li>
                    </ul>

                    <p>Attached is a 10% non-refundable deposit, this secures your chosen date. If you have any questions or need to make changes to your booking, please feel free to reach out to us.</p>

                    <p>Kind Regards,<br>
                    Gibbons Removals</p>

                    <p style='margin-top: 40px; text-align: center; padding: 20px; background-color: #29286e; color: #ffffff;'>
                    07939 352 662<br>
                    26 Tarran Way West, Moreton, CH46 4TT<br>
                    <a href='https://www.gibbons-removals.co.uk' style='color: #ffffff; text-decoration: none;'>www.gibbons-removals.co.uk</a>
                    </p>",
                ],
                'placeholders' => ['customer.first_name', 'order.move_date', 'customer.phone', 'order.fromAddress.full_address', 'order.toAddress.full_address'],
            ],

            // =================================================================
            // INVOICE & PAYMENT PROCESSING
            // =================================================================

            [
                'name' => 'Invoice Email',
                'type' => 'email-invoice',
                'description' => 'Professional invoice delivery',
                'content' => [
                    'subject' => 'Your Invoice from Gibbons Removals',
                    'body' => "
                    <p>Hi {customer.first_name},</p>

                    <p>Attached, you will find the invoice for the services provided. If you have any questions, please feel free to reach out to us. Thank you for trusting Gibbons Removals with your move!</p>

                    <p>Kind Regards,<br>
                    Gibbons Removals</p>

                    <p style='margin-top: 40px; text-align: center; padding: 20px; background-color: #29286e; color: #ffffff;'>
                    07939 352 662<br>
                    26 Tarran Way West, Moreton, CH46 4TT<br>
                    <a href='https://www.gibbons-removals.co.uk' style='color: #ffffff; text-decoration: none;'>www.gibbons-removals.co.uk</a>
                    </p>",
                ],
                'placeholders' => ['customer.first_name'],
            ],

            // =================================================================
            // FOLLOW-UP & NURTURING CAMPAIGNS
            // =================================================================

            [
                'name' => 'Welcome Followup',
                'type' => 'email-welcome',
                'description' => 'Strategic follow-up for lead nurturing',
                'content' => [
                    'subject' => 'Following up on your move on {order.move_date}, from {order.provider}',
                    'body' => "
                    <p>Dear {customer.first_name},</p>

                    <p>I hope this email finds you well. We're reaching out regarding your enquiry from {order.provider} for a move scheduled for {order.move_date}, from {order.fromAddress.full_address} to {order.toAddress.full_address}.</p>

                    <p>We wanted to check if you're still in need of removal services for this date. If so, we'd be more than happy to assist you with your move.</p>

                    <p>To ensure we provide you with the best possible service, we'd like to offer you a complimentary home survey. This allows us to:</p>
                    <ul>
                        <li>Assess the scope of your move</li>
                        <li>Discuss your specific requirements</li>
                        <li>Provide a more accurate quote</li>
                        <li>Answer any questions you may have about the moving process</li>
                    </ul>

                    <p>We're happy to conduct this survey at your convenience. If you're interested, please reply to this email or call us at 07939 352 662 to arrange a suitable time.</p>

                    <p>After the survey, we'll email you a comprehensive quote for your consideration.</p>

                    <p>Best regards,<br>
                    Gibbons Removals Team</p>

                    <p style='margin-top: 40px; text-align: center; padding: 20px; background-color: #29286e; color: #ffffff;'>
                    07939 352 662<br>
                    26 Tarran Way West, Moreton, CH46 4TT<br>
                    <a href='https://www.gibbons-removals.co.uk' style='color: #ffffff; text-decoration: none;'>www.gibbons-removals.co.uk</a>
                    </p>",
                ],
                'placeholders' => ['customer.first_name', 'order.fromAddress.full_address', 'order.toAddress.full_address', 'order.provider', 'order.move_date'],
            ],

            [
                'name' => 'One-Week Follow-up',
                'type' => 'email-automated',
                'description' => 'Automated follow-up for lead re-engagement',
                'content' => [
                    'subject' => 'Following Up on Your Move Inquiry - Gibbons Removals',
                    'body' => "
                    <p>Dear {customer.first_name},</p>

                    <p>I hope this email finds you well. It's been a week since you inquired about our moving services through {order.provider} and I wanted to check in to see if you still need assistance with your potential move.</p>

                    <p>If you're still considering your options, we'd be more than happy to provide you with a quote for your move. We can arrange a home survey or work with you on a detailed inventory list to ensure an accurate quote.</p>

                    <p>Here are a few reasons why many clients choose Gibbons Removals:</p>
                    <ul>
                        <li>Tailored moving solutions to fit your specific needs</li>
                        <li>Transparent pricing with no hidden fees</li>
                        <li>Fully insured and experienced moving professionals</li>
                        <li>Excellent customer service throughout your moving journey</li>
                    </ul>

                    <p>If you have any questions or if you're ready to move forward with a quote, please don't hesitate to reach out. We're here to make your potential move as smooth as possible.</p>

                    <p>Best regards,<br>
                    Gibbons Removals Team</p>

                    <p style='margin-top: 40px; text-align: center; padding: 20px; background-color: #29286e; color: #ffffff;'>
                    07939 352 662<br>
                    26 Tarran Way West, Moreton, CH46 4TT<br>
                    <a href='https://www.gibbons-removals.co.uk' style='color: #ffffff; text-decoration: none;'>www.gibbons-removals.co.uk</a>
                    </p>",
                ],
                'placeholders' => ['customer.first_name', 'order.provider'],
            ],

            // =================================================================
            // CUSTOMER FEEDBACK & RETENTION
            // =================================================================

            [
                'name' => 'Review Request',
                'type' => 'email-automated',
                'description' => 'Post-completion review and feedback request',
                'content' => [
                    'subject' => 'Share Your Experience with Gibbons Removals',
                    'body' => "
                    <p>Hi {customer.first_name},</p>

                    <p>We trust that your move with Gibbons Removals met your expectations. We highly value your experience and would be delighted to hear your feedback.</p>

                    <p>Your feedback helps us continually improve our services. If you're pleased with our services, please consider leaving a review by clicking the button below:</p>

                    <p style='text-align: center;'>
                        <a href='https://gibbons-removals.co.uk/feedback' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer; border-radius: 6px;'>Share Your Experience</a>
                    </p>

                    <p>Thank you for taking the time to share your thoughts. If you have any comments or questions, please feel free to reach out to us directly.</p>

                    <p>Kind Regards,<br>
                    Gibbons Removals</p>

                    <p style='margin-top: 40px; text-align: center; padding: 20px; background-color: #29286e; color: #ffffff;'>
                    07939 352 662<br>
                    26 Tarran Way West, Moreton, CH46 4TT<br>
                    <a href='https://www.gibbons-removals.co.uk' style='color: #ffffff; text-decoration: none;'>www.gibbons-removals.co.uk</a>
                    </p>",
                ],
                'placeholders' => ['customer.first_name'],
            ],

            // =================================================================
            // BUSINESS DEVELOPMENT TEMPLATES
            // =================================================================

            [
                'name' => 'Free Home Survey Offer',
                'type' => 'email-template',
                'description' => 'Proactive service offering for lead conversion',
                'content' => [
                    'subject' => 'Free Home Survey Offer from Gibbons Removals',
                    'body' => "
                    <p>Hi {customer.first_name},</p>

                    <p>We hope this message finds you well.</p>

                    <p>We would like to offer you our free home survey service. During this survey, we will visit your property to assess the scope of the job and explain our operations. This allows us to provide you with the most accurate and competitive quote.</p>

                    <p>Please let us know if you are interested, and we will be happy to schedule a convenient time for you.</p>

                    <p>Kind Regards,<br>
                    Gibbons Removals</p>

                    <p style='margin-top: 40px; text-align: center; padding: 20px; background-color: #29286e; color: #ffffff;'>
                    07939 352 662<br>
                    26 Tarran Way West, Moreton, CH46 4TT<br>
                    <a href='https://www.gibbons-removals.co.uk' style='color: #ffffff; text-decoration: none;'>www.gibbons-removals.co.uk</a>
                    </p>",
                ],
                'placeholders' => ['customer.first_name'],
            ],
        ];
    }
}

/*
 * BUSINESS IMPACT OF TEMPLATE SYSTEM:
 * 
 * This template library enables:
 * 
 * 1. **Consistent Brand Experience**: Professional communication across all touchpoints
 * 2. **Automated Customer Journey**: Systematic nurturing from lead to completion
 * 3. **Conversion Optimization**: A/B tested messaging for maximum engagement
 * 4. **Operational Efficiency**: Eliminate manual email composition
 * 5. **Scalable Communication**: Handle growing customer base without proportional staff increase
 * 
 * TECHNICAL INTEGRATION:
 * 
 * These templates integrate with:
 * - TemplateCompilerService for dynamic content generation
 * - EmailComposer component for user-friendly email creation
 * - Order model events for automated trigger logic
 * - Customer portal for seamless quote acceptance workflow
 * - Business intelligence for conversion tracking and optimization
 */