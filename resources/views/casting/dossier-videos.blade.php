@php($singleTape = $audition->application_details && $audition->videos->count() === 1)
<section class="dossier-videos" id="video" aria-labelledby="review-videos-title">
    <h2 id="review-videos-title">{{ $singleTape ? 'Self-tape' : 'I video' }}</h2>
    @if($audition->videos->count() > 1)
        <div class="video-choices" data-video-choices hidden role="group" aria-label="Scegli il video da guardare">
            @foreach($audition->videos as $video)
                <button type="button" data-video-choice="{{ $video->id }}" aria-controls="review-video-{{ $video->id }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">Video {{ $video->position }}</button>
            @endforeach
        </div>
    @endif
    @foreach($audition->videos as $video)
        <div class="review-video-card" id="review-video-{{ $video->id }}" data-review-video="{{ $video->id }}">
            @unless($singleTape)<h3>Video {{ $video->position }}</h3>@endunless
            @if($video->compression_status === \App\Models\AuditionVideo::QUEUED)
                <p class="video-compression-status">Self-tape in elaborazione.</p>
            @elseif($video->compression_status === \App\Models\AuditionVideo::COMPRESSED)
                <p class="video-compression-status">MP4 pronto@if($video->original_bytes > $video->bytes) · {{ round(100 * (1 - $video->bytes / $video->original_bytes)) }}% più leggero@endif</p>
            @elseif($video->compression_status === \App\Models\AuditionVideo::FAILED)
                <p class="video-compression-status">Conversione non riuscita. Il file ricevuto è disponibile.</p>
            @endif
            @unless($video->compression_status === \App\Models\AuditionVideo::QUEUED)
                <video controls playsinline preload="none" aria-label="{{ $singleTape ? 'Self-tape' : 'Video '.$video->position }} di {{ $audition->first_name }}">
                    <source src="{{ route('casting.videos.watch',$video) }}" type="{{ $video->file_mime }}">
                    Il browser non supporta questo formato video.
                </video>
            @endunless
        </div>
    @endforeach
    @if($audition->video_path)
        <video class="legacy-audition-video" controls playsinline preload="none" aria-label="Video già presente nella candidatura">
            <source src="{{ route('casting.video',$audition) }}" type="{{ $audition->video_mime }}">
        </video>
    @elseif($audition->videos->isEmpty())
        <p class="production-empty">Nessun video associato a questa candidatura.</p>
    @endif
</section>
