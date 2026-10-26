<?php

namespace App\Filament\Pages;

use App\Http\Controllers\AdminFraudController;
use App\Models\Campaign;
use App\Support\AdminAccess;
use App\Support\AdminWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Pages\Page;

class FraudReviewQueue extends Page
{
    use \App\Filament\Support\NotifiesWorkflowErrors;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Trust & verification';

    protected static ?string $navigationLabel = 'Review suspicious activity';

    protected static ?string $title = 'Suspicious posting activity';

    protected static string $view = 'filament.pages.fraud-review-queue';

    public ?array $data = ['campaign_id' => null, 'min_views' => 10];

    public array $groups = [];

    public bool $scanned = false;

    public static function canAccess(): bool
    {
        return AdminAccess::allows('fraud');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            F\Select::make('campaign_id')->options(fn () => Campaign::pluck('name', 'id'))->searchable()->required()->label('Campaign'),
            F\TextInput::make('min_views')->integer()->minValue(0)->default(10)->required()->label('Minimum views'),
        ])->columns(2)->statePath('data');
    }

    public function scan(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $this->groups = $this->candidates($data['campaign_id'], (int) $data['min_views']);
        $this->scanned = true;
    }

    private function candidates(string $campaignId, int $minimum): array
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', ['min_views' => $minimum]);
        $response = app(AdminFraudController::class)->getFraudForCampaign($request, $campaignId);
        if ($response->getStatusCode() >= 400) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.campaign_id' => 'Could not load suspicious activity.']);
        }

        return $response->getData(true)['fraud_groups'];
    }

    public function reviewAction(): Action
    {
        return Action::make('review')->label('Review group')->form([
            F\Select::make('status')->options(['CONFIRMED' => 'Confirmed suspicious activity', 'REJECTED' => 'Not fraudulent'])->required(),
            F\Textarea::make('notes')->maxLength(2000)->required(),
        ])->action(function (array $arguments, array $data) {
            abort_unless(static::canAccess(), 403);
            $campaignId = $this->form->getState()['campaign_id'];
            $group = collect($this->candidates($campaignId, (int) $this->data['min_views']))
                ->first(fn ($candidate) => $candidate['matching_views_timestamp'] === $arguments['pattern'] && $candidate['advert_id'] === $arguments['advert']);
            abort_unless($group && $group['advert_id'] === $arguments['advert'], 422);

            AdminWorkflow::run(AdminFraudController::class, 'reviewFraudGroup', [
                'campaign_id' => $campaignId, 'advert_id' => $group['advert_id'],
                'pattern_key' => $group['matching_views_timestamp'], 'status' => $data['status'],
                'notes' => $data['notes'], 'reviewed_by' => auth()->id(), 'fraud_payload' => $group,
            ]);
            $this->scan();
        });
    }

    public function memberAction(): Action
    {
        return Action::make('member')->label('Member action')->requiresConfirmation()->form([
            F\Select::make('action')->options(['WARN' => 'Send warning', 'DEACTIVATE' => 'Deactivate account & revoke API sessions'])->required(),
            F\CheckboxList::make('user_ids')->label('Members')->options(function (array $arguments) {
                $groups = $this->candidates($this->form->getState()['campaign_id'], (int) $this->data['min_views']);
                $group = collect($groups)->first(fn ($g) => $g['advert_id'] === $arguments['advert'] && $g['matching_views_timestamp'] === $arguments['pattern']);

                return collect($group['details'] ?? [])->pluck('name', 'user_id')->all();
            })->required(),
        ])->action(function (array $arguments, array $data) {
            abort_unless(static::canAccess(), 403);
            $groups = $this->candidates($this->form->getState()['campaign_id'], (int) $this->data['min_views']);
            $group = collect($groups)->first(fn ($g) => $g['advert_id'] === $arguments['advert'] && $g['matching_views_timestamp'] === $arguments['pattern']);
            abort_unless($group && ! array_diff($data['user_ids'], $group['users']), 422);
            // Staff accounts are managed through the member access workflow.
            abort_if(\App\Models\User::whereIn('id', $data['user_ids'])->whereHas('role', fn ($query) => $query->whereIn('slug', ['admin', 'dev', 'manager']))->exists(), 403);

            AdminWorkflow::run(AdminFraudController::class, 'bulkFraudAction', $data + ['reported_by' => auth()->id()]);
        });
    }
}
