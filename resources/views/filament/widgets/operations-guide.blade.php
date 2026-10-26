<x-filament-widgets::widget>
    <x-filament::section>
        <div class="visible-welcome">
            <div>
                <p class="visible-eyebrow">VISIBLE · OPERATIONS</p>
                <h2>Everything moving, in one place.</h2>
                <p>Review a campaign, follow its design, then publish. Track token payments and member earnings in their own workspaces.</p>
            </div>
            <a class="visible-cta" href="{{ \App\Filament\Resources\AdvertSubmissionResource::getUrl() }}">Open submissions <span aria-hidden="true">↗</span></a>
        </div>
        <div class="visible-steps">
            <div><b>01 · Review</b><span>Check the brief, audience and reserved tokens.</span></div>
            <div><b>02 · Design & publish</b><span>Upload final media and roll out the approved advert.</span></div>
            <div><b>03 · Measure</b><span>Follow screenshots, views and reward periods.</span></div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

