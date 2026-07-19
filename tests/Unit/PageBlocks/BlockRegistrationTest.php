<?php

namespace Tests\Unit\PageBlocks\BlockRegistrationTest;

use App\Filament\Fabricator\PageBlocks\BreadcrumbBlock;
use App\Filament\Fabricator\PageBlocks\ComparisonTableBlock;
use App\Filament\Fabricator\PageBlocks\FaqBlock;
use App\Filament\Fabricator\PageBlocks\HeroSectionBlock;
use App\Filament\Fabricator\PageBlocks\HowToPlayBlock;
use App\Filament\Fabricator\PageBlocks\IndividualDrawDetailsBlock;
use App\Filament\Fabricator\PageBlocks\LatestResultsBlock;
use App\Filament\Fabricator\PageBlocks\NumberGeneratorBlock;
use App\Filament\Fabricator\PageBlocks\RelatedLinksBlock;
use App\Filament\Fabricator\PageBlocks\ResultsGridBlock;
use App\Filament\Fabricator\PageBlocks\RichTextContentBlock;
use App\Filament\Fabricator\PageBlocks\SimulationBlock;
use App\Filament\Fabricator\PageBlocks\StatisticsCardsBlock;
use App\Filament\Fabricator\PageBlocks\TimelineBlock;
use Filament\Forms\Components\Builder\Block;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * Every PageBlock class under app/Filament/Fabricator/PageBlocks, keyed by
 * the block name each one registers under (its `Block::make()` slug from
 * before the v4 upgrade, now the `$name` static property).
 *
 * @return array<string, array{0: class-string, 1: string}>
 */
function blockProvider(): array
{
    return [
        'breadcrumb' => [BreadcrumbBlock::class, 'breadcrumb'],
        'comparison-table' => [ComparisonTableBlock::class, 'comparison-table'],
        'faq' => [FaqBlock::class, 'faq'],
        'hero-section' => [HeroSectionBlock::class, 'hero-section'],
        'how-to-play' => [HowToPlayBlock::class, 'how-to-play'],
        'individual-draw-details' => [IndividualDrawDetailsBlock::class, 'individual-draw-details'],
        'latest-results' => [LatestResultsBlock::class, 'latest-results'],
        'number-generator' => [NumberGeneratorBlock::class, 'number-generator'],
        'related-links' => [RelatedLinksBlock::class, 'related-links'],
        'results-grid' => [ResultsGridBlock::class, 'results-grid'],
        'rich-text-content' => [RichTextContentBlock::class, 'rich-text-content'],
        'simulation' => [SimulationBlock::class, 'simulation'],
        'statistics-cards' => [StatisticsCardsBlock::class, 'statistics-cards'],
        'timeline' => [TimelineBlock::class, 'timeline'],
    ];
}

test('every block class instantiates and is registered', function (string $blockClass, string $expectedName) {
    expect($blockClass::getName())->toBe($expectedName);

    $schema = $blockClass::getBlockSchema();

    expect($schema)->toBeInstanceOf(Block::class);
    expect($schema->getName())->toBe($expectedName);
})->with(blockProvider());

test('all fourteen blocks appear in the registered block set', function () {
    $registered = FilamentFabricator::getPageBlocksRaw();

    foreach (blockProvider() as [$blockClass, $expectedName]) {
        expect($registered)->toHaveKey($expectedName);
        expect($registered[$expectedName])->toBe($blockClass);
    }

    expect($registered)->toHaveCount(14);
});
