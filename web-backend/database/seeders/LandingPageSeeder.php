<?php

namespace Database\Seeders;

use App\Models\LandingPageSection;
use App\Models\LandingPageItem;
use App\Models\LandingPageSetting;
use Illuminate\Database\Seeder;

class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        // ===== SETTINGS =====

        // Branding
        LandingPageSetting::setValue('site_name', 'LEGALEASE', 'text', 'branding', 'Site Name');
        LandingPageSetting::setValue('logo_url', '/logo.jpg', 'text', 'branding', 'Logo URL');
        LandingPageSetting::setValue('logo_alt', 'LegalEase Logo', 'text', 'branding', 'Logo Alt Text');
        LandingPageSetting::setValue('copyright_text', '© 2024 LegalEase System. All rights reserved. | Privacy | Terms', 'text', 'branding', 'Copyright Text');

        // Footer
        LandingPageSetting::setValue('footer_address', '233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro.', 'text', 'footer', 'Office Address');
        LandingPageSetting::setValue('footer_contact_text', 'Have questions? Our team is here to help.', 'text', 'footer', 'Contact Section Text');
        LandingPageSetting::setValue('footer_services_links', json_encode(['Services', 'Notarization', 'Verification', 'Certification', 'Signing']), 'json', 'footer', 'Services Links');
        LandingPageSetting::setValue('footer_support_links', json_encode(['Help Center', 'Contact Us', 'FAQ']), 'json', 'footer', 'Support Links');

        // SEO
        LandingPageSetting::setValue('meta_title', 'LegalEase - Professional Notary Services Made Easy', 'text', 'seo', 'Meta Title');
        LandingPageSetting::setValue('meta_description', 'Get your documents notarized online in minutes. Secure, convenient, and professional.', 'text', 'seo', 'Meta Description');

        // Navigation
        LandingPageSetting::setValue('nav_items', json_encode(['Home', 'Services', 'How It Works', 'Reviews']), 'json', 'navigation', 'Navigation Menu Items');
        LandingPageSetting::setValue('nav_cta_text', 'Get Started', 'text', 'navigation', 'Nav CTA Button Text');
        LandingPageSetting::setValue('nav_signin_text', 'Sign In', 'text', 'navigation', 'Nav Sign In Text');

        // Chatbot
        LandingPageSetting::setValue('chatbot_section_enabled', 'true', 'boolean', 'chatbot', 'Show Chatbot Section');
        LandingPageSetting::setValue('chatbot_section_title', 'Have Questions? Ask Our AI Assistant', 'text', 'chatbot', 'Chatbot Section Title');
        LandingPageSetting::setValue('chatbot_section_subtitle', 'Get instant answers about our services, pricing, requirements, and more. Our AI assistant is available 24/7.', 'text', 'chatbot', 'Chatbot Section Subtitle');
        LandingPageSetting::setValue('chatbot_placeholder', 'Ask about our services, pricing, requirements...', 'text', 'chatbot', 'Chatbot Input Placeholder');

        // Feedback form
        LandingPageSetting::setValue('feedback_form_title', 'Share Your Feedback', 'text', 'feedback', 'Feedback Form Title');
        LandingPageSetting::setValue('feedback_categories', json_encode([
            ['value' => 'service_quality', 'label' => 'Service Quality'],
            ['value' => 'speed', 'label' => 'Speed'],
            ['value' => 'support', 'label' => 'Support'],
            ['value' => 'system_experience', 'label' => 'System Experience'],
            ['value' => 'bug_report', 'label' => 'Bug Report'],
            ['value' => 'suggestion', 'label' => 'Suggestion'],
            ['value' => 'other', 'label' => 'Other'],
        ]), 'json', 'feedback', 'Feedback Categories');

        // ===== HERO SECTION =====
        $hero = LandingPageSection::create([
            'section_key' => 'hero',
            'title' => 'Professional Notary',
            'subtitle' => 'Services Made Easy',
            'description' => 'Get your documents notarized online in minutes. Secure, convenient, and professional. No hidden fees, no complicated process.',
            'badge_text' => '✨ Trusted Legal Notary Service',
            'button_primary_text' => 'Book Appointment',
            'button_primary_link' => '#auth',
            'button_secondary_text' => 'Learn More',
            'button_secondary_link' => '#howitworks',
            'image_url' => '/hero.webp',
            'image_alt' => 'Legal Notary Services',
            'sort_order' => 1,
            'is_visible' => true,
        ]);

        // Trust indicators as items on hero
        $hero->items()->createMany([
            ['title' => 'Documents Notarized', 'description' => '500+', 'icon' => '📄', 'sort_order' => 1, 'metadata' => ['stat_key' => 'totalAppointments', 'fallback' => '500+']],
            ['title' => 'Satisfied Clients', 'description' => '1000+', 'icon' => '👥', 'sort_order' => 2, 'metadata' => ['stat_key' => 'totalUsers', 'fallback' => '1000+']],
            ['title' => 'Available Anytime', 'description' => '8/5', 'icon' => '🕐', 'sort_order' => 3, 'metadata' => ['static_value' => '8/5']],
        ]);

        // ===== STATS SECTION =====
        $stats = LandingPageSection::create([
            'section_key' => 'stats',
            'title' => 'Our Impact',
            'sort_order' => 2,
            'is_visible' => true,
        ]);

        $stats->items()->createMany([
            ['title' => 'Total Appointments', 'icon' => '📅', 'sort_order' => 1, 'metadata' => ['stat_key' => 'totalAppointments', 'suffix' => '+']],
            ['title' => 'Active Users', 'icon' => '👤', 'sort_order' => 2, 'metadata' => ['stat_key' => 'totalUsers', 'suffix' => '+']],
            ['title' => 'Completed Services', 'icon' => '✅', 'sort_order' => 3, 'metadata' => ['stat_key' => 'completedAppointments', 'suffix' => '+']],
            ['title' => 'Available Services', 'icon' => '⚙️', 'sort_order' => 4, 'metadata' => ['stat_key' => 'totalServices', 'suffix' => '']],
        ]);

        // ===== SERVICES SECTION =====
        $services = LandingPageSection::create([
            'section_key' => 'services',
            'title' => 'Complete Notary Solutions',
            'subtitle' => 'Our Services',
            'description' => 'Professional notarization services tailored to your legal needs',
            'badge_text' => 'Our Services',
            'sort_order' => 3,
            'is_visible' => true,
            'metadata' => ['use_api_services' => true, 'api_limit' => 4],
        ]);

        // Fallback feature items (used when API services unavailable)
        $services->items()->createMany([
            ['title' => 'Instant Booking', 'description' => 'Schedule appointments in seconds with our intuitive booking system', 'icon' => '⏱️', 'sort_order' => 1],
            ['title' => 'Document Security', 'description' => 'Military-grade encryption for all your sensitive legal documents', 'icon' => '🛡️', 'sort_order' => 2],
            ['title' => 'Real-time Tracking', 'description' => 'Track your appointment status and receive live updates', 'icon' => '📱', 'sort_order' => 3],
            ['title' => 'Available Always', 'description' => 'Book and manage appointments anytime, anywhere', 'icon' => '🌙', 'sort_order' => 4],
        ]);

        // ===== HOW IT WORKS SECTION =====
        $howItWorks = LandingPageSection::create([
            'section_key' => 'how_it_works',
            'title' => 'Simple 4-Step Process',
            'subtitle' => 'How It Works',
            'description' => 'Get your documents notarized in minutes with our streamlined process',
            'badge_text' => 'How It Works',
            'sort_order' => 4,
            'is_visible' => true,
            'metadata' => ['visual_icon' => '📋', 'visual_text' => 'Secure Document Processing'],
        ]);

        $howItWorks->items()->createMany([
            ['title' => 'Register Account', 'description' => 'Create your secure account in under 2 minutes', 'step_number' => '01', 'sort_order' => 1],
            ['title' => 'Verify Identity', 'description' => 'Complete quick identity verification process', 'step_number' => '02', 'sort_order' => 2],
            ['title' => 'Book Appointment', 'description' => 'Choose your preferred date and time slot', 'step_number' => '03', 'sort_order' => 3],
            ['title' => 'Get Notarized', 'description' => 'Complete your notarization seamlessly', 'step_number' => '04', 'sort_order' => 4],
        ]);

        // ===== TESTIMONIALS SECTION =====
        LandingPageSection::create([
            'section_key' => 'testimonials',
            'title' => 'Trusted by Hundreds',
            'subtitle' => 'Testimonials',
            'description' => 'Real feedback from clients who have experienced our professional notary service',
            'badge_text' => 'Testimonials',
            'button_primary_text' => 'See All Testimonials',
            'sort_order' => 5,
            'is_visible' => true,
            'metadata' => ['api_limit' => 3, 'fallback_message' => 'Professional and reliable notary service. Made my document process so much easier!'],
        ]);

        // ===== CTA SECTION =====
        LandingPageSection::create([
            'section_key' => 'cta',
            'title' => 'Ready to Get Started?',
            'description' => 'Join hundreds of satisfied clients who trust us with their important legal documents',
            'button_primary_text' => 'Start Now',
            'button_primary_link' => '#auth',
            'button_secondary_text' => 'Learn More',
            'button_secondary_link' => '#howitworks',
            'sort_order' => 6,
            'is_visible' => true,
        ]);

        // ===== CHATBOT SECTION =====
        LandingPageSection::create([
            'section_key' => 'chatbot',
            'title' => 'Have Questions? Ask Our AI Assistant',
            'description' => 'Get instant answers about our services, pricing, requirements, and more. Our AI assistant is available 24/7.',
            'badge_text' => 'AI Assistant',
            'sort_order' => 7,
            'is_visible' => true,
            'metadata' => [
                'placeholder' => 'Ask about our services, pricing, requirements...',
                'suggested_questions' => [
                    'What services do you offer?',
                    'How do I book an appointment?',
                    'What are your business hours?',
                    'What documents do I need?',
                ],
            ],
        ]);
    }
}
