<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyCustomerGroupScope;
use Hryvinskyi\BannerSlider\Model\Migration\LegacyScopeParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyScopeParser::class)]
#[CoversClass(LegacyCustomerGroupScope::class)]
class LegacyScopeParserTest extends TestCase
{
    private const EXISTING_STORES = [0, 1, 3, 4];
    private const EXISTING_GROUPS = [0, 1, 2, 3];

    /**
     * @var LegacyScopeParser
     */
    private LegacyScopeParser $parser;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->parser = new LegacyScopeParser();
    }

    /**
     * Store ids are trimmed, deduplicated, sorted and limited to existing stores
     *
     * @param string|null $value
     * @param list<int> $expected
     * @return void
     */
    #[TestWith(['1', [1]])]
    #[TestWith([' 3 , 1 ', [1, 3]])]
    #[TestWith(['3,1,3,1', [1, 3]])]
    #[TestWith(['-1,3', [3]])]
    #[TestWith(['abc,4,1.5,', [4]])]
    #[TestWith(['1,99', [1]])]
    #[TestWith(['0', [0]])]
    #[TestWith(['1,0,3', [0]])]
    public function testParseStoreIds(?string $value, array $expected): void
    {
        self::assertSame($expected, $this->parser->parseStoreIds($value, self::EXISTING_STORES));
    }

    /**
     * Nothing usable, including stores that are all gone, gives an empty list rather than "all store views"
     *
     * @param string|null $value
     * @return void
     */
    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['  '])]
    #[TestWith([',,'])]
    #[TestWith(['abc'])]
    #[TestWith(['-1'])]
    #[TestWith(['98,99'])]
    public function testParseStoreIdsWithNothingUsableIsEmpty(?string $value): void
    {
        self::assertSame([], $this->parser->parseStoreIds($value, self::EXISTING_STORES));
    }

    /**
     * The "all groups" marker alone or mixed with other ids means every group and lists none
     *
     * @param string $value
     * @return void
     */
    #[TestWith(['32000'])]
    #[TestWith(['1, 32000 ,2'])]
    #[TestWith(['99,32000'])]
    public function testParseCustomerGroupsWithAllGroupsMarker(string $value): void
    {
        $scope = $this->parser->parseCustomerGroups($value, self::EXISTING_GROUPS);

        self::assertTrue($scope->isAllGroups());
        self::assertSame([], $scope->getGroupIds());
        self::assertFalse($scope->isNone());
    }

    /**
     * Listed groups are trimmed, deduplicated, sorted and limited to existing groups
     *
     * @param string $value
     * @param list<int> $expected
     * @return void
     */
    #[TestWith(['0', [0]])]
    #[TestWith([' 3, 1 ,3', [1, 3]])]
    #[TestWith(['2,-1,x,99', [2]])]
    public function testParseCustomerGroupsWithListedGroups(string $value, array $expected): void
    {
        $scope = $this->parser->parseCustomerGroups($value, self::EXISTING_GROUPS);

        self::assertFalse($scope->isAllGroups());
        self::assertSame($expected, $scope->getGroupIds());
        self::assertFalse($scope->isNone());
    }

    /**
     * Nothing usable, including groups that are all gone, means nobody rather than every group
     *
     * @param string|null $value
     * @return void
     */
    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['x,-2'])]
    #[TestWith(['98,99'])]
    public function testParseCustomerGroupsWithNothingUsableIsNone(?string $value): void
    {
        $scope = $this->parser->parseCustomerGroups($value, self::EXISTING_GROUPS);

        self::assertTrue($scope->isNone());
        self::assertFalse($scope->isAllGroups());
        self::assertSame([], $scope->getGroupIds());
    }

    /**
     * A scope for all groups cannot list groups
     *
     * @return void
     */
    public function testAllGroupsScopeRejectsGroupIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LegacyCustomerGroupScope(true, [1]);
    }
}
