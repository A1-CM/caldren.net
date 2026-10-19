@php($testimonials = config('marketing.testimonials', []))
@if(count($testimonials))
<section class="obs-testimonials" aria-labelledby="testimonials-title">
    <img class="obs-testimonials-image" src="{{ asset('images/caldren-building-dusk.webp') }}" alt="" loading="lazy" width="1536" height="1024" aria-hidden="true">
    <div class="obs-testimonials-shade" aria-hidden="true"></div>
    <div class="container obs-testimonials-inner">
        <header class="obs-testimonials-heading">
            <span class="obs-testimonials-kicker">ENERGY TEAM PERSPECTIVES / 0{{ count($testimonials) }}</span>
            <span class="eyebrow">{{ config('marketing.testimonials_are_samples') ? 'ILLUSTRATIVE TESTIMONIALS' : 'CUSTOMER PERSPECTIVES' }}</span>
            <h2 id="testimonials-title">A clearer outlook for energy decisions.</h2>
            @if(config('marketing.testimonials_are_samples'))
                <p class="obs-sample-note">Sample statements for design preview only. These are not real customer testimonials.</p>
            @endif
        </header>
        <div class="obs-quote-observatory" data-testimonial-carousel role="region" aria-roledescription="carousel" aria-label="Energy team perspectives">
            <div class="obs-quote-stage" id="testimonial-quote-stage" aria-live="polite" aria-atomic="true">
                @foreach($testimonials as $index => $testimonial)
                    <figure class="obs-quote-slide" data-testimonial-slide data-index="{{ $index }}" role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} of {{ count($testimonials) }}">
                        <span class="obs-quote-index" aria-hidden="true">0{{ $index + 1 }}</span>
                        <blockquote><p>“{{ $testimonial['quote'] }}”</p></blockquote>
                        <figcaption><strong>{{ $testimonial['name'] }}</strong><span>{{ $testimonial['role'] }} · {{ $testimonial['organization'] }}</span></figcaption>
                    </figure>
                @endforeach
            </div>
            <div class="obs-quote-navigation" aria-label="Testimonial navigation">
                <div class="obs-quote-selectors" role="group" aria-label="Choose a testimonial">
                    @foreach($testimonials as $index => $testimonial)
                        <button type="button" data-testimonial-select="{{ $index }}" aria-label="Show quote {{ $index + 1 }} of {{ count($testimonials) }}" aria-controls="testimonial-quote-stage" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                            <span>0{{ $index + 1 }}</span><span class="obs-quote-selector-name">{{ $testimonial['name'] }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="obs-quote-arrows">
                    <button type="button" data-testimonial-prev aria-label="Previous testimonial" aria-controls="testimonial-quote-stage"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                    <button type="button" data-testimonial-next aria-label="Next testimonial" aria-controls="testimonial-quote-stage"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                </div>
            </div>
        </div>
    </div>
</section>
@endif
