<section class="concept-guide" aria-labelledby="workflow-guide-title">
    <div class="concept-guide__head">
        <div>
            <h2 id="workflow-guide-title">{{ __('ui.workflow_title') }}</h2>
            <p>{{ __('ui.workflow_intro') }}</p>
        </div>
    </div>
    <ol class="concept-guide__grid">
        @foreach (['intake', 'delivery', 'verification', 'closure'] as $stage)
            <li class="concept-guide__item">
                <strong>{{ __("ui.stages.{$stage}.title") }}</strong>
                <span>{{ __("ui.stages.{$stage}.body") }}</span>
            </li>
        @endforeach
    </ol>
</section>
