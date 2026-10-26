<?php

namespace App\Filament\Pages;

use App\Models\AdvertImages;
use App\Services\AdvertQrCodeService;
use App\Services\ImageEncoderService;
use App\Support\AdminAccess;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ImageStudio extends Page
{
    use \App\Filament\Support\NotifiesWorkflowErrors;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Campaigns';

    protected static ?string $navigationLabel = 'Image layout studio';

    protected static string $view = 'filament.pages.image-studio';

    public ?array $data = [];

    public ?string $previewUrl = null;

    public static function canAccess(): bool
    {
        return AdminAccess::allows('campaigns');
    }

    public function mount(): void
    {
        $this->form->fill(['layout' => 'smart_overlay']);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            \App\Filament\Support\Fields::user()->required()->helperText('The image will be linked to this member and the selected advert.'),
            \App\Filament\Support\Fields::advert()->required(),
            F\Select::make('layout')->options(array_combine(ImageEncoderService::supportedLayouts(), [
                'Story · personal image first', 'Balanced · equal space', 'Advert focus', 'Smart overlay · transparent advert', 'Floating advert',
            ]))->required(),
            F\FileUpload::make('image')->image()->acceptedFileTypes(['image/jpeg', 'image/png'])->maxSize(10240)->storeFiles(false)->required()->label('Member’s image'),
        ])->columns(2)->statePath('data');
    }

    public function generate(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $member = \App\Models\User::findOrFail($data['user_id']);
        $advert = AdvertImages::findOrFail($data['advert_id']);
        $source = public_path('storage/'.ltrim((string) $advert->image_path, '/'));
        if (! is_file($source)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.advert_id' => 'The advert image is missing from storage.']);
        }
        $codes = app(AdvertQrCodeService::class);
        $result = app(ImageEncoderService::class)->encode(
            $data['image']->getRealPath(), $codes->issue($member, $advert), $source,
            visibleProofCode: $codes->visibleCodeFor($member, $advert), layout: $data['layout']
        );
        $this->previewUrl = asset('storage/image_ads/encoded/'.$result['filename']);
    }
}
