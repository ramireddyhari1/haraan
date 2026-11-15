<?php

namespace App\Filament\Resources\SupportThreads\Pages;

use App\Filament\Resources\SupportThreads\SupportThreadResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

class EditSupportThread extends EditRecord
{
    protected static string $resource = SupportThreadResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    /** Opening the conversation clears the "needs reply" badge. */
    protected function afterFill(): void
    {
        if ($this->record->admin_unread_count !== 0) {
            $this->record->forceFill(['admin_unread_count' => 0])->save();
        }
    }

    /** A reply moves the thread to "pending"; mirror that in the header and the form. */
    #[On('support-reply-sent')]
    public function syncAfterReply(): void
    {
        $this->record->refresh();
        $this->data['status'] = $this->record->status;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** Chat header: back to the inbox, who this is, and where it stands. */
    public function getHeader(): ?View
    {
        return view('filament.resources.support-threads.chat-header', [
            'thread' => $this->record->loadMissing(['user', 'assignee', 'category']),
            'backUrl' => SupportThreadResource::getUrl('index'),
        ]);
    }

    /** Two panes: the conversation on the left, the person and details on the right. */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->extraAttributes(['class' => 'stc-layout'])
                    ->schema([
                        Group::make([$this->getRelationManagersContentComponent()])
                            ->columnSpan(['default' => 1, 'lg' => 2])
                            ->extraAttributes(['class' => 'stc-main']),
                        Group::make([$this->getFormContentComponent()])
                            ->columnSpan(1)
                            ->extraAttributes(['class' => 'stc-side']),
                    ]),
            ]);
    }

    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()->label('Save details');
    }

    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction()];
    }
}
