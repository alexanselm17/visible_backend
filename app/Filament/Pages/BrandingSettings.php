<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\LegalPageService;
use App\Support\AdminAccess;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BrandingSettings extends Page
{
    use \App\Filament\Support\NotifiesWorkflowErrors;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Branding & legal settings';

    protected static ?string $title = 'Branding & legal settings';

    protected static string $view = 'filament.pages.branding-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminAccess::allows('settings');
    }

    public function mount(): void
    {
        $this->form->fill(SiteSetting::current()->toArray());
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            F\Section::make('The name people recognize')->description('Shared by the public policy pages, admin workspace and branding on newly generated adverts.')->schema([
                F\TextInput::make('app_name')->label('App name (match the Play listing)')->required()->maxLength(100),
                F\TextInput::make('operator_name')->label('Legal operator / developer name')->maxLength(255)
                    ->helperText('Enter the real person or registered business responsible for the service. Required before publishing.'),
                F\FileUpload::make('logo_path')->label('Brand logo')->disk('admin_media')->directory('branding')
                    ->visibility('public')->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(2048)->maxFiles(1)->rules(['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'dimensions:max_width=4096,max_height=4096'])
                    ->helperText('PNG with transparency recommended. PNG, JPEG or WebP; up to 2 MB / 4096 px. No SVG. Existing generated adverts are not rewritten.')
                    ->columnSpanFull(),
            ])->columns(2),
            F\Section::make('Public contact details')->description('Use a monitored mailbox. Do not enter private staff-only contact information.')->schema([
                F\TextInput::make('support_email')->email()->maxLength(254)->helperText('Required before publishing.'),
                F\TextInput::make('privacy_email')->email()->maxLength(254)->helperText('Optional; otherwise support email is used.'),
                F\TextInput::make('support_phone')->maxLength(40),
                F\Textarea::make('postal_address')->rows(2)->maxLength(1000),
            ])->columns(2),
            F\Section::make('Privacy facts to confirm')->description('These are disclosures, not feature switches. Changing them does not change Android permissions or implement data deletion. Republish BOTH policies after changing these facts.')->schema([
                F\Select::make('accessibility_mode')->label('Does the shipped Android app use Accessibility Services?')->required()->live()->options([
                    'unconfirmed' => 'Not yet confirmed — blocks publication',
                    'none' => 'No — user uploads screenshots manually',
                    'whatsapp_status' => 'Yes — WhatsApp Status functionality',
                    'other' => 'Yes — another use',
                ]),
                F\Textarea::make('accessibility_disclosure')->label('Exact Accessibility Services disclosure')->rows(5)->maxLength(6000)
                    ->visible(fn (Get $get) => in_array($get('accessibility_mode'), ['whatsapp_status', 'other'], true))
                    ->helperText('State the screens/data accessed, actions performed, when it runs, purpose, data sent off-device and to whom, consent and how to disable it. Do not say it only reads your own app if it reads WhatsApp.'),
                F\Textarea::make('retention_policy')->label('Actual retention and deletion schedule')->rows(7)->maxLength(10000)
                    ->helperText('Required before publishing. Give durations/triggers for accounts, uploaded images/screenshots, financial/audit records, request records, logs and backups; explain deletion/anonymization, any lawful exceptions and the request turnaround. This must match your real operations.'),
            ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        app(LegalPageService::class)->saveSettings($this->form->getState());
        Notification::make()->title('Branding saved')->body('Republish the policies if the operator, contact or privacy facts changed.')->success()->send();
        $this->redirect(static::getUrl());
    }
}
