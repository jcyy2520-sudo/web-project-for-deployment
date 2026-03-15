<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            if (DB::getDriverName() === 'sqlite') {
                $indexes = DB::select("PRAGMA index_list('{$table}')");
                foreach ($indexes as $index) {
                    if ($index->name === $indexName) return true;
                }
                return false;
            }
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function up(): void
    {
        // Add indexes to the users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!$this->indexExists('users', 'users_role_is_active_index')) {
                    $table->index(['role', 'is_active'], 'users_role_is_active_index');
                }
                if (!$this->indexExists('users', 'users_role_index')) {
                    $table->index('role', 'users_role_index');
                }
                if (!$this->indexExists('users', 'users_is_active_index')) {
                    $table->index('is_active', 'users_is_active_index');
                }
            });
        }

        // Add indexes to the appointments table
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (!$this->indexExists('appointments', 'appointments_status_index')) {
                    $table->index('status', 'appointments_status_index');
                }
                if (Schema::hasColumn('appointments', 'payment_status')) {
                    if (!$this->indexExists('appointments', 'appointments_payment_status_index')) {
                        $table->index('payment_status', 'appointments_payment_status_index');
                    }
                }
                if (Schema::hasColumn('appointments', 'appointment_date')) {
                    if (!$this->indexExists('appointments', 'appointments_appointment_date_index')) {
                        $table->index('appointment_date', 'appointments_appointment_date_index');
                    }
                }
                if (!$this->indexExists('appointments', 'appointments_created_at_index')) {
                    $table->index('created_at', 'appointments_created_at_index');
                }
            });
        }

        // Add indexes to the services table
        if (Schema::hasTable('services')) {
            Schema::table('services', function (Blueprint $table) {
                if (Schema::hasColumn('services', 'is_active')) {
                    if (!$this->indexExists('services', 'services_is_active_index')) {
                        $table->index('is_active', 'services_is_active_index');
                    }
                }
            });
        }

        // Add indexes to the refunds table
        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $table) {
                if (Schema::hasColumn('refunds', 'status')) {
                    if (!$this->indexExists('refunds', 'refunds_status_index')) {
                        $table->index('status', 'refunds_status_index');
                    }
                }
                if (!$this->indexExists('refunds', 'refunds_created_at_index')) {
                    $table->index('created_at', 'refunds_created_at_index');
                }
            });
        }

        // Add indexes to the landing page sections and items
        if (Schema::hasTable('landing_page_sections')) {
            Schema::table('landing_page_sections', function (Blueprint $table) {
                if (Schema::hasColumn('landing_page_sections', 'is_visible')) {
                    if (!$this->indexExists('landing_page_sections', 'landing_page_sections_is_visible_index')) {
                        $table->index('is_visible', 'landing_page_sections_is_visible_index');
                    }
                }
            });
        }

        if (Schema::hasTable('landing_page_items')) {
            Schema::table('landing_page_items', function (Blueprint $table) {
                if (Schema::hasColumn('landing_page_items', 'is_visible')) {
                    if (!$this->indexExists('landing_page_items', 'landing_page_items_is_visible_index')) {
                        $table->index('is_visible', 'landing_page_items_is_visible_index');
                    }
                }
                if (Schema::hasColumn('landing_page_items', 'section_id')) {
                    if (!$this->indexExists('landing_page_items', 'landing_page_items_section_id_is_visible_index')) {
                        $table->index(['section_id', 'is_visible'], 'landing_page_items_section_id_is_visible_index');
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->indexExists('users', 'users_role_is_active_index')) {
                    $table->dropIndex('users_role_is_active_index');
                }
                if ($this->indexExists('users', 'users_role_index')) {
                    $table->dropIndex('users_role_index');
                }
                if ($this->indexExists('users', 'users_is_active_index')) {
                    $table->dropIndex('users_is_active_index');
                }
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if ($this->indexExists('appointments', 'appointments_status_index')) {
                    $table->dropIndex('appointments_status_index');
                }
                if ($this->indexExists('appointments', 'appointments_payment_status_index')) {
                    $table->dropIndex('appointments_payment_status_index');
                }
                if ($this->indexExists('appointments', 'appointments_appointment_date_index')) {
                    $table->dropIndex('appointments_appointment_date_index');
                }
                if ($this->indexExists('appointments', 'appointments_created_at_index')) {
                    $table->dropIndex('appointments_created_at_index');
                }
            });
        }

        if (Schema::hasTable('services')) {
            Schema::table('services', function (Blueprint $table) {
                if ($this->indexExists('services', 'services_is_active_index')) {
                    $table->dropIndex('services_is_active_index');
                }
            });
        }

        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $table) {
                if ($this->indexExists('refunds', 'refunds_status_index')) {
                    $table->dropIndex('refunds_status_index');
                }
                if ($this->indexExists('refunds', 'refunds_created_at_index')) {
                    $table->dropIndex('refunds_created_at_index');
                }
            });
        }

        if (Schema::hasTable('landing_page_sections')) {
            Schema::table('landing_page_sections', function (Blueprint $table) {
                if ($this->indexExists('landing_page_sections', 'landing_page_sections_is_visible_index')) {
                    $table->dropIndex('landing_page_sections_is_visible_index');
                }
            });
        }

        if (Schema::hasTable('landing_page_items')) {
            Schema::table('landing_page_items', function (Blueprint $table) {
                if ($this->indexExists('landing_page_items', 'landing_page_items_is_visible_index')) {
                    $table->dropIndex('landing_page_items_is_visible_index');
                }
                if ($this->indexExists('landing_page_items', 'landing_page_items_section_id_is_visible_index')) {
                    $table->dropIndex('landing_page_items_section_id_is_visible_index');
                }
            });
        }
    }
};
