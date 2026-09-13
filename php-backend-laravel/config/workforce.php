<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Statutory Labor Compliance & Wage Rules
    |--------------------------------------------------------------------------
    |
    | Governs statutory deductions, overtime calculation, working hours,
    | and mandatory rest periods adhering to Indian labor law frameworks
    | (EPF Act 1952, ESI Act 1948, Factories Act, Shops & Establishments Act).
    |
    */

    'statutory' => [
        // Employee Provident Fund (PF)
        'pf_employee_rate' => (float) env('WORKFORCE_PF_EMPLOYEE_RATE', 0.12),
        'pf_employer_rate' => (float) env('WORKFORCE_PF_EMPLOYER_RATE', 0.12),

        // Employee State Insurance (ESI)
        'esi_employee_rate' => (float) env('WORKFORCE_ESI_EMPLOYEE_RATE', 0.0075),
        'esi_employer_rate' => (float) env('WORKFORCE_ESI_EMPLOYER_RATE', 0.0325),
        'esi_wage_ceiling' => (float) env('WORKFORCE_ESI_WAGE_CEILING', 21000.0),

        // Professional Tax (PT)
        'professional_tax_threshold' => (float) env('WORKFORCE_PT_THRESHOLD', 15000.0),
        'professional_tax_amount' => (float) env('WORKFORCE_PT_AMOUNT', 200.0),

        // Tax Deducted at Source (TDS)
        'tds_threshold' => (float) env('WORKFORCE_TDS_THRESHOLD', 40000.0),
        'tds_rate' => (float) env('WORKFORCE_TDS_RATE', 0.05),

        // Working Hours & Overtime
        'standard_work_hours_per_day' => (float) env('WORKFORCE_STANDARD_DAILY_HOURS', 8.0),
        'weekly_max_hours' => (float) env('WORKFORCE_WEEKLY_MAX_HOURS', 48.0),
        'overtime_rate_multiplier' => (float) env('WORKFORCE_OVERTIME_MULTIPLIER', 2.0),
        'min_rest_interval_hours' => (float) env('WORKFORCE_MIN_REST_HOURS', 8.0),
        'max_consecutive_work_days' => (int) env('WORKFORCE_MAX_CONSECUTIVE_DAYS', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tier Approval Workflows & SLA Rules
    |--------------------------------------------------------------------------
    |
    | Governs approval tier thresholds, delegation guardrails, and automated
    | SLA breach escalation windows.
    |
    */

    'approvals' => [
        // Attendance regularisation adjustment threshold (minutes) requiring Tier 2 (Venue GM / HR)
        'tier2_regularisation_threshold_minutes' => (int) env('WORKFORCE_TIER2_REGULARISATION_THRESHOLD_MINUTES', 120),

        // Leave duration threshold (days) requiring Tier 2
        'tier2_leave_threshold_days' => (float) env('WORKFORCE_TIER2_LEAVE_THRESHOLD_DAYS', 2.0),

        // Automated SLA breach escalation threshold (hours)
        'sla_escalation_hours' => (int) env('WORKFORCE_SLA_ESCALATION_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | High-Velocity Punch Ingestion & Edge Sync
    |--------------------------------------------------------------------------
    |
    | Cache deduplication, replay prevention, and batch synchronization limits.
    |
    */

    'ingestion' => [
        // Idempotency receipt cache retention window
        'idempotency_ttl_hours' => (int) env('WORKFORCE_IDEMPOTENCY_TTL_HOURS', 24),

        // Maximum events permitted per offline sync batch
        'max_batch_size' => (int) env('WORKFORCE_MAX_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cryptographic Audit Ledger
    |--------------------------------------------------------------------------
    |
    | Dedicated HMAC signing key, verification batch limits, and chain rules.
    |
    */

    'audit' => [
        'signing_key' => env('WORKFORCE_AUDIT_SIGNING_KEY'),
        'verification_batch_limit' => (int) env('WORKFORCE_AUDIT_VERIFY_LIMIT', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting & Protection
    |--------------------------------------------------------------------------
    |
    | Request throttling rates for edge punch ingestion vs intelligence computations.
    |
    */

    'rate_limiting' => [
        'punch_rpm' => (int) env('WORKFORCE_PUNCH_RPM', 120),
        'intelligence_rpm' => (int) env('WORKFORCE_INTELLIGENCE_RPM', 60),
    ],

];
