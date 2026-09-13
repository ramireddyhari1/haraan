<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Cities;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CitiesManagerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin@haraan.test',
            'password' => bcrypt('secret123'),
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
    }

    private function partner(): User
    {
        return User::create([
            'name' => 'Partner User',
            'email' => 'partner@haraan.test',
            'password' => bcrypt('secret123'),
            'role' => 'PARTNER',
            'status' => 'active',
        ]);
    }

    public function test_unauthorized_user_cannot_access_cities(): void
    {
        $this->actingAs($this->partner());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->assertFalse(Cities::canAccess());
    }

    public function test_superadmin_can_access_and_render_cities_page(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->assertTrue(Cities::canAccess());

        Livewire::test(Cities::class)
            ->assertOk()
            ->assertSee('Directory Actions')
            ->assertSee('Add City Manually');
    }

    public function test_it_saves_valid_cities_json(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $jsonPayload = json_encode([
            ['id' => 'bengaluru', 'name' => 'Bengaluru', 'country' => 'India', 'popular' => true],
            ['id' => 'hyderabad', 'name' => 'Hyderabad', 'country' => 'India', 'popular' => false],
        ]);

        Livewire::test(Cities::class)
            ->fillForm(['cities_json' => $jsonPayload])
            ->call('save')
            ->assertNotified('Cities saved');
    }

    public function test_it_rejects_invalid_json_on_save(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(Cities::class)
            ->set('data.cities_json', '{notjson')
            ->call('save')
            ->assertNotified('Invalid JSON');
    }
}