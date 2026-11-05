<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Webconsulting\AstryxTypo3\Data\AstryxSiteDefinitions;
use Webconsulting\Desiderio\Data\Showcase\ShowcasePricing;

/**
 * The Astryx home page sells the same plans and the same open-source model
 * as Desiderio, with every price read from Desiderio's ShowcasePricing.
 */
final class AstryxPricingTest extends TestCase
{
    public function testThePlansShowThePricesOfThePriceSource(): void
    {
        $tiers = $this->pricingBlocks()[0]['tiers'];

        self::assertSame(array_column(ShowcasePricing::plans(), 'name'), array_column($tiers, 'title'));
        self::assertSame(array_column(ShowcasePricing::plans(), 'price'), array_column($tiers, 'value'));
    }

    public function testTheModelBlockOffersCareSupportHoursSponsoringAndTheSupporter(): void
    {
        $block = $this->pricingBlocks()[1];
        $values = array_column($block['tiers'], 'value', 'title');

        self::assertSame([
            'Care' => ShowcasePricing::care()['price'],
            'Support hours' => ShowcasePricing::service('Support hours')['rate'],
            'Sponsored feature' => ShowcasePricing::sponsoredFeature()['price'],
            'Supporter' => ShowcasePricing::supporter()['price'],
        ], $values);
        self::assertStringContainsString(ShowcasePricing::modelSentence(), $block['lead']);
        self::assertStringContainsString(ShowcasePricing::SELLER_NAME, $block['note']);

        $care = $block['tiers'][0]['text'];
        self::assertStringContainsString(ShowcasePricing::care()['yearly'], $care);
        self::assertStringContainsString(ShowcasePricing::care()['further_sites'], $care);
    }

    public function testTheFaqNamesTheSellerWithItsLegalNoticeAndTerms(): void
    {
        $answers = '';
        foreach (AstryxSiteDefinitions::homeContent() as $element) {
            if ($element['ctype'] === 'astryx_typo3_accordionfaq') {
                $answers = (string)json_encode($element['fixture']['questions'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        self::assertStringContainsString('Who sends the invoice?', $answers);
        self::assertStringContainsString('href=\"' . ShowcasePricing::SELLER_IMPRINT_URL . '\"', $answers);
        self::assertStringContainsString('href=\"' . ShowcasePricing::SELLER_TERMS_URL . '\"', $answers);
    }

    public function testNoPriceIsTypedTwice(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/Classes/Data/AstryxSiteDefinitions.php');

        self::assertDoesNotMatchRegularExpression('/€\s?\d/u', $source, 'Prices come from ShowcasePricing.');
    }

    /**
     * The lab site with "How we earn" sits behind basic auth, so the public
     * Sponsor button leads to the contact page of webconsulting GmbH.
     */
    public function testTheSponsorButtonLeadsToThePublicContactPage(): void
    {
        $file = dirname(__DIR__, 2) . '/.github/FUNDING.yml';
        $funding = Yaml::parseFile($file);

        self::assertIsArray($funding);
        self::assertSame(['https://www.webconsulting.at/kontakt'], $funding['custom'] ?? null);
        self::assertArrayNotHasKey('github', $funding, 'GitHub Sponsors waits for the owner to enrol.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pricingBlocks(): array
    {
        $blocks = [];
        foreach (AstryxSiteDefinitions::homeContent() as $element) {
            if ($element['ctype'] === 'astryx_typo3_pricinglicencetiers') {
                $blocks[] = $element['fixture'];
            }
        }
        self::assertCount(2, $blocks);

        return $blocks;
    }
}
