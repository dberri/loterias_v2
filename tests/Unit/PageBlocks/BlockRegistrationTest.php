<?php

namespace Tests\Unit\PageBlocks;

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
use Tests\TestCase;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

class BlockRegistrationTest extends TestCase
{
    /**
     * Every PageBlock class under app/Filament/Fabricator/PageBlocks, keyed by
     * the block name each one registers under (its `Block::make()` slug from
     * before the v4 upgrade, now the `$name` static property).
     *
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function blockProvider(): array
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

    #[\PHPUnit\Framework\Attributes\DataProvider('blockProvider')]
    public function test_every_block_class_instantiates_and_is_registered(string $blockClass, string $expectedName): void
    {
        $this->assertSame($expectedName, $blockClass::getName());

        $schema = $blockClass::getBlockSchema();

        $this->assertInstanceOf(Block::class, $schema);
        $this->assertSame($expectedName, $schema->getName());
    }

    public function test_all_fourteen_blocks_appear_in_the_registered_block_set(): void
    {
        $registered = FilamentFabricator::getPageBlocksRaw();

        foreach (self::blockProvider() as [$blockClass, $expectedName]) {
            $this->assertArrayHasKey($expectedName, $registered);
            $this->assertSame($blockClass, $registered[$expectedName]);
        }

        $this->assertCount(14, $registered);
    }
}
