@foreach($editorialBlocks->where('enabled', true) as $block)
  @php
    $blockData = collect($block);
    $key = $blockData->get('key');
    $forcedLayouts = [
      'enigma' => 'image_left',
      'macchina-universale' => 'image_right',
      'test-turing' => 'image_left',
      'ai-moderna' => 'image_right',
    ];
    $layout = $forcedLayouts[$key] ?? ($blockData->get('layout') ?? 'image_left');
    $currentBlockImage = $blockImage($block);
    $currentBlockBackground = $blockBackground($block);
    $blockId = $key ?: Str::slug($blockData->get('title', 'sezione'));
    $rawBlockUrl = trim((string) $blockData->get('link_url', ''));
    $hasBlockUrl = $rawBlockUrl !== '' && $rawBlockUrl !== '#'.$blockId;
    $containerTag = $hasBlockUrl ? 'a' : 'div';
    $containerClasses = 'container container--wide turing-split'.($hasBlockUrl ? ' turing-editorial-link' : '');
  @endphp

  <section class="turing-section turing-editorial-block {{ !empty($currentBlockBackground) ? 'has-bg' : '' }} turing-layout--{{ $layout }}" id="{{ $blockId }}" style="{{ $bg($currentBlockBackground) }}">
    <{{ $containerTag }} class="{{ $containerClasses }}" @if($hasBlockUrl) href="{{ $rawBlockUrl }}" @endif>
      @if($layout === 'image_left')
        <div class="turing-image-panel" style="{{ $bg($currentBlockImage) }}"></div>
      @endif

      <div class="turing-copy-panel">
        <x-special.section-header
          variant="panel"
          align="left"
          :kicker="$blockData->get('kicker', 'Turing')"
          :title="$blockData->get('title', 'Sezione editoriale')"
          :text="$blockData->get('text', '')"
        />

        @if($hasBlockUrl && filled($blockData->get('link_label')))
          <div class="turing-actions">
            <span>{{ $blockData->get('link_label') }}</span>
          </div>
        @endif
      </div>

      @if($layout === 'image_right')
        <div class="turing-image-panel" style="{{ $bg($currentBlockImage) }}"></div>
      @endif
    </{{ $containerTag }}>
  </section>
@endforeach
