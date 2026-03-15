<?php
return [
    'query' => 'SELECT DATE(appointment_date) as date, COUNT(*) as count, GROUP_CONCAT(status SEPARATOR ",") as statuses FROM appointments WHERE user_id = 26 GROUP BY DATE(appointment_date) ORDER BY appointment_date DESC LIMIT 10;' 
];
