@aware(['page'])
@props([
    'title', 
    'title_level', 
    'content', 
    'container_style', 
    'background_color', 
    'text_alignment', 
    'content_width', 
    'add_padding', 
    'add_margin', 
    'anchor_id', 
    'schema_type',
    'container_classes',
    'content_classes'
])

@php
    $titleTag = $title_level ?? 'h2';
    $schemaAttributes = '';
    
    if ($schema_type) {
        switch ($schema_type) {
            case 'article':
                $schemaAttributes = 'itemscope itemtype="https://schema.org/Article"';
                break;
            case 'faq':
                $schemaAttributes = 'itemscope itemtype="https://schema.org/FAQPage"';
                break;
            case 'how-to':
                $schemaAttributes = 'itemscope itemtype="https://schema.org/HowTo"';
                break;
            case 'news':
                $schemaAttributes = 'itemscope itemtype="https://schema.org/NewsArticle"';
                break;
            case 'review':
                $schemaAttributes = 'itemscope itemtype="https://schema.org/Review"';
                break;
        }
    }
@endphp

<div class="{{ $container_classes }}"
     @if($anchor_id) id="{{ $anchor_id }}" @endif
     @if($schemaAttributes) {!! $schemaAttributes !!} @endif>
     
    <div class="{{ $content_classes }}">
        @if($title)
            <{{ $titleTag }} class="
                @if($titleTag === 'h1') text-3xl md:text-4xl lg:text-5xl @endif
                @if($titleTag === 'h2') text-2xl md:text-3xl lg:text-4xl @endif
                @if($titleTag === 'h3') text-xl md:text-2xl lg:text-3xl @endif
                @if($titleTag === 'h4') text-lg md:text-xl lg:text-2xl @endif
                font-bold text-gray-900 mb-6
                @if($text_alignment === 'center') text-center @endif
                @if($text_alignment === 'right') text-right @endif
            "
            @if($schema_type === 'article' || $schema_type === 'news') itemprop="headline" @endif>
                {{ $title }}
            </{{ $titleTag }}>
        @endif
        
        @if($content)
            <div class="prose prose-lg max-w-none 
                @if($text_alignment === 'center') prose-center @endif
                @if($text_alignment === 'right') prose-right @endif
                prose-blue
                prose-headings:text-gray-900 
                prose-p:text-gray-700 
                prose-a:text-blue-600 
                prose-a:no-underline 
                hover:prose-a:underline
                prose-strong:text-gray-900
                prose-code:text-blue-800
                prose-code:bg-blue-50
                prose-code:px-1
                prose-code:py-0.5
                prose-code:rounded
                prose-blockquote:border-l-blue-500
                prose-blockquote:bg-blue-50
                prose-blockquote:py-2
                prose-blockquote:px-4
                prose-blockquote:rounded-r
                prose-ul:text-gray-700
                prose-ol:text-gray-700
                prose-li:text-gray-700"
                @if($schema_type === 'article' || $schema_type === 'news') itemprop="articleBody" @endif>
                {!! $content !!}
            </div>
        @endif
        
        <!-- Schema.org structured data for articles -->
        @if($schema_type === 'article' || $schema_type === 'news')
            <meta itemprop="datePublished" content="{{ now()->toISOString() }}">
            <meta itemprop="dateModified" content="{{ now()->toISOString() }}">
            @if(isset($page) && $page)
                <meta itemprop="url" content="{{ url($page->slug ?? '') }}">
            @endif
            <div itemprop="author" itemscope itemtype="https://schema.org/Organization" class="hidden">
                <meta itemprop="name" content="{{ config('app.name', 'Loterias') }}">
            </div>
            <div itemprop="publisher" itemscope itemtype="https://schema.org/Organization" class="hidden">
                <meta itemprop="name" content="{{ config('app.name', 'Loterias') }}">
                <div itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">
                    <meta itemprop="url" content="{{ asset('favicon.ico') }}">
                </div>
            </div>
        @endif
    </div>
    
    <!-- Table of Contents (if content has headings) -->
    @if($content && str_contains($content, '<h'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Auto-generate table of contents if headings are present
                const contentDiv = document.querySelector('[data-content-block="{{ $anchor_id ?: 'rich-text' }}"]');
                if (contentDiv) {
                    const headings = contentDiv.querySelectorAll('h2, h3, h4, h5, h6');
                    if (headings.length > 2) {
                        generateTableOfContents(headings, contentDiv);
                    }
                }
            });
            
            function generateTableOfContents(headings, container) {
                const toc = document.createElement('div');
                toc.className = 'bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6';
                toc.innerHTML = '<h4 class="text-lg font-semibold text-gray-900 mb-3">Índice</h4><ul class="space-y-1 text-sm"></ul>';
                
                const list = toc.querySelector('ul');
                
                headings.forEach((heading, index) => {
                    const id = `heading-${index}`;
                    heading.id = id;
                    
                    const li = document.createElement('li');
                    li.innerHTML = `<a href="#${id}" class="text-blue-600 hover:text-blue-800 hover:underline">${heading.textContent}</a>`;
                    list.appendChild(li);
                });
                
                container.insertBefore(toc, container.firstChild.nextSibling);
            }
        </script>
    @endif
</div>

<!-- Hidden data attribute for JS functionality -->
<div data-content-block="{{ $anchor_id ?: 'rich-text' }}" style="display: none;"></div>
