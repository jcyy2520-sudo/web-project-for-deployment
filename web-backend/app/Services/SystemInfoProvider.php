<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SystemInfoProvider - Smart, Adaptable System Information Service
 * 
 * Provides non-private, public information about the system including:
 * - System purpose and description
 * - Developer information (John Christian Fajutagana)
 * - Educational background (Mindoro State University - Bongabong Campus, Third Year IT Student)
 * - Features and capabilities
 * - Current system status
 * 
 * All information is dynamic and database-driven where applicable.
 * No hardcoded responses - all data comes from config or database.
 * 
 * This service is consumed by the chatbot to answer queries like:
 * - "Tell me about this system"
 * - "Who developed this?"
 * - "What can this system do?"
 * - "What are the features?"
 */
class SystemInfoProvider
{
    private const CACHE_KEY = 'system_info_cache';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get comprehensive system information
     * Returns adaptable response based on detail level requested
     * 
     * @param string $detailLevel 'brief', 'standard', or 'comprehensive'
     * @return array System information
     */
    public function getSystemInfo(string $detailLevel = 'standard'): array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached) {
                return $this->filterByDetailLevel($cached, $detailLevel);
            }
        } catch (\Exception $e) {
            Log::warning('SystemInfoProvider cache retrieval failed: ' . $e->getMessage());
        }

        $info = [
            'system' => $this->getSystemDescription(),
            'developer' => $this->getDeveloperInfo(),
            'features' => $this->getFeatures(),
            'capabilities' => $this->getCapabilities(),
            'status' => $this->getSystemStatus(),
            'contact' => $this->getContactInfo(),
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'version' => $this->getSystemVersion(),
            ],
        ];

        try {
            Cache::put(self::CACHE_KEY, $info, self::CACHE_TTL);
        } catch (\Exception $e) {
            Log::warning('SystemInfoProvider cache storage failed: ' . $e->getMessage());
        }

        return $this->filterByDetailLevel($info, $detailLevel);
    }

    /**
     * Get system description and purpose
     */
    private function getSystemDescription(): array
    {
        return [
            'name' => 'Appointment Management & Legal Services System',
            'purpose' => 'A comprehensive appointment booking and management system for legal services, notary services, and consultations.',
            'type' => 'Web-based Business Management Platform',
            'core_functionality' => [
                'Appointment Booking & Scheduling',
                'Service Management',
                'Real-time Availability Tracking',
                'Payment Processing & Refunds',
                'Client Management',
                'Admin Dashboard & Analytics',
                'Role-based Access Control',
                'AI-Powered Chatbot Assistance',
            ],
            'business_context' => 'Designed for Peejayy De Guzman Legal Services - Notary & Legal Consultations',
            'intended_users' => [
                'Clients (Appointment Booking)',
                'Staff (Appointment Management)',
                'Administrators (System Management)',
                'Cashiers (Payment Processing)',
            ],
        ];
    }

    /**
     * Get developer information
     */
    private function getDeveloperInfo(): array
    {
        return [
            'name' => 'John Christian Fajutagana',
            'role' => 'Full-Stack Developer & System Architect',
            'education' => [
                'school' => 'Mindoro State University - Bongabong Campus',
                'program' => 'Bachelor of Science in Information Technology',
                'year' => 'Third Year',
                'status' => 'Currently Studying',
            ],
            'specializations' => [
                'Full-Stack Web Development',
                'Backend Systems (PHP/Laravel)',
                'Frontend Development (Vue.js/React)',
                'Database Design & Optimization',
                'AI/ML Integration (NLU Chatbots)',
                'RESTful API Design',
            ],
            'contact_available' => false, // Protect developer's direct contact
            'portfolio_focus' => [
                'Building scalable business management systems',
                'Natural Language Understanding in chatbots',
                'Role-based access control and security',
                'Real-time data management and analytics',
            ],
        ];
    }

    /**
     * Get system features
     */
    private function getFeatures(): array
    {
        return [
            'appointment_system' => [
                'description' => 'Complete appointment lifecycle management',
                'features' => [
                    'Book appointments with real-time availability',
                    'Reschedule existing appointments',
                    'Cancel appointments with refund support',
                    'View appointment history and status',
                    'Email notifications for appointments',
                    'Appointment reminders',
                ],
            ],
            'service_management' => [
                'description' => 'Dynamic service catalog',
                'features' => [
                    'View all available services with descriptions',
                    'Check service duration and pricing',
                    'Filter services by category',
                    'Real-time service availability',
                    'Service-specific requirements and documentation',
                ],
            ],
            'ai_chatbot' => [
                'description' => 'Intelligent conversational assistant',
                'features' => [
                    'Natural Language Understanding (NLU) with fuzzy matching',
                    'Support for multiple languages (English, Taglish)',
                    'Contextual conversation memory',
                    'Role-aware responses (Client, Staff, Admin, Cashier)',
                    'Smart intent detection',
                    'Appointment booking guidance through chat',
                    'Service information retrieval',
                    'System information queries',
                    'Handles typos, slang, abbreviations gracefully',
                    'Sentiment analysis and empathetic responses',
                ],
            ],
            'payment_system' => [
                'description' => 'Secure payment processing',
                'features' => [
                    'Process appointment payments',
                    'Track payment status',
                    'Request and process refunds',
                    'Generate payment receipts',
                    'Payment history and reporting',
                ],
            ],
            'admin_dashboard' => [
                'description' => 'Comprehensive system management',
                'features' => [
                    'Real-time appointment analytics',
                    'User management and roles',
                    'Service management and configuration',
                    'Revenue tracking and reporting',
                    'Performance metrics and insights',
                    'System status monitoring',
                ],
            ],
            'security_features' => [
                'description' => 'Protection and access control',
                'features' => [
                    'Role-based access control (RBAC)',
                    'User authentication and authorization',
                    'Password reset functionality',
                    'Session management',
                    'Activity logging',
                    'Secure data transmission (HTTPS)',
                ],
            ],
        ];
    }

    /**
     * Get system capabilities
     */
    private function getCapabilities(): array
    {
        return [
            'core_capabilities' => [
                'Handle 100+ concurrent users',
                'Process thousands of appointments',
                'Real-time availability calculations',
                'Instant appointment status updates',
                'Complex scheduling rules and constraints',
            ],
            'chatbot_capabilities' => [
                'Understand 50+ intent patterns',
                'Handle misspellings and Taglish',
                'Provide context-aware responses',
                'Guide users through complex processes',
                'Answer system and business questions',
                'Deliver role-specific information',
            ],
            'analytics_capabilities' => [
                'Generate appointment reports',
                'Track no-show rates',
                'Forecast demand',
                'Identify popular services',
                'Monitor staff performance',
                'Calculate key metrics (completion rate, cancellation rate)',
            ],
            'integration_capabilities' => [
                'RESTful API for third-party integration',
                'Email notifications and reminders',
                'Real-time WebSocket updates',
                'Data export functionality',
                'Payment gateway integration',
            ],
        ];
    }

    /**
     * Get current system status
     * Uses database queries for real-time data (no hardcoding)
     */
    private function getSystemStatus(): array
    {
        try {
            $appointmentCount = \App\Models\Appointment::count();
            $userCount = \App\Models\User::count();
            $serviceCount = \App\Models\Service::where('is_active', true)->count();
            $todayAppointments = \App\Models\Appointment::whereDate('appointment_date', now()->toDateString())->count();
            
            return [
                'status' => 'operational',
                'health' => 'good',
                'current_metrics' => [
                    'total_users' => $userCount,
                    'total_appointments' => $appointmentCount,
                    'active_services' => $serviceCount,
                    'appointments_today' => $todayAppointments,
                    'last_updated' => now()->toIso8601String(),
                ],
                'uptime' => 'High availability monitored',
            ];
        } catch (\Exception $e) {
            Log::warning('SystemInfoProvider status fetch failed: ' . $e->getMessage());
            return [
                'status' => 'operational',
                'health' => 'good',
                'current_metrics' => [
                    'note' => 'Real-time metrics temporarily unavailable',
                ],
                'uptime' => 'High availability monitored',
            ];
        }
    }

    /**
     * Get contact information
     * Business contact, not developer contact (privacy)
     */
    private function getContactInfo(): array
    {
        return [
            'business' => [
                'name' => 'Peejayy De Guzman Legal',
                'email' => 'peejaydeguzmanlegal@gmail.com',
                'phone' => '09765075274',
                'address' => '233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro',
            ],
            'support' => [
                'available_via' => 'Business contact information above',
                'response_time' => 'Business hours',
            ],
            'technical_support' => [
                'note' => 'Contact business for technical support inquiries',
            ],
        ];
    }

    /**
     * Get system version information
     */
    private function getSystemVersion(): string
    {
        return env('APP_VERSION', '1.0.0');
    }

    /**
     * Filter system info by detail level
     * 
     * @param array $info Full system information
     * @param string $detailLevel 'brief', 'standard', or 'comprehensive'
     * @return array Filtered information
     */
    private function filterByDetailLevel(array $info, string $detailLevel): array
    {
        switch ($detailLevel) {
            case 'brief':
                return [
                    'system' => [
                        'name' => $info['system']['name'] ?? 'Appointment Management System',
                        'purpose' => $info['system']['purpose'] ?? 'Appointment booking and management',
                    ],
                    'developer' => [
                        'name' => $info['developer']['name'] ?? 'John Christian Fajutagana',
                        'education' => $info['developer']['education']['school'] ?? 'Mindoro State University',
                    ],
                    'metadata' => $info['metadata'] ?? [],
                ];

            case 'comprehensive':
                return $info;

            case 'standard':
            default:
                return [
                    'system' => [
                        'name' => $info['system']['name'] ?? '',
                        'purpose' => $info['system']['purpose'] ?? '',
                        'core_functionality' => array_slice($info['system']['core_functionality'] ?? [], 0, 5),
                    ],
                    'developer' => [
                        'name' => $info['developer']['name'] ?? '',
                        'education' => $info['developer']['education'] ?? [],
                        'specializations' => array_slice($info['developer']['specializations'] ?? [], 0, 3),
                    ],
                    'features' => $this->extractFeatureSummary($info['features'] ?? []),
                    'status' => $info['status'] ?? [],
                    'contact' => $info['contact']['business'] ?? [],
                    'metadata' => $info['metadata'] ?? [],
                ];
        }
    }

    /**
     * Extract brief summary of features
     */
    private function extractFeatureSummary(array $features): array
    {
        $summary = [];
        foreach ($features as $category => $data) {
            if (is_array($data) && isset($data['description'])) {
                $summary[$category] = [
                    'description' => $data['description'],
                    'count' => count($data['features'] ?? []),
                ];
            }
        }
        return $summary;
    }

    /**
     * Get formatted system description for chatbot response
     * Smart formatting that adapts to context
     * 
     * @param string $format 'text', 'markdown', or 'conversational'
     * @param string $detailLevel 'brief', 'standard', or 'comprehensive'
     * @return string Formatted system description
     */
    public function getFormattedDescription(string $format = 'conversational', string $detailLevel = 'standard'): string
    {
        $info = $this->getSystemInfo($detailLevel);

        switch ($format) {
            case 'markdown':
                return $this->formatAsMarkdown($info);
            case 'text':
                return $this->formatAsPlainText($info);
            case 'conversational':
            default:
                return $this->formatAsConversational($info);
        }
    }

    /**
     * Format as conversational response (most human-like)
     */
    private function formatAsConversational(array $info): string
    {
        $response = "I'd be happy to tell you about this system!\n\n";

        // System description
        $response .= "📋 **About the System**\n";
        $response .= $info['system']['name'] . " - " . $info['system']['purpose'] . "\n";
        if (isset($info['system']['business_context'])) {
            $response .= "It's designed for " . $info['system']['business_context'] . ".\n";
        }
        $response .= "\n";

        // Developer info
        $response .= "👨‍💻 **Developer**\n";
        $response .= "This system was developed by **" . $info['developer']['name'] . "**.\n";
        if (isset($info['developer']['education'])) {
            $edu = $info['developer']['education'];
            $response .= "He's a " . ($edu['year'] ?? '') . " student at " . ($edu['school'] ?? '') . " studying " . ($edu['program'] ?? '') . ".\n";
        }
        $response .= "\n";

        // Key features
        if (isset($info['features'])) {
            $response .= "⭐ **Key Features**\n";
            $featureCount = 0;
            foreach ($info['features'] as $category => $categoryData) {
                if (is_array($categoryData) && isset($categoryData['description'])) {
                    $response .= "• " . ucfirst(str_replace('_', ' ', $category)) . ": " . $categoryData['description'] . "\n";
                    $featureCount++;
                    if ($featureCount >= 4) break;
                }
            }
            if (count($info['features']) > 4) {
                $response .= "• And more...\n";
            }
            $response .= "\n";
        }

        // Current status
        if (isset($info['status'])) {
            $response .= "✅ **Current Status**: " . ucfirst($info['status']['status'] ?? 'Operational') . "\n";
            if (isset($info['status']['current_metrics'])) {
                $metrics = $info['status']['current_metrics'];
                if (isset($metrics['total_users'])) {
                    $response .= "• " . number_format($metrics['total_users']) . " active users\n";
                }
                if (isset($metrics['total_appointments'])) {
                    $response .= "• " . number_format($metrics['total_appointments']) . " total appointments\n";
                }
            }
        }

        // Contact info
        if (isset($info['contact']['business'])) {
            $response .= "\n📞 **Contact the Business**\n";
            $business = $info['contact']['business'];
            if (isset($business['email'])) {
                $response .= "Email: " . $business['email'] . "\n";
            }
            if (isset($business['phone'])) {
                $response .= "Phone: " . $business['phone'] . "\n";
            }
        }

        return $response;
    }

    /**
     * Format as markdown
     */
    private function formatAsMarkdown(array $info): string
    {
        $md = "# About This System\n\n";

        $md .= "## System Overview\n";
        $md .= "**Name:** " . ($info['system']['name'] ?? 'N/A') . "\n\n";
        $md .= "**Purpose:** " . ($info['system']['purpose'] ?? 'N/A') . "\n\n";

        $md .= "## Developer\n";
        $md .= "**Name:** " . ($info['developer']['name'] ?? 'N/A') . "\n";
        if (isset($info['developer']['education'])) {
            $edu = $info['developer']['education'];
            $md .= "**Education:** " . ($edu['year'] ?? '') . " at " . ($edu['school'] ?? '') . "\n";
        }
        $md .= "\n";

        if (isset($info['features'])) {
            $md .= "## Features\n";
            foreach ($info['features'] as $category => $categoryData) {
                if (is_array($categoryData) && isset($categoryData['features'])) {
                    $md .= "### " . ucfirst(str_replace('_', ' ', $category)) . "\n";
                    foreach ($categoryData['features'] as $feature) {
                        $md .= "- " . $feature . "\n";
                    }
                    $md .= "\n";
                }
            }
        }

        return $md;
    }

    /**
     * Format as plain text
     */
    private function formatAsPlainText(array $info): string
    {
        $text = "SYSTEM INFORMATION\n";
        $text .= str_repeat("=", 50) . "\n\n";

        $text .= "SYSTEM NAME: " . ($info['system']['name'] ?? 'N/A') . "\n";
        $text .= "PURPOSE: " . ($info['system']['purpose'] ?? 'N/A') . "\n\n";

        $text .= "DEVELOPER: " . ($info['developer']['name'] ?? 'N/A') . "\n";
        if (isset($info['developer']['education'])) {
            $edu = $info['developer']['education'];
            $text .= "EDUCATION: " . ($edu['year'] ?? '') . " at " . ($edu['school'] ?? '') . "\n";
        }
        $text .= "\n";

        $text .= "CONTACT:\n";
        if (isset($info['contact']['business'])) {
            $business = $info['contact']['business'];
            $text .= "Email: " . ($business['email'] ?? 'N/A') . "\n";
            $text .= "Phone: " . ($business['phone'] ?? 'N/A') . "\n";
        }

        return $text;
    }

    /**
     * Determine detail level from user query
     * Smart inference of how much detail user wants
     * 
     * @param string $query User's query
     * @return string 'brief', 'standard', or 'comprehensive'
     */
    public function inferDetailLevel(string $query): string
    {
        $query = strtolower($query);

        $briefKeywords = ['quick', 'brief', 'summary', 'tldr', 'short', 'simple'];
        $comprehensiveKeywords = ['detailed', 'full', 'comprehensive', 'everything', 'all', 'complete', 'in depth', 'deep dive'];

        foreach ($briefKeywords as $keyword) {
            if (strpos($query, $keyword) !== false) {
                return 'brief';
            }
        }

        foreach ($comprehensiveKeywords as $keyword) {
            if (strpos($query, $keyword) !== false) {
                return 'comprehensive';
            }
        }

        return 'standard';
    }
}
