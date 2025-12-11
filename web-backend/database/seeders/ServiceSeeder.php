<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Services without dollar sign
        $services = [
            [
                'name' => 'Legal Consultation',
                'description' => 'One-on-one consultation with a legal professional',
                'price' => 1500,
                'duration' => 60,
                'is_active' => true
            ],
            [
                'name' => 'Document Review',
                'description' => 'Detailed review of legal documents',
                'price' => 2000,
                'duration' => 90,
                'is_active' => true
            ],
            [
                'name' => 'Contract Drafting',
                'description' => 'Professional contract preparation and drafting',
                'price' => 3500,
                'duration' => 120,
                'is_active' => true
            ],
            [
                'name' => 'Notarization Service',
                'description' => 'Official document notarization and certification',
                'price' => 500,
                'duration' => 30,
                'is_active' => true
            ],
            [
                'name' => 'Will Preparation',
                'description' => 'Estate planning and will preparation',
                'price' => 4000,
                'duration' => 150,
                'is_active' => true
            ],
            [
                'name' => 'Property Legal Review',
                'description' => 'Legal review for property transactions',
                'price' => 2500,
                'duration' => 90,
                'is_active' => true
            ],
            [
                'name' => 'Family Law Consultation',
                'description' => 'Guidance on family law matters',
                'price' => 2200,
                'duration' => 60,
                'is_active' => true
            ],
            [
                'name' => 'Business Formation',
                'description' => 'Help with setting up business entities',
                'price' => 3000,
                'duration' => 120,
                'is_active' => true
            ],
            [
                'name' => 'Litigation Support',
                'description' => 'Support for legal disputes and court cases',
                'price' => 5000,
                'duration' => 180,
                'is_active' => true
            ],
            [
                'name' => 'Deed Recording',
                'description' => 'Recording and filing of property deeds',
                'price' => 800,
                'duration' => 45,
                'is_active' => true
            ]
        ];

        // Delete existing services (skip existing ones if they exist)
        if (Service::count() === 0) {
            foreach ($services as $service) {
                Service::create($service);
            }
            $this->command->info('Services seeded successfully!');
            $this->command->info('Created 10 services with prices');
        } else {
            $this->command->info('Services already exist. Skipping seeding.');
        }
    }
}
