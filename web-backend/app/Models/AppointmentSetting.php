<?php

namespace App\Models;

/**
 * Backwards-compatible alias for AppointmentSettings.
 * Some older code referenced App\Models\AppointmentSetting (singular).
 * This class extends the canonical AppointmentSettings model to avoid
 * "Class not found" errors while keeping a single source of truth.
 */
class AppointmentSetting extends AppointmentSettings
{
    // Intentionally empty. Inherits all behavior from AppointmentSettings.
}
