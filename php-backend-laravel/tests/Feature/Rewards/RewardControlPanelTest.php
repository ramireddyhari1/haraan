<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Filament\Pages\PlatformRulesPage;
use App\Filament\Resources\Notifications\NotificationResource;
use App\Filament\Resources\Rewards\BadgeDefinitionResource;
use App\Filament\Resources\Rewards\Pages\EditRewardCodePool;
use App\Filament\Resources\Rewards\Pages\EditRewardProgram;
use App\Filament\Resources\Rewards\Pages\ListRewardCodePools;
use App\Filament\Resources\Rewards\Pages\ListRewardPrograms;
use App\Filament\Resources\Rewards\Pages\ManageBadgeDefinitions;
use App\Filament\Resources\Rewards\Pages\ManageRewardGrants;
use App\Filament\Resources\Rewards\Pages\ManageRewardSponsors;
use App\Filament\Resources\Rewards\RelationManagers\RewardCodesRelationManager;
use App\Filament\Resources\Rewards\RelationManagers\RewardRulesRelationManager;
use App\Filament\Resources\Rewards\RewardCodePoolResource;
use App\Filament\Resources\Rewards\RewardGrantResource;
use App\Filament\Resources\Rewards\RewardProgramResource;
use App\Filament\Resources\Rewards\RewardSponsorResource;
use App\Models\AdminAction;
use App\Models\BonusXpEntry;
use App\Models\Notification;
use App\Models\RewardCode;
use App\Models\RewardGrant;
use App\Models\RewardRule;
use App\Models\User;
use App\Support\PlatformRules;
use App\Support\Rewards\RewardTypes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /control → Rewards: who can reach it, that rules are configured through typed fields, that code
 * pools never leak codes into the page or the audit log, and that every sensitive change is audited.
 */
final class RewardControlPanelTest extends TestCase
{
    use RefreshDatabase;
    use RewardFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function staff(string $role): User
    {
        return User::create([
            'name' => $role.' staff', 'email' => strtolower($role).'-'.uniqid().'@haraan.test',
            'password' => bcrypt('secret123'), 'role' => $role, 'status' => 'active',
        ]);
    }

    public function test_access_is_marketing_and_admins_only(): void
    {
        $resources = [RewardProgramResource::class, RewardGrantResource::class, RewardSponsorResource::class, RewardCodePoolResource::class, BadgeDefinitionResource::class];

        foreach (['ADMIN', 'MARKETING'] as $role) {
            $this->actingAs($this->staff($role));
            foreach ($resources as $r) {
                self::assertTrue($r::canAccess(), "{$role} → {$r}");
            }
        }
        foreach (['FINANCE', 'OPS', 'PARTNER', 'USER'] as $role) {
            $this->actingAs($this->staff($role));
            foreach ($resources as $r) {
                self::assertFalse($r::canAccess(), "{$role} → {$r}");
            }
        }
        self::assertFalse(RewardGrantResource::canCreate(), 'the ledger is never hand-written');
    }

    public function test_pages_render(): void
    {
        $this->actingAs($this->staff('ADMIN'));
        $program = $this->sponsoredProgram();
        $pool = $this->pool(['SECRET-CODE-0001']);

        Livewire::test(ListRewardPrograms::class)->assertOk()->assertSee('PayFast cashback');
        Livewire::test(EditRewardProgram::class, ['record' => $program->getRouteKey()])->assertOk();
        Livewire::test(ManageRewardSponsors::class)->assertOk()->assertSee('PayFast');
        Livewire::test(ListRewardCodePools::class)->assertOk();
        Livewire::test(ManageBadgeDefinitions::class)->assertOk()->assertSee('Half Century');
        Livewire::test(ManageRewardGrants::class)->assertOk();
        Livewire::test(PlatformRulesPage::class)->assertOk()->assertSee('Post-match rewards');

        Livewire::test(RewardCodesRelationManager::class, ['ownerRecord' => $pool, 'pageClass' => EditRewardCodePool::class])
            ->assertOk()->assertSee('••••0001')->assertDontSee('SECRET-CODE-0001');
    }

    public function test_rules_are_saved_through_typed_fields_and_audited(): void
    {
        $this->actingAs($this->staff('MARKETING'));
        $program = $this->program();

        Livewire::test(RewardRulesRelationManager::class, ['ownerRecord' => $program, 'pageClass' => EditRewardProgram::class])
            ->callTableAction('create', data: [
                'name' => 'Winners coupon', 'trigger' => RewardRule::TRIGGER_SETTLED, 'reward_type' => RewardTypes::HARAAN_COUPON,
                'unlock_method' => 'auto', 'is_active' => true, 'sort' => 1,
                'p_discount_type' => 'percent', 'p_discount' => 15, 'p_max_discount' => 100, 'p_scope' => 'event', 'p_valid_days' => 20,
                'c_result' => 'won', 'c_min_trust' => 'medium',
            ])
            ->assertHasNoTableActionErrors();

        $rule = RewardRule::query()->sole();
        self::assertSame(['discount_type' => 'percent', 'discount' => 15, 'max_discount' => 100, 'min_order' => null, 'scope' => 'event', 'valid_days' => 20], $rule->payload);
        self::assertSame(['result' => 'won', 'min_trust' => 'medium'], $rule->conditions);
        self::assertTrue(AdminAction::query()->where('action', 'reward_rule.created')->exists());

        // An invalid payload is refused, not stored.
        Livewire::test(RewardRulesRelationManager::class, ['ownerRecord' => $program, 'pageClass' => EditRewardProgram::class])
            ->callTableAction('create', data: [
                'name' => 'Bad link', 'trigger' => RewardRule::TRIGGER_COMPLETED, 'reward_type' => RewardTypes::OFFER_LINK,
                'unlock_method' => 'auto', 'p_url' => 'http://insecure.example', 'c_result' => 'any', 'c_min_trust' => 'low',
            ]);
        self::assertSame(1, RewardRule::query()->count());
    }

    public function test_going_live_and_pausing_are_audited(): void
    {
        $this->actingAs($this->staff('MARKETING'));
        $program = $this->program(['status' => 'draft']);

        Livewire::test(ListRewardPrograms::class)->callTableAction('goLive', $program);
        self::assertSame('live', $program->fresh()->status);
        Livewire::test(ListRewardPrograms::class)->callTableAction('pause', $program);
        self::assertSame('paused', $program->fresh()->status);

        self::assertTrue(AdminAction::query()->where('action', 'reward_program.published')->exists());
        self::assertTrue(AdminAction::query()->where('action', 'reward_program.paused')->exists());
    }

    public function test_code_imports_are_audited_by_count_and_never_log_a_code(): void
    {
        $this->actingAs($this->staff('MARKETING'));
        $pool = $this->pool([]);

        Livewire::test(RewardCodesRelationManager::class, ['ownerRecord' => $pool, 'pageClass' => EditRewardCodePool::class])
            ->callTableAction('import', data: ['codes' => "code\nALPHA-1111\nBETA-2222\nALPHA-1111\nbad code with spaces\n"]);

        self::assertSame(2, RewardCode::query()->count());
        $log = AdminAction::query()->where('action', 'reward_codes.imported')->sole();
        self::assertSame(2, $log->meta['added']);
        self::assertSame(1, $log->meta['duplicates']);
        self::assertSame(1, $log->meta['invalid']);

        $everything = AdminAction::query()->get()->toJson();
        self::assertStringNotContainsString('ALPHA-1111', $everything);
        self::assertStringNotContainsString('BETA-2222', $everything);
    }

    public function test_only_a_super_admin_can_reveal_a_code_and_the_reveal_is_audited(): void
    {
        $pool = $this->pool(['REVEAL-ME-42']);
        $code = RewardCode::query()->sole();

        $this->actingAs($this->staff('MARKETING'));
        Livewire::test(RewardCodesRelationManager::class, ['ownerRecord' => $pool, 'pageClass' => EditRewardCodePool::class])
            ->assertTableActionHidden('reveal', $code);

        $this->actingAs($this->staff('ADMIN'));
        Livewire::test(RewardCodesRelationManager::class, ['ownerRecord' => $pool, 'pageClass' => EditRewardCodePool::class])
            ->callTableAction('reveal', $code);

        $log = AdminAction::query()->where('action', 'reward_code.revealed')->sole();
        self::assertSame($code->id, $log->meta['code_id']);
        self::assertStringNotContainsString('REVEAL-ME-42', AdminAction::query()->get()->toJson());
    }

    public function test_revoking_a_reward_is_audited_and_reverses_its_bonus_xp(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 25]);
        $a = $this->player();
        $this->finishedMatch([$a], []);
        $grant = RewardGrant::query()->where('user_id', $a->id)->where('type', RewardTypes::BONUS_XP)->sole();

        $this->actingAs($this->staff('MARKETING'));
        Livewire::test(ManageRewardGrants::class)->callTableAction('revoke', $grant, ['reason' => 'Fake match']);

        self::assertSame(RewardGrant::REVOKED, $grant->fresh()->status);
        self::assertSame(0, BonusXpEntry::totalFor($a->id));
        $log = AdminAction::query()->where('action', 'reward_grant.revoked')->sole();
        self::assertSame('Fake match', $log->meta['reason']);
    }

    public function test_reward_rules_live_in_platform_rules_and_refuse_secrets(): void
    {
        self::assertSame('rewards', PlatformRules::sectionOf('rewards.admob_ad_unit_id'));
        self::assertSame('ops', PlatformRules::sectionOf('ops.rewards_disabled'));

        $this->expectException(\InvalidArgumentException::class);
        PlatformRules::assertNotSecret('rewards.admob_api_key');
    }

    public function test_an_invalid_ad_unit_id_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlatformRules::save(['rewards.admob_ad_unit_id' => 'not-an-ad-unit']);
    }

    public function test_the_notification_composer_only_lists_what_the_team_wrote(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 5]);
        $this->finishedMatch([$this->player()], []);
        self::assertSame(1, Notification::query()->where('source', 'rewards')->count());

        $this->actingAs($this->staff('ADMIN'));
        self::assertSame(0, NotificationResource::getEloquentQuery()->count());
    }
}
