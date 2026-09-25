<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminExportStreamingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Operator',
            'email' => 'admin.export@example.com',
            'password' => Hash::make('secret-password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    private function user(string $name, string $email, string $phone): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('secret-password'),
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_users_csv_export_returns_streamed_csv_and_logs_audit_action(): void
    {
        $admin = $this->admin();
        $user = $this->user('Jane Doe', 'jane.doe@example.com', '+919876543210');

        $response = $this->actingAs($admin)->get('/admin/export/users');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="users.csv"', (string) $response->headers->get('Content-Disposition'));

        // Capture streamed response content
        $content = $response->streamedContent();
        $this->assertStringContainsString('id,name,email,phone,role,status,created_at', $content);
        $this->assertStringContainsString('Jane Doe', $content);
        $this->assertStringContainsString('jane.doe@example.com', $content);
        $this->assertStringContainsString('+919876543210', $content);

        $this->assertDatabaseHas('admin_actions', [
            'action' => 'export.users',
        ]);
    }

    public function test_bookings_csv_export_returns_streamed_csv_and_logs_audit_action(): void
    {
        $admin = $this->admin();
        $customer = $this->user('Customer One', 'customer1@example.com', '+919111111111');

        $partner = $this->user('Partner Org', 'partner@example.com', '+919222222222');
        $partner->role = 'PARTNER';
        $partner->save();

        $event = new Event();
        $event->partner_id = $partner->id;
        $event->title = 'Championship Match';
        $event->category = 'Sports';
        $event->location = 'Stadium';
        $event->venue = 'Main Arena';
        $event->date = now()->addDays(2);
        $event->time = '18:00';
        $event->price = 500;
        $event->total_slots = 100;
        $event->available_slots = 100;
        $event->status = 'published';
        $event->save();

        $booking = Booking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'booking_type' => 'event',
            'quantity' => 2,
            'total_amount' => 1500.00,
            'status' => 'CONFIRMED',
        ]);

        $response = $this->actingAs($admin)->get('/admin/export/bookings');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="bookings.csv"', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('id,user_id,user_name,event_id,event_title,quantity,total_amount,status,created_at', $content);
        $this->assertStringContainsString((string) $booking->id, $content);
        $this->assertStringContainsString('Customer One', $content);
        $this->assertStringContainsString('Championship Match', $content);

        $this->assertDatabaseHas('admin_actions', [
            'action' => 'export.bookings',
        ]);
    }
}
