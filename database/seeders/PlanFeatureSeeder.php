<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Create features and ensure permissions exist
            $features = [
                [
                    'name' => 'Product Management',
                    'key' => 'product-management',
                    'description' => 'Create, edit, and delete products',
                    'permission_key' => 'manage-products',
                    'is_active' => true,
                ],
                [
                    'name' => 'Order Management',
                    'key' => 'order-management',
                    'description' => 'Process and manage orders',
                    'permission_key' => 'manage-orders',
                    'is_active' => true,
                ],
                [
                    'name' => 'Inventory Management',
                    'key' => 'inventory-management',
                    'description' => 'Track and manage inventory',
                    'permission_key' => 'manage-inventory',
                    'is_active' => true,
                ],
                [
                    'name' => 'Customer Management',
                    'key' => 'customer-management',
                    'description' => 'Manage customer information',
                    'permission_key' => 'manage-customers',
                    'is_active' => true,
                ],
                [
                    'name' => 'Reporting & Analytics',
                    'key' => 'reporting-analytics',
                    'description' => 'View sales reports and analytics',
                    'permission_key' => 'view-reports',
                    'is_active' => true,
                ],
                [
                    'name' => 'Staff Management',
                    'key' => 'staff-management',
                    'description' => 'Add and manage staff members',
                    'permission_key' => 'manage-staff',
                    'is_active' => true,
                ],
                [
                    'name' => 'Role Management',
                    'key' => 'role-management',
                    'description' => 'Create and manage custom roles',
                    'permission_key' => 'manage-roles',
                    'is_active' => true,
                ],
                [
                    'name' => 'Discount Management',
                    'key' => 'discount-management',
                    'description' => 'Create and manage discounts',
                    'permission_key' => 'manage-discounts',
                    'is_active' => true,
                ],
                [
                    'name' => 'Shipping Management',
                    'key' => 'shipping-management',
                    'description' => 'Manage shipping options',
                    'permission_key' => 'manage-shipping',
                    'is_active' => true,
                ],
                [
                    'name' => 'Tax Management',
                    'key' => 'tax-management',
                    'description' => 'Configure tax settings',
                    'permission_key' => 'manage-taxes',
                    'is_active' => true,
                ],
            ];

            $createdFeatures = collect();
            foreach ($features as $feature) {
                // Ensure permission exists
                Permission::firstOrCreate(
                    ['key' => $feature['permission_key']],
                    [
                        'name' => ucfirst(str_replace('-', ' ', $feature['permission_key'])),
                        'key' => $feature['permission_key'],
                    ]
                );

                $createdFeatures->push(Feature::updateOrCreate(
                    ['key' => $feature['key']],
                    $feature
                ));
            }

            // Create plans
            $starterPlan = Plan::updateOrCreate(
                ['slug' => 'starter'],
                [
                    'name' => 'Starter',
                    'slug' => 'starter',
                    'description' => 'Perfect for small businesses',
                    'price' => 29.99,
                    'billing_cycle' => 'monthly',
                    'is_active' => true,
                    'max_stores' => 1,
                    'max_staff_per_store' => 5,
                    'trial_days' => 14,
                    'is_default' => true,
                ]
            );

            $professionalPlan = Plan::updateOrCreate(
                ['slug' => 'professional'],
                [
                    'name' => 'Professional',
                    'slug' => 'professional',
                    'description' => 'For growing businesses',
                    'price' => 79.99,
                    'billing_cycle' => 'monthly',
                    'is_active' => true,
                    'max_stores' => 3,
                    'max_staff_per_store' => 25,
                    'trial_days' => 14,
                    'is_default' => false,
                ]
            );

            $enterprisePlan = Plan::updateOrCreate(
                ['slug' => 'enterprise'],
                [
                    'name' => 'Enterprise',
                    'slug' => 'enterprise',
                    'description' => 'For large organizations',
                    'price' => 199.99,
                    'billing_cycle' => 'monthly',
                    'is_active' => true,
                    'max_stores' => 10,
                    'max_staff_per_store' => 100,
                    'trial_days' => 30,
                    'is_default' => false,
                ]
            );

            // Assign features to plans
            // Starter: Basic features
            $starterPlan->features()->sync([
                $createdFeatures->where('key', 'product-management')->first()->id,
                $createdFeatures->where('key', 'order-management')->first()->id,
                $createdFeatures->where('key', 'inventory-management')->first()->id,
                $createdFeatures->where('key', 'customer-management')->first()->id,
            ]);

            // Professional: All starter + reporting + staff + discounts
            $professionalPlan->features()->sync([
                $createdFeatures->where('key', 'product-management')->first()->id,
                $createdFeatures->where('key', 'order-management')->first()->id,
                $createdFeatures->where('key', 'inventory-management')->first()->id,
                $createdFeatures->where('key', 'customer-management')->first()->id,
                $createdFeatures->where('key', 'reporting-analytics')->first()->id,
                $createdFeatures->where('key', 'staff-management')->first()->id,
                $createdFeatures->where('key', 'discount-management')->first()->id,
            ]);

            // Enterprise: All features
            $enterprisePlan->features()->sync($createdFeatures->pluck('id')->toArray());
        });
    }
}
