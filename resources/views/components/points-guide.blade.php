<section class="concept-guide concept-guide--points" aria-labelledby="points-guide-title">
    <div class="concept-guide__head">
        <div>
            <h2 id="points-guide-title">{{ __('ui.points_title') }}</h2>
        </div>
    </div>
    <div class="concept-guide__grid concept-guide__grid--three">
        <div class="concept-guide__item"><strong>+ {{ app()->isLocale('en') ? 'Award' : 'استحقاق' }}</strong><span>{{ __('ui.points_award') }}</span></div>
        <div class="concept-guide__item"><strong>− {{ app()->isLocale('en') ? 'Late penalty' : 'خصم تأخير' }}</strong><span>{{ __('ui.points_penalty') }}</span></div>
        <div class="concept-guide__item"><strong>± {{ app()->isLocale('en') ? 'Correction' : 'تصحيح' }}</strong><span>{{ __('ui.points_correction') }}</span></div>
    </div>
</section>
