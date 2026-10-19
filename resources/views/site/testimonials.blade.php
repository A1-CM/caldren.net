@php($testimonials = config('marketing.testimonials', []))
@if(count($testimonials))
<section class="container obs-section obs-testimonials" aria-labelledby="testimonials-title">
    <div class="obs-section-heading"><span class="eyebrow">{{ config('marketing.testimonials_are_samples') ? 'ILLUSTRATIVE TESTIMONIALS' : 'CUSTOMER PERSPECTIVES' }}</span><h2 id="testimonials-title">A space for building teams’ perspectives.</h2>@if(config('marketing.testimonials_are_samples'))<p class="obs-sample-note">Sample statements for design preview only. These are not real customer testimonials.</p>@endif</div>
    <div class="obs-testimonial-grid">
        @foreach($testimonials as $testimonial)
            <figure class="obs-testimonial-card obs-light-glass">
                <blockquote><p>“{{ $testimonial['quote'] }}”</p></blockquote>
                <figcaption><strong>{{ $testimonial['name'] }}</strong><span>{{ $testimonial['role'] }} · {{ $testimonial['organization'] }}</span></figcaption>
            </figure>
        @endforeach
    </div>
</section>
@endif
