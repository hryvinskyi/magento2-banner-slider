<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Etc;

use Magento\Framework\DB\ExpressionConverter;
use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Keeps db_schema_whitelist.json in step with db_schema.xml.
 *
 * The declarative engine drops an index or constraint only when the whitelist holds the name Magento generated for
 * it in the database, not its referenceId; a column, index or table leaves the database only when it is
 * whitelisted but no longer declared. Columns whose data a patch migrates must be neither declared nor whitelisted,
 * or the engine would drop them before the patch copies them.
 */
#[CoversNothing]
class DbSchemaWhitelistTest extends TestCase
{
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const SCHEMA_FILE = __DIR__ . '/../../../etc/db_schema.xml';
    private const WHITELIST_FILE = __DIR__ . '/../../../etc/db_schema_whitelist.json';

    /**
     * Columns a data patch reads (and copies elsewhere, or applies) before it drops them itself
     */
    private const MIGRATED_COLUMNS = [
        'hryvinskyi_banner_slider' => ['store_ids', 'customer_group_ids', 'is_responsive'],
        'hryvinskyi_banner_slider_responsive_crop' => [
            'webp_image',
            'avif_image',
            'generate_webp',
            'generate_avif',
            'webp_quality',
            'avif_quality',
        ],
    ];

    /**
     * Elements no longer declared that the declarative engine must drop: table => type => names
     */
    private const TO_DROP = [
        'hryvinskyi_banner_slider_banner' => [
            'index' => [
                'HRYVINSKYI_BANNER_SLIDER_BANNER_NAME_IMAGE_LINK_URL',
                'HRYVINSKYI_BANNER_SLIDER_BANNER_SLIDER_ID',
            ],
        ],
        'hryvinskyi_banner_slider_breakpoint' => [
            'index' => ['HRYVINSKYI_BANNER_SLIDER_BREAKPOINT_SLIDER_ID'],
        ],
        'hryvinskyi_banner_slider_responsive_crop' => [
            'column' => ['sort_order'],
            'index' => [
                'HRYVINSKYI_BANNER_SLIDER_RESPONSIVE_CROP_BANNER_ID',
                'HRYVINSKYI_BANNER_SLIDER_RESPONSIVE_CROP_STATUS',
            ],
        ],
        'hryvinskyi_banner_slider_image' => [
            'column' => [
                'image_id',
                'banner_id',
                'title',
                'alt',
                'picture_media',
                'status',
                'image',
                'created_at',
                'updated_at',
            ],
            'index' => ['HRYVINSKYI_BANNER_SLIDER_IMAGE_BANNER_ID'],
            'constraint' => ['PRIMARY'],
        ],
    ];

    /**
     * Every declared column, index and constraint is whitelisted under its generated database name
     *
     * @return void
     */
    public function testDeclaredElementsAreWhitelisted(): void
    {
        $whitelist = $this->readWhitelist();
        foreach ($this->readDeclaredElements() as $table => $types) {
            foreach ($types as $type => $names) {
                foreach ($names as $name) {
                    self::assertTrue(
                        isset($whitelist[$table][$type][$name]),
                        sprintf('%s %s "%s" is declared but not whitelisted.', $table, $type, $name)
                    );
                }
            }
        }
    }

    /**
     * Every referenceId equals the name Magento generates for the element, so XML, whitelist and database agree
     *
     * @return void
     */
    public function testReferenceIdsAreGeneratedNames(): void
    {
        foreach ($this->readSchema() as $table => $elements) {
            foreach ($elements['keys'] as $key) {
                self::assertSame(
                    $key['name'],
                    $key['referenceId'],
                    sprintf('%s: referenceId "%s" is not the generated name.', $table, $key['referenceId'])
                );
            }
        }
    }

    /**
     * Every element the engine must drop is whitelisted, and none of them is still declared
     *
     * @return void
     */
    public function testElementsToDropAreWhitelistedAndUndeclared(): void
    {
        $whitelist = $this->readWhitelist();
        $declared = $this->readDeclaredElements();
        foreach ($this->toDrop() as $table => $types) {
            foreach ($types as $type => $names) {
                foreach ($names as $name) {
                    self::assertTrue(
                        isset($whitelist[$table][$type][$name]),
                        sprintf('%s %s "%s" must be whitelisted so it is dropped.', $table, $type, $name)
                    );
                    self::assertNotContains($name, $declared[$table][$type] ?? []);
                }
            }
        }
        self::assertArrayNotHasKey('hryvinskyi_banner_slider_image', $declared);
    }

    /**
     * No migrated column is declared or whitelisted, so the engine leaves it for its data patch
     *
     * @return void
     */
    public function testMigratedColumnsAreNeitherDeclaredNorWhitelisted(): void
    {
        $whitelist = $this->readWhitelist();
        $declared = $this->readDeclaredElements();
        foreach (self::MIGRATED_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                self::assertFalse(
                    isset($whitelist[$table]['column'][$column]),
                    sprintf('Migrated column %s.%s must not be whitelisted.', $table, $column)
                );
                self::assertNotContains($column, $declared[$table]['column'] ?? []);
            }
        }
    }

    /**
     * The whitelist holds exactly the declared elements plus the ones to drop
     *
     * @return void
     */
    public function testWhitelistHoldsNothingElse(): void
    {
        $expected = $this->readDeclaredElements();
        foreach ($this->toDrop() as $table => $types) {
            foreach ($types as $type => $names) {
                foreach ($names as $name) {
                    $expected[$table][$type][] = $name;
                }
            }
        }

        $actual = [];
        foreach ($this->readWhitelist() as $table => $types) {
            foreach ($types as $type => $names) {
                $actual[$table][$type] = array_keys($names);
            }
        }

        self::assertSame($this->sortElements($expected), $this->sortElements($actual));
    }

    /**
     * Elements to drop, with the generated foreign key name of the removed table added
     *
     * @return array<string, array<string, list<string>>>
     */
    private function toDrop(): array
    {
        $toDrop = self::TO_DROP;
        $toDrop['hryvinskyi_banner_slider_image']['constraint'][] = $this->foreignKeyName(
            'hryvinskyi_banner_slider_image',
            'banner_id',
            'hryvinskyi_banner_slider_banner',
            'banner_id'
        );

        return $toDrop;
    }

    /**
     * Declared element names per table and type
     *
     * @return array<string, array<string, list<string>>>
     */
    private function readDeclaredElements(): array
    {
        $declared = [];
        foreach ($this->readSchema() as $table => $elements) {
            $declared[$table]['column'] = $elements['columns'];
            foreach ($elements['keys'] as $key) {
                $declared[$table][$key['type']][] = $key['name'];
            }
        }

        return $declared;
    }

    /**
     * Parse db_schema.xml into column names and keys with their generated names
     *
     * @return array<string, array{
     *     columns: list<string>,
     *     keys: list<array{type: string, referenceId: string, name: string}>
     * }>
     */
    private function readSchema(): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::SCHEMA_FILE));

        $schema = [];
        foreach ($document->getElementsByTagName('table') as $table) {
            $tableName = $table->getAttribute('name');
            $columns = [];
            $keys = [];
            foreach ($table->childNodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                if ($node->tagName === 'column') {
                    $columns[] = $node->getAttribute('name');
                    continue;
                }
                $keys[] = [
                    'type' => $node->tagName,
                    'referenceId' => $node->getAttribute('referenceId'),
                    'name' => $this->generatedName($tableName, $node),
                ];
            }
            $schema[$tableName] = ['columns' => $columns, 'keys' => $keys];
        }

        return $schema;
    }

    /**
     * The name Magento generates in the database for an index or constraint
     *
     * @param string $table
     * @param \DOMElement $element
     * @return string
     */
    private function generatedName(string $table, \DOMElement $element): string
    {
        $type = $element->getAttributeNS(self::XSI, 'type');
        if ($element->tagName === 'constraint' && $type === 'primary') {
            return 'PRIMARY';
        }
        if ($element->tagName === 'constraint' && $type === 'foreign') {
            return $this->foreignKeyName(
                $table,
                $element->getAttribute('column'),
                $element->getAttribute('referenceTable'),
                $element->getAttribute('referenceColumn')
            );
        }

        $columns = [];
        foreach ($element->getElementsByTagName('column') as $column) {
            $columns[] = $column->getAttribute('name');
        }
        $prefix = match (true) {
            $element->tagName === 'constraint' => 'unq_',
            $element->getAttribute('indexType') === 'fulltext' => 'fti_',
            default => 'idx_',
        };

        return strtoupper(ExpressionConverter::shortenEntityName($table . '_' . implode('_', $columns), $prefix));
    }

    /**
     * The name Magento generates in the database for a foreign key
     *
     * @param string $table
     * @param string $column
     * @param string $referenceTable
     * @param string $referenceColumn
     * @return string
     */
    private function foreignKeyName(
        string $table,
        string $column,
        string $referenceTable,
        string $referenceColumn
    ): string {
        return strtoupper(ExpressionConverter::shortenEntityName(
            sprintf('%s_%s_%s_%s', $table, $column, $referenceTable, $referenceColumn),
            'fk_'
        ));
    }

    /**
     * Decode db_schema_whitelist.json
     *
     * @return array<string, array<string, array<string, bool>>>
     */
    private function readWhitelist(): array
    {
        $decoded = json_decode((new File())->fileGetContents(self::WHITELIST_FILE), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        $whitelist = [];
        foreach ($decoded as $table => $types) {
            self::assertIsArray($types);
            foreach ($types as $type => $names) {
                self::assertIsArray($names);
                foreach ($names as $name => $flag) {
                    self::assertTrue($flag);
                    $whitelist[(string)$table][(string)$type][(string)$name] = true;
                }
            }
        }

        return $whitelist;
    }

    /**
     * Sort tables, types and names so two element maps compare regardless of order
     *
     * @param array<string, array<string, list<string>>> $elements
     * @return array<string, array<string, list<string>>>
     */
    private function sortElements(array $elements): array
    {
        ksort($elements);
        foreach ($elements as $table => $types) {
            ksort($types);
            foreach ($types as $type => $names) {
                sort($names);
                $types[$type] = $names;
            }
            $elements[$table] = $types;
        }

        return $elements;
    }
}
