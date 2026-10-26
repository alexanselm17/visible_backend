<div class="space-y-4">
    @foreach ($submission?->media ?? [] as $media)
        <div>
            <a class="text-primary-600 underline" href="{{ $media->url }}" target="_blank" rel="noopener">{{ $media->original_name ?: 'Original '.$media->type }}</a>
            @if ($media->type === 'IMAGE')
                <img src="{{ $media->url }}" alt="{{ $media->original_name }}" style="max-height:320px;max-width:100%;object-fit:contain;margin-top:12px;border-radius:12px">
            @else
                <video controls preload="metadata" src="{{ $media->url }}" style="max-height:320px;max-width:100%"></video>
            @endif
        </div>
    @endforeach
    @if ($submission?->final_image_url)
        <p class="font-semibold">Final design</p>
        <img src="{{ $submission->final_image_url }}" alt="Final advert design" style="max-height:420px;max-width:100%;object-fit:contain;border-radius:12px">
    @endif
    @if ($submission?->final_video_url)
        <video controls preload="metadata" src="{{ $submission->final_video_url }}" poster="{{ $submission->final_thumbnail_url }}" style="max-height:420px;max-width:100%"></video>
    @endif
</div>

