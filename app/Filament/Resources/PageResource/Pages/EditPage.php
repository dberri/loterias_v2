<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Enums\PageStatus;
use App\Filament\Resources\PageResource;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;
use Z3d0X\FilamentFabricator\Resources\PageResource\Pages\EditPage as VendorEditPage;

class EditPage extends VendorEditPage
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return array_merge(parent::getHeaderActions(), [
            Action::make('publish')
                ->label('Publish')
                ->color('success')
                ->requiresConfirmation()
                ->action('publish'),
        ]);
    }

    public function publish(): void
    {
        $page = $this->getRecord();

        if ($page->status !== PageStatus::Generated) {
            throw ValidationException::withMessages([
                'status' => 'Only generated pages can be published.',
            ]);
        }

        $page->update([
            'status' => PageStatus::Published->value,
        ]);

    }
}
