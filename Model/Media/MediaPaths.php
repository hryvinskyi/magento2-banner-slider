<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSlider\Model\Data\MediaRelativePath;

/**
 * The two containment levels of a media-relative path, and the package's own media roots.
 *
 * - Safe: the path cannot leave the media directory (the MediaRelativePath rule). A safe path may be read, copied
 *   locally or turned into a URL wherever it lives, because stored rows also point at folders other modules or
 *   earlier imports wrote.
 * - Writable: safe, and equal to or inside one of the package roots. Only writable paths are written, moved or
 *   deleted, so core never changes a file outside its own folders.
 *
 * The roots come from `di.xml`, keyed by purpose (`image`, `responsive`, `video`, `breakpoint`, `tmp`).
 */
class MediaPaths
{
    /**
     * @var array<string,string>
     */
    private readonly array $roots;

    /**
     * @param array<string,string> $roots Package media roots by purpose, relative to the media directory
     * @throws \InvalidArgumentException When a root is not a safe media-relative folder
     */
    public function __construct(array $roots)
    {
        $normalised = [];
        foreach ($roots as $purpose => $root) {
            $normalised[$purpose] = trim((new MediaRelativePath(trim($root, '/')))->toString(), '/');
        }
        $this->roots = $normalised;
    }

    /**
     * The path itself when it cannot leave the media directory
     *
     * @param string $path
     * @return string
     * @throws \InvalidArgumentException When the path is empty, absolute or could leave the media directory
     */
    public function assertSafe(string $path): string
    {
        return (new MediaRelativePath($path))->toString();
    }

    /**
     * The path itself when it is safe and equal to or inside one of the package roots
     *
     * @param string $path
     * @return string
     * @throws \InvalidArgumentException When the path is not safe or lies outside the package roots
     */
    public function assertWritable(string $path): string
    {
        $safe = $this->assertSafe($path);
        if ($this->rootOf($safe) === null) {
            throw new \InvalidArgumentException(
                sprintf('The media path "%s" is outside the banner slider media folders.', $safe)
            );
        }

        return $safe;
    }

    /**
     * Whether the path names one of the package roots itself rather than something inside it
     *
     * @param string $path
     * @return bool
     */
    public function isRoot(string $path): bool
    {
        return in_array(trim($path, '/'), $this->roots, true);
    }

    /**
     * The root registered for a purpose
     *
     * @param string $purpose Such as `image` or `responsive`
     * @return string Relative to the media directory, without leading or trailing slash
     * @throws \InvalidArgumentException When no root is registered for the purpose
     */
    public function getRoot(string $purpose): string
    {
        if (!isset($this->roots[$purpose])) {
            throw new \InvalidArgumentException(
                sprintf('No banner slider media root is registered for "%s".', $purpose)
            );
        }

        return $this->roots[$purpose];
    }

    /**
     * Every package root
     *
     * @return list<string>
     */
    public function getRoots(): array
    {
        return array_values($this->roots);
    }

    /**
     * The package root the path lies in, or null when it lies in none
     *
     * @param string $safePath
     * @return string|null
     */
    private function rootOf(string $safePath): ?string
    {
        $path = rtrim($safePath, '/');
        foreach ($this->roots as $root) {
            if ($path === $root || str_starts_with($path, $root . '/')) {
                return $root;
            }
        }

        return null;
    }
}
