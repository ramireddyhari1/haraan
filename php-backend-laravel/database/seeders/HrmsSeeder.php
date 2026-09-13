<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeLeaveBalance;
use App\Models\Hrms\EmployeeLeaveType;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\Hrms\HolidayCalendar;
use App\Models\Hrms\HrmsAnnouncement;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class HrmsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin & Partner Accounts
        $admin = User::firstOrCreate(
            ['email' => 'admin@haraan.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Password@123'),
                'role' => 'ADMIN',
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
            ]
        );

        $partner = User::firstOrCreate(
            ['email' => 'partner@haraan.com'],
            [
                'name' => 'Chennai Turf Arena Partner',
                'password' => Hash::make('Password@123'),
                'role' => 'PARTNER',
                'status' => 'ACTIVE',
                'partner_type' => 'VENUE',
                'email_verified_at' => now(),
            ]
        );

        // Demo Venue with GPS coordinates (Chennai Central / Marina area)
        $venue = Venue::firstOrCreate(
            ['name' => 'HARAAN Arena - Central Hub'],
            [
                'partner_id' => $partner->id,
                'category' => 'TURF',
                'location' => 'Marina, Chennai',
                'address' => '100 Marina Loop Road, Chennai, TN 600001',
                'city' => 'Chennai',
                'latitude' => 13.0827,
                'longitude' => 80.2707,
                'is_active' => true,
                'sports' => ['cricket', 'football'],
            ]
        );

        // 2. Departments
        $deptOps = Department::firstOrCreate(
            ['code' => 'OPS'],
            [
                'name' => 'Operations',
                'description' => 'Venue and turf floor operations & scheduling',
                'is_active' => true,
            ]
        );

        $deptFd = Department::firstOrCreate(
            ['code' => 'FD'],
            [
                'name' => 'Front Desk & Hospitality',
                'description' => 'Customer reception, check-in, and guest care',
                'is_active' => true,
            ]
        );

        $deptGmt = Department::firstOrCreate(
            ['code' => 'GMT'],
            [
                'name' => 'Turf & Ground Maintenance',
                'description' => 'Turf upkeep, floodlight systems, and court maintenance',
                'is_active' => true,
            ]
        );

        $deptSec = Department::firstOrCreate(
            ['code' => 'SEC'],
            [
                'name' => 'Security & Facility Safety',
                'description' => 'Access control and physical security',
                'is_active' => true,
            ]
        );

        $deptAdm = Department::firstOrCreate(
            ['code' => 'ADM'],
            [
                'name' => 'HR & General Administration',
                'description' => 'Talent management, compliance, and payroll',
                'is_active' => true,
            ]
        );

        // 3. Designations
        $desOpsLead = Designation::firstOrCreate(
            ['code' => 'OPS-LEAD'],
            [
                'department_id' => $deptOps->id,
                'name' => 'Shift Operations Lead',
                'is_active' => true,
            ]
        );

        $desDeskExec = Designation::firstOrCreate(
            ['code' => 'DESK-EXEC'],
            [
                'department_id' => $deptFd->id,
                'name' => 'Front Desk Executive',
                'is_active' => true,
            ]
        );

        $desGroundCrew = Designation::firstOrCreate(
            ['code' => 'TURF-CREW'],
            [
                'department_id' => $deptGmt->id,
                'name' => 'Ground & Turf Crew',
                'is_active' => true,
            ]
        );

        $desSecurity = Designation::firstOrCreate(
            ['code' => 'SEC-MARSHAL'],
            [
                'department_id' => $deptSec->id,
                'name' => 'Security Marshal',
                'is_active' => true,
            ]
        );

        // 4. Shifts
        $shiftMorning = EmployeeShift::firstOrCreate(
            ['code' => 'MORN'],
            [
                'name' => 'Morning Shift',
                'start_time' => '07:00:00',
                'end_time' => '15:30:00',
                'grace_period_minutes' => 15,
                'half_day_threshold_minutes' => 240,
                'is_night_shift' => false,
                'is_rotational' => false,
                'is_active' => true,
            ]
        );

        $shiftEvening = EmployeeShift::firstOrCreate(
            ['code' => 'EVE'],
            [
                'name' => 'Evening Shift',
                'start_time' => '15:00:00',
                'end_time' => '23:30:00',
                'grace_period_minutes' => 15,
                'half_day_threshold_minutes' => 240,
                'is_night_shift' => false,
                'is_rotational' => false,
                'is_active' => true,
            ]
        );

        $shiftNight = EmployeeShift::firstOrCreate(
            ['code' => 'NIGHT'],
            [
                'name' => 'Night Shift',
                'start_time' => '23:00:00',
                'end_time' => '07:30:00',
                'grace_period_minutes' => 15,
                'half_day_threshold_minutes' => 240,
                'is_night_shift' => true,
                'is_rotational' => true,
                'is_active' => true,
            ]
        );

        $shiftGeneral = EmployeeShift::firstOrCreate(
            ['code' => 'GEN'],
            [
                'name' => 'General Day Shift',
                'start_time' => '09:30:00',
                'end_time' => '18:30:00',
                'grace_period_minutes' => 15,
                'half_day_threshold_minutes' => 240,
                'is_night_shift' => false,
                'is_rotational' => false,
                'is_active' => true,
            ]
        );

        // 5. Leave Types
        $leaveCl = EmployeeLeaveType::firstOrCreate(
            ['code' => 'CL'],
            [
                'name' => 'Casual Leave',
                'annual_quota' => 12,
                'is_paid' => true,
                'carry_forward_max' => 0,
                'is_active' => true,
            ]
        );

        $leaveSl = EmployeeLeaveType::firstOrCreate(
            ['code' => 'SL'],
            [
                'name' => 'Sick Leave',
                'annual_quota' => 10,
                'is_paid' => true,
                'carry_forward_max' => 0,
                'is_active' => true,
            ]
        );

        $leaveEl = EmployeeLeaveType::firstOrCreate(
            ['code' => 'EL'],
            [
                'name' => 'Earned Leave',
                'annual_quota' => 15,
                'is_paid' => true,
                'carry_forward_max' => 10,
                'is_active' => true,
            ]
        );

        $leaveComp = EmployeeLeaveType::firstOrCreate(
            ['code' => 'COMP'],
            [
                'name' => 'Compensatory Off',
                'annual_quota' => 6,
                'is_paid' => true,
                'carry_forward_max' => 0,
                'is_active' => true,
            ]
        );

        $leaveLop = EmployeeLeaveType::firstOrCreate(
            ['code' => 'LOP'],
            [
                'name' => 'Loss of Pay',
                'annual_quota' => 30,
                'is_paid' => false,
                'carry_forward_max' => 0,
                'is_active' => true,
            ]
        );

        // 6. Holiday Calendar (2026)
        $holidays = [
            ['title' => 'Republic Day', 'date' => '2026-01-26', 'is_optional' => false],
            ['title' => 'Tamil New Year / Dr. Ambedkar Jayanti', 'date' => '2026-04-14', 'is_optional' => false],
            ['title' => 'May Day', 'date' => '2026-05-01', 'is_optional' => false],
            ['title' => 'Independence Day', 'date' => '2026-08-15', 'is_optional' => false],
            ['title' => 'Gandhi Jayanti', 'date' => '2026-10-02', 'is_optional' => false],
            ['title' => 'Vijayadasami / Dussehra', 'date' => '2026-10-20', 'is_optional' => false],
            ['title' => 'Deepavali / Diwali', 'date' => '2026-11-08', 'is_optional' => false],
            ['title' => 'Christmas Day', 'date' => '2026-12-25', 'is_optional' => false],
        ];

        foreach ($holidays as $h) {
            HolidayCalendar::firstOrCreate(
                ['date' => $h['date']],
                [
                    'title' => $h['title'],
                    'applicable_venue_id' => null, // all venues
                    'is_optional' => $h['is_optional'],
                ]
            );
        }

        // 7. Demo Staff Accounts & Employee Profiles
        $employeesData = [
            [
                'email' => 'emp.ramesh@haraan.com',
                'name' => 'Ramesh Kumar',
                'phone' => '+91 98765 43210',
                'code' => 'EMP-2026-001',
                'dept' => $deptFd->id,
                'desig' => $desDeskExec->id,
                'salary' => 28000.00,
                'gender' => 'MALE',
                'blood' => 'O+',
                'address' => '42 South Beach Road, Chennai 600004',
            ],
            [
                'email' => 'emp.suresh@haraan.com',
                'name' => 'Suresh Prabhu',
                'phone' => '+91 98765 43211',
                'code' => 'EMP-2026-002',
                'dept' => $deptGmt->id,
                'desig' => $desGroundCrew->id,
                'salary' => 22000.00,
                'gender' => 'MALE',
                'blood' => 'B+',
                'address' => '15 Velachery Main Road, Chennai 600042',
            ],
            [
                'email' => 'emp.anita@haraan.com',
                'name' => 'Anita Roy',
                'phone' => '+91 98765 43212',
                'code' => 'EMP-2026-003',
                'dept' => $deptOps->id,
                'desig' => $desOpsLead->id,
                'salary' => 38000.00,
                'gender' => 'FEMALE',
                'blood' => 'A+',
                'address' => '88 Anna Nagar West, Chennai 600040',
            ],
        ];

        $currentYear = (int) Carbon::now()->format('Y');

        foreach ($employeesData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make('Password@123'),
                    'role' => 'EMPLOYEE',
                    'status' => 'ACTIVE',
                    'email_verified_at' => now(),
                ]
            );

            $profile = EmployeeProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_code' => $data['code'],
                    'department_id' => $data['dept'],
                    'designation_id' => $data['desig'],
                    'partner_id' => $partner->id,
                    'venue_id' => $venue->id,
                    'joining_date' => Carbon::parse('2025-06-01'),
                    'employment_type' => 'FULL_TIME',
                    'employment_status' => 'ACTIVE',
                    'gender' => $data['gender'],
                    'blood_group' => $data['blood'],
                    'residential_address' => $data['address'],
                    'base_salary' => $data['salary'],
                    'geofence_radius_meters' => 250,
                    'bank_name' => 'HDFC Bank',
                    'bank_account_no' => '50100458923412',
                    'bank_ifsc' => 'HDFC0001234',
                    'pan_number' => 'ABCDE1234F',
                    'aadhaar_number' => '123456789012',
                ]
            );

            // Seed Leave Balances for CL, SL, EL
            EmployeeLeaveBalance::firstOrCreate(
                ['employee_profile_id' => $profile->id, 'employee_leave_type_id' => $leaveCl->id, 'year' => $currentYear],
                ['allocated_days' => 12, 'used_days' => 2, 'pending_days' => 0, 'remaining_days' => 10]
            );
            EmployeeLeaveBalance::firstOrCreate(
                ['employee_profile_id' => $profile->id, 'employee_leave_type_id' => $leaveSl->id, 'year' => $currentYear],
                ['allocated_days' => 10, 'used_days' => 1, 'pending_days' => 0, 'remaining_days' => 9]
            );
            EmployeeLeaveBalance::firstOrCreate(
                ['employee_profile_id' => $profile->id, 'employee_leave_type_id' => $leaveEl->id, 'year' => $currentYear],
                ['allocated_days' => 15, 'used_days' => 0, 'pending_days' => 0, 'remaining_days' => 15]
            );

            // Seed Shift Rosters for this week
            $today = Carbon::today();
            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                $rosterDate = $today->copy()->addDays($dayOffset);

                $existingRoster = EmployeeShiftRoster::where('employee_profile_id', $profile->id)
                    ->whereDate('roster_date', $rosterDate->toDateString())
                    ->first();

                if (! $existingRoster) {
                    EmployeeShiftRoster::create([
                        'employee_profile_id' => $profile->id,
                        'roster_date' => $rosterDate->toDateString(),
                        'employee_shift_id' => $shiftMorning->id,
                        'venue_id' => $venue->id,
                        'status' => 'scheduled',
                        'notes' => 'Regular morning floor rotation',
                    ]);
                }
            }
        }

        // 8. Company Announcements
        HrmsAnnouncement::firstOrCreate(
            ['title' => 'Welcome to HARAAN Employee Self-Service Portal'],
            [
                'body' => 'We are thrilled to unveil the new HARAAN Employee Self-Service portal. Check your live roster, clock in with geo-fenced GPS check-in, request leaves, track daily tasks, and view instant salary payslips.',
                'priority' => 'high',
                'audience' => 'all',
                'published_at' => now(),
                'is_active' => true,
            ]
        );

        HrmsAnnouncement::firstOrCreate(
            ['title' => 'Q1 Performance Appraisals & Goal Setting'],
            [
                'body' => 'Quarterly KPI and performance review cycles are now open. Partner managers and supervisors will be conducting one-on-one reviews through the new digital appraisal scorecard.',
                'priority' => 'normal',
                'audience' => 'all',
                'published_at' => now(),
                'is_active' => true,
            ]
        );

        // 9. Demo Floor Tasks
        $rameshProfile = EmployeeProfile::where('employee_code', 'EMP-2026-001')->first();
        $sureshProfile = EmployeeProfile::where('employee_code', 'EMP-2026-002')->first();
        $anitaProfile = EmployeeProfile::where('employee_code', 'EMP-2026-003')->first();

        if ($rameshProfile) {
            // Set Suresh and Anita to report to Ramesh (making Ramesh the Shift Supervisor / Lead)
            if ($sureshProfile) {
                $sureshProfile->update(['reporting_manager_id' => $rameshProfile->user_id]);

                // Seed a pending attendance regularisation from Suresh
                \App\Models\Hrms\EmployeeAttendanceRegularisation::firstOrCreate(
                    [
                        'employee_profile_id' => $sureshProfile->id,
                        'date' => Carbon::yesterday()->toDateString(),
                    ],
                    [
                        'requested_clock_in_at' => Carbon::yesterday()->setTime(8, 30),
                        'requested_clock_out_at' => Carbon::yesterday()->setTime(17, 00),
                        'reason_category' => 'gps_drift',
                        'reason' => 'GPS location failed to sync due to stadium concrete overhang at West Gate.',
                        'status' => 'pending',
                    ]
                );
            }

            if ($anitaProfile) {
                $anitaProfile->update(['reporting_manager_id' => $rameshProfile->user_id]);

                // Seed a pending leave request from Anita
                \App\Models\Hrms\EmployeeLeaveRequest::firstOrCreate(
                    [
                        'employee_profile_id' => $anitaProfile->id,
                        'start_date' => Carbon::today()->addDays(3)->toDateString(),
                    ],
                    [
                        'employee_leave_type_id' => $leaveCl->id,
                        'end_date' => Carbon::today()->addDays(4)->toDateString(),
                        'total_days' => 2,
                        'reason' => 'Attending family wedding in Coimbatore.',
                        'status' => 'pending',
                    ]
                );
            }

            \App\Models\Hrms\EmployeeTask::firstOrCreate(
                ['title' => 'Pitch 1 Morning Floodlight & Turf Inspection', 'employee_profile_id' => $rameshProfile->id],
                [
                    'description' => 'Perform pre-opening safety sweep of Turf 1 and check all high-mast LED floodlights.',
                    'assigned_by' => $partner->id,
                    'venue_id' => $venue->id,
                    'priority' => 'high',
                    'status' => 'in_progress',
                    'progress_percent' => 65,
                    'checklist_items' => [
                        ['title' => 'Inspect turf surface for debris and sprinkler heads', 'completed' => true],
                        ['title' => 'Test floodlight bank A & B controllers', 'completed' => true],
                        ['title' => 'Calibrate digital booking tablet at reception', 'completed' => false],
                    ],
                    'due_date' => Carbon::today(),
                ]
            );

            \App\Models\Hrms\EmployeeTask::firstOrCreate(
                ['title' => 'Front Desk Check-in Device Calibration', 'employee_profile_id' => $rameshProfile->id],
                [
                    'description' => 'Recharge RFID scanners and verify barcode readers for smooth customer entry.',
                    'assigned_by' => $partner->id,
                    'venue_id' => $venue->id,
                    'priority' => 'urgent',
                    'status' => 'pending',
                    'progress_percent' => 20,
                    'checklist_items' => [
                        ['title' => 'Check battery health of thermal receipt printer', 'completed' => true],
                        ['title' => 'Re-authenticate kiosk POS terminal', 'completed' => false],
                    ],
                    'due_date' => Carbon::today(),
                ]
            );

            \App\Models\Hrms\EmployeeTask::firstOrCreate(
                ['title' => 'Weekly Turf Ingress Audit', 'employee_profile_id' => $rameshProfile->id],
                [
                    'description' => 'Audit peak weekend attendance logs and check-in counts against online slot reservations.',
                    'assigned_by' => $partner->id,
                    'venue_id' => $venue->id,
                    'priority' => 'medium',
                    'status' => 'completed',
                    'progress_percent' => 100,
                    'checklist_items' => [
                        ['title' => 'Reconcile gate counts with booking roster', 'completed' => true],
                    ],
                    'due_date' => Carbon::yesterday(),
                    'completed_at' => Carbon::yesterday()->setHour(17),
                ]
            );

            // 10. Demo Live Attendance Records
            if (! \App\Models\Hrms\EmployeeAttendance::where('employee_profile_id', $rameshProfile->id)->whereDate('date', Carbon::today()->toDateString())->exists()) {
                \App\Models\Hrms\EmployeeAttendance::create([
                    'employee_profile_id' => $rameshProfile->id,
                    'date' => Carbon::today()->toDateString(),
                    'employee_shift_id' => $shiftMorning->id,
                    'clock_in_at' => Carbon::today()->setHour(8)->setMinute(55),
                    'clock_in_latitude' => 13.08272,
                    'clock_in_longitude' => 80.27071,
                    'clock_in_distance_meters' => 14.2,
                    'clock_in_geofence_status' => 'inside',
                    'clock_in_method' => 'gps',
                    'total_work_minutes' => 380,
                    'total_break_minutes' => 30,
                    'status' => 'present',
                    'break_logs' => [
                        [
                            'start' => Carbon::today()->setHour(13)->setMinute(0)->toDateTimeString(),
                            'end' => Carbon::today()->setHour(13)->setMinute(30)->toDateTimeString(),
                            'duration_minutes' => 30,
                        ]
                    ],
                    'is_verified' => true,
                ]);
            }

            if (! \App\Models\Hrms\EmployeeAttendance::where('employee_profile_id', $rameshProfile->id)->whereDate('date', Carbon::yesterday()->toDateString())->exists()) {
                \App\Models\Hrms\EmployeeAttendance::create([
                    'employee_profile_id' => $rameshProfile->id,
                    'date' => Carbon::yesterday()->toDateString(),
                    'employee_shift_id' => $shiftMorning->id,
                    'clock_in_at' => Carbon::yesterday()->setHour(8)->setMinute(50),
                    'clock_in_latitude' => 13.08271,
                    'clock_in_longitude' => 80.27069,
                    'clock_in_distance_meters' => 10.5,
                    'clock_in_geofence_status' => 'inside',
                    'clock_in_method' => 'gps',
                    'clock_out_at' => Carbon::yesterday()->setHour(17)->setMinute(35),
                    'clock_out_latitude' => 13.08270,
                    'clock_out_longitude' => 80.27070,
                    'clock_out_distance_meters' => 8.1,
                    'clock_out_geofence_status' => 'inside',
                    'clock_out_method' => 'gps',
                    'total_work_minutes' => 495,
                    'total_break_minutes' => 40,
                    'status' => 'present',
                    'is_verified' => true,
                ]);
            }

            // 11. Demo Leave Requests
            $pastLeaveDate = Carbon::now()->subDays(14)->toDateString();
            if (! \App\Models\Hrms\EmployeeLeaveRequest::where('employee_profile_id', $rameshProfile->id)->whereDate('start_date', $pastLeaveDate)->exists()) {
                \App\Models\Hrms\EmployeeLeaveRequest::create([
                    'employee_profile_id' => $rameshProfile->id,
                    'employee_leave_type_id' => $leaveCl->id,
                    'start_date' => $pastLeaveDate,
                    'end_date' => Carbon::now()->subDays(13)->toDateString(),
                    'total_days' => 2.0,
                    'reason' => 'Family function and travel to native hometown.',
                    'status' => 'approved',
                    'approver_id' => $partner->id,
                    'approver_notes' => 'Approved. Roster covered by shift swap with Anita.',
                    'approved_at' => Carbon::now()->subDays(15),
                ]);
            }

            $futureLeaveDate = Carbon::now()->addDays(5)->toDateString();
            if (! \App\Models\Hrms\EmployeeLeaveRequest::where('employee_profile_id', $rameshProfile->id)->whereDate('start_date', $futureLeaveDate)->exists()) {
                \App\Models\Hrms\EmployeeLeaveRequest::create([
                    'employee_profile_id' => $rameshProfile->id,
                    'employee_leave_type_id' => $leaveSl->id,
                    'start_date' => $futureLeaveDate,
                    'end_date' => $futureLeaveDate,
                    'total_days' => 1.0,
                    'reason' => 'Scheduled medical health checkup and vision test.',
                    'status' => 'pending',
                    'approver_id' => null,
                ]);
            }

            // 12. Demo Payroll Records
            \App\Models\Hrms\EmployeePayroll::firstOrCreate(
                ['employee_profile_id' => $rameshProfile->id, 'payroll_month' => '2026-08'],
                [
                    'payment_date' => Carbon::parse('2026-09-01'),
                    'working_days' => 26,
                    'present_days' => 24.0,
                    'paid_leave_days' => 2.0,
                    'unpaid_leave_days' => 0.0,
                    'absent_days' => 0.0,
                    'basic_salary' => 14000.00,
                    'hra' => 7000.00,
                    'special_allowance' => 7000.00,
                    'overtime_amount' => 1250.00,
                    'performance_bonus' => 2000.00,
                    'gross_earnings' => 31250.00,
                    'pf_deduction' => 1680.00,
                    'esi_deduction' => 234.38,
                    'professional_tax' => 200.00,
                    'tds_deduction' => 0.00,
                    'other_deductions' => 0.00,
                    'total_deductions' => 2114.38,
                    'net_salary' => 29135.62,
                    'status' => 'paid',
                    'payment_method' => 'bank_transfer',
                    'transaction_reference' => 'TXN-HDFC-98420194',
                    'payslip_number' => 'PAY-2026-08-001',
                ]
            );

            \App\Models\Hrms\EmployeePayroll::firstOrCreate(
                ['employee_profile_id' => $rameshProfile->id, 'payroll_month' => '2026-07'],
                [
                    'payment_date' => Carbon::parse('2026-08-01'),
                    'working_days' => 27,
                    'present_days' => 27.0,
                    'paid_leave_days' => 0.0,
                    'unpaid_leave_days' => 0.0,
                    'absent_days' => 0.0,
                    'basic_salary' => 14000.00,
                    'hra' => 7000.00,
                    'special_allowance' => 7000.00,
                    'overtime_amount' => 800.00,
                    'performance_bonus' => 1500.00,
                    'gross_earnings' => 30300.00,
                    'pf_deduction' => 1680.00,
                    'esi_deduction' => 227.25,
                    'professional_tax' => 200.00,
                    'tds_deduction' => 0.00,
                    'other_deductions' => 0.00,
                    'total_deductions' => 2107.25,
                    'net_salary' => 28192.75,
                    'status' => 'paid',
                    'payment_method' => 'bank_transfer',
                    'transaction_reference' => 'TXN-HDFC-88210381',
                    'payslip_number' => 'PAY-2026-07-001',
                ]
            );

            // 13. Demo Performance Scorecard
            \App\Models\Hrms\EmployeeKpi::firstOrCreate(
                ['employee_profile_id' => $rameshProfile->id, 'period' => '2026-Q2'],
                [
                    'reviewer_id' => $partner->id,
                    'punctuality_rating' => 4.9,
                    'task_completion_rating' => 4.8,
                    'customer_service_rating' => 5.0,
                    'teamwork_rating' => 4.7,
                    'overall_score' => 4.9,
                    'achievements' => 'Demonstrated flawless attendance consistency (99.4% on-time). Commended by tournament organizers for prompt guest check-in handling.',
                    'areas_for_improvement' => 'Participate in level-2 first-aid facility certification.',
                    'manager_feedback' => 'Ramesh is an exemplar front desk leader with top-tier punctuality and guest feedback.',
                ]
            );
        }

        if ($anitaProfile) {
            \App\Models\Hrms\EmployeePayroll::firstOrCreate(
                ['employee_profile_id' => $anitaProfile->id, 'payroll_month' => '2026-08'],
                [
                    'payment_date' => Carbon::parse('2026-09-01'),
                    'working_days' => 26,
                    'present_days' => 26.0,
                    'paid_leave_days' => 0.0,
                    'unpaid_leave_days' => 0.0,
                    'absent_days' => 0.0,
                    'basic_salary' => 19000.00,
                    'hra' => 9500.00,
                    'special_allowance' => 9500.00,
                    'overtime_amount' => 2400.00,
                    'performance_bonus' => 3000.00,
                    'gross_earnings' => 43400.00,
                    'pf_deduction' => 2280.00,
                    'esi_deduction' => 325.50,
                    'professional_tax' => 200.00,
                    'tds_deduction' => 1200.00,
                    'other_deductions' => 0.00,
                    'total_deductions' => 4005.50,
                    'net_salary' => 39394.50,
                    'status' => 'paid',
                    'payment_method' => 'bank_transfer',
                    'transaction_reference' => 'TXN-HDFC-98420195',
                    'payslip_number' => 'PAY-2026-08-002',
                ]
            );
        }
    }
}
