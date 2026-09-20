<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\AdminAction;
use App\Support\PlatformRules;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

/**
 * The form + save logic shared by /control → Platform rules and → Operations. Both render
 * straight from the PlatformRules registry, so a rule added there appears here with its label,
 * bounds and help — nothing to wire by hand.
 *
 * Authorisation is per SECTION (PlatformRules::SECTIONS[*]['workspace']) and enforced on save,
 * not only by disabling fields: a section the user can't manage is never written, whatever the
 * request carries. Every save writes one audit entry with the exact before → after of each
 * changed rule.
 */
trait EditsPlatformRules
{
    public ?array $data = [];

    /** @return list<string> the registry sections this page edits */
    abstract protected function ruleSections(): array;

    public static function canManageSection(string $section): bool
    {
        $workspace = PlatformRules::SECTIONS[$section]['workspace'] ?? 'admin';

        return auth()->user()?->canManage($workspace) ?? false;
    }

    protected function fillRules(): void
    {
        $values = [];
        foreach ($this->ruleSections() as $section) {
            foreach (array_keys(PlatformRules::inSection($section)) as $key) {
                $values[self::field($key)] = PlatformRules::get($key);
            }
        }

        $this->form->fill($values);
    }

    /** @return list<Component> */
    protected function ruleSectionComponents(): array
    {
        $out = [];
        foreach ($this->ruleSections() as $section) {
            $meta = PlatformRules::SECTIONS[$section];
            $editable = self::canManageSection($section);
            $fields = [];

            foreach (PlatformRules::inSection($section) as $key => $def) {
                $fields[] = $this->ruleField($key, $def)->disabled(! $editable);
            }

            $out[] = Section::make($meta['label'])
                ->description($meta['description'].($editable ? '' : ' You can view these but not change them.'))
                ->columns(2)
                ->schema($fields);
        }

        return $out;
    }

    /** @param  array<string, mixed>  $def */
    private function ruleField(string $key, array $def): Component
    {
        $name = self::field($key);
        $default = PlatformRules::defaultOf($key);
        $defaultLabel = match (true) {
            is_bool($default) => $default ? 'on' : 'off',
            $default === '' => 'empty',
            $def['type'] === PlatformRules::TYPE_SELECT => (string) ($def['options'][$default] ?? $default),
            default => (string) $default,
        };
        $help = trim(($def['help'] ?? '').' Default: '.$defaultLabel.'.');

        $field = match ($def['type']) {
            PlatformRules::TYPE_BOOL => Toggle::make($name)->onColor(($def['danger'] ?? false) ? 'danger' : 'primary'),
            PlatformRules::TYPE_SELECT => Select::make($name)->options($def['options'])->native(false)->required(),
            PlatformRules::TYPE_INT => TextInput::make($name)->numeric()->integer()->required()
                ->minValue($def['min'] ?? null)->maxValue($def['max'] ?? null),
            PlatformRules::TYPE_FLOAT => TextInput::make($name)->numeric()->required()->step('any')
                ->minValue($def['min'] ?? null)->maxValue($def['max'] ?? null),
            default => TextInput::make($name)->maxLength($def['max'] ?? 255)
                ->required(! is_array($def['default']) && (string) $def['default'] !== '')
                ->regex($def['pattern'] ?? null),
        };

        return $field->label($def['label'])->helperText($help)
            ->columnSpan($def['type'] === PlatformRules::TYPE_TEXT && ($def['max'] ?? 0) > 60 ? 'full' : 1);
    }

    /** Save the sections this user may manage. Returns what changed. */
    protected function saveRules(string $auditAction): array
    {
        $state = $this->form->getState();
        $values = [];

        foreach ($this->ruleSections() as $section) {
            if (! self::canManageSection($section)) {
                continue;
            }
            foreach (array_keys(PlatformRules::inSection($section)) as $key) {
                if (array_key_exists(self::field($key), $state)) {
                    $values[$key] = $state[self::field($key)];
                }
            }
        }

        try {
            $changes = PlatformRules::save($values);
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title('Not saved')->body($e->getMessage())->danger()->send();

            return [];
        }

        if ($changes === []) {
            Notification::make()->title('Nothing changed')->send();

            return [];
        }

        AdminAction::log($auditAction, ['changes' => $changes]);
        Notification::make()
            ->title(count($changes) === 1 ? '1 rule updated' : count($changes).' rules updated')
            ->body('Live now on the server. Apps pick it up within seconds.')
            ->success()
            ->send();

        $this->fillRules();

        return $changes;
    }

    /** Form state keys can't contain dots (they'd nest), so `ops.pause_all_bookings` → `ops__pause_all_bookings`. */
    protected static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
