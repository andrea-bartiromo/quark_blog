@foreach($editorialBlocks->where('enabled', true) as $block)
  @php
    $layout = $block['layout'] ?? 'image_left';
    $currentBlockImage = $blockImage($block);
    $currentBlockBackground = $blockBackground($block);
    $blockUrl = $block['link_url'] ?? '#'.($block['key'] ?? Str::slug($block['title'] ?? 'sezione'));
  @endphp

  <section class="turing-section turing-editorial-block {{ !empty($currentBlockBackground) ? 'has-bg' : '' }} turing-layout--{{ $layout }}" id="{{ $block['key'] ?? Str::slug($block['title'] ?? 'sezione') }}" style="{{ $bg($currentBlockBackground) }}">
    <a class="container container--wide turing-split turing-editorial-link" href="{{ $blockUrl }}">
      @if(in_array($layout, ['image_left', 'feature_grid']))
        <div class="turing-image-panel" style="{{ $bg($currentBlockImage) }}"></div>
      @endif

      <div class="turing-copy-panel">
        <p class="turing-kicker">{{ $block['kicker'] ?? 'Turing' }}</p>
        <h2>{{ $block['title'] ?? 'Sezione editoriale' }}</h2>
        <p>{{ $block['text'] ?? '' }}</p>

        @if(!empty($block['link_label']))
          <div class="turing-actions">
            <span>{{ $block['link_label'] }}</span>
          </div>
        @endif
      </div>

      @if($layout === 'image_right')
        <div class="turing-image-panel" style="{{ $bg($currentBlockImage) }}"></div>
      @endif
    </a>
  </section>
@endforeach
