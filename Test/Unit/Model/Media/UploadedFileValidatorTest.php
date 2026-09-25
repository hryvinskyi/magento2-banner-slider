<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\UploadedFileChecker;
use Hryvinskyi\BannerSlider\Model\Media\UploadedFileValidator;
use Hryvinskyi\BannerSliderApi\Api\Value\UploadedFile;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(UploadedFileValidator::class)]
class UploadedFileValidatorTest extends TestCase
{
    private const FILES = __DIR__ . '/../../_files/';
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];
    private const MAX_BYTES = 1048576;
    private const PNG_FIXTURE_BYTES = 103;

    /**
     * @var UploadedFileChecker&MockObject
     */
    private MockObject $checker;

    /**
     * @var UploadedFileValidator
     */
    private UploadedFileValidator $validator;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->checker = $this->createMock(UploadedFileChecker::class);
        $this->checker->method('isUploadedFile')->willReturn(true);
        $this->validator = new UploadedFileValidator($this->checker, new LocalFileDriver());
    }

    /**
     * An accepted file reports its sniffed type, the extension of that type and its real size
     *
     * @return void
     */
    public function testAcceptsAllowedType(): void
    {
        $result = $this->validator->validate(
            $this->file('images/2x2.png', 'Holiday Photo.png'),
            self::IMAGE_TYPES,
            self::MAX_BYTES
        );

        self::assertSame(
            ['mime' => 'image/png', 'extension' => 'png', 'size' => self::PNG_FIXTURE_BYTES],
            $result
        );
    }

    /**
     * The extension follows the content, not the name the client sent
     *
     * @return void
     */
    public function testExtensionComesFromSniffedTypeNotClientName(): void
    {
        $result = $this->validator->validate(
            $this->file('images/2x2.jpg', 'innocent.png'),
            self::IMAGE_TYPES,
            self::MAX_BYTES
        );

        self::assertSame('image/jpeg', $result['mime']);
        self::assertSame('jpg', $result['extension']);
    }

    /**
     * Videos are sniffed the same way
     *
     * @param string $file
     * @param string $mime
     * @param string $extension
     * @return void
     */
    #[TestWith(['video/tiny.mp4', 'video/mp4', 'mp4'])]
    #[TestWith(['video/tiny.webm', 'video/webm', 'webm'])]
    public function testAcceptsVideo(string $file, string $mime, string $extension): void
    {
        $result = $this->validator->validate(
            $this->file($file, 'clip.bin'),
            ['video/mp4' => 'mp4', 'video/webm' => 'webm'],
            self::MAX_BYTES
        );

        self::assertSame($mime, $result['mime']);
        self::assertSame($extension, $result['extension']);
    }

    /**
     * A failed PHP upload is refused with a message for its error code
     *
     * @param int $errorCode
     * @param string $message
     * @return void
     */
    #[TestWith([UPLOAD_ERR_INI_SIZE, 'The file is larger than the server accepts.'])]
    #[TestWith([UPLOAD_ERR_FORM_SIZE, 'The file is larger than the server accepts.'])]
    #[TestWith([UPLOAD_ERR_PARTIAL, 'The file was only partly uploaded. Please try again.'])]
    #[TestWith([UPLOAD_ERR_NO_FILE, 'No file was uploaded.'])]
    #[TestWith([UPLOAD_ERR_NO_TMP_DIR, 'The file could not be uploaded (error code 6).'])]
    public function testRejectsUploadError(int $errorCode, string $message): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($message);

        $this->validator->validate(new UploadedFile('', 'a.png', 0, $errorCode, true), self::IMAGE_TYPES, 10);
    }

    /**
     * An HTTP upload whose temp file PHP did not receive is refused
     *
     * @return void
     */
    public function testRejectsFileNotUploadedThroughHttp(): void
    {
        $checker = $this->createMock(UploadedFileChecker::class);
        $checker->expects(self::once())->method('isUploadedFile')
            ->with(self::FILES . 'images/2x2.png')
            ->willReturn(false);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The file was not received as an upload.');

        (new UploadedFileValidator($checker, new LocalFileDriver()))
            ->validate($this->file('images/2x2.png', 'a.png'), self::IMAGE_TYPES, self::MAX_BYTES);
    }

    /**
     * A command-line or import file is not checked as an HTTP upload
     *
     * @return void
     */
    public function testNonHttpFileSkipsUploadCheck(): void
    {
        $checker = $this->createMock(UploadedFileChecker::class);
        $checker->expects(self::never())->method('isUploadedFile');
        $path = self::FILES . 'images/2x2.gif';

        $result = (new UploadedFileValidator($checker, new LocalFileDriver()))
            ->validate(new UploadedFile($path, 'a.gif', 35, UPLOAD_ERR_OK, false), self::IMAGE_TYPES, 100);

        self::assertSame('image/gif', $result['mime']);
    }

    /**
     * A file above the cap is refused; the real size counts, not the reported one
     *
     * @return void
     */
    public function testRejectsTooBig(): void
    {
        $path = self::FILES . 'images/2x2.jpg';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('larger than the 0.0 MB the store accepts');

        $this->validator->validate(new UploadedFile($path, 'a.jpg', 1, UPLOAD_ERR_OK, true), self::IMAGE_TYPES, 10);
    }

    /**
     * An empty file is refused
     *
     * @return void
     */
    public function testRejectsEmpty(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The uploaded file is empty.');

        $this->validator->validate($this->file('video/empty.mp4', 'a.mp4'), ['video/mp4' => 'mp4'], self::MAX_BYTES);
    }

    /**
     * A missing temp file is refused without naming its path
     *
     * @return void
     */
    public function testRejectsUnreadableWithoutPath(): void
    {
        $path = self::FILES . 'images/missing.png';

        try {
            $this->validator->validate(
                new UploadedFile($path, 'a.png', 10, UPLOAD_ERR_OK, true),
                self::IMAGE_TYPES,
                self::MAX_BYTES
            );
            self::fail('A missing file must be refused.');
        } catch (ValidationException $exception) {
            self::assertSame('The uploaded file cannot be read.', $exception->getMessage());
            self::assertStringNotContainsString('_files', $exception->getMessage());
            self::assertCount(1, $exception->getErrors());
        }
    }

    /**
     * A sniffed type outside the allow-list is refused, whatever the client name says
     *
     * @return void
     */
    public function testRejectsTypeNotAllowed(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The file type is not accepted. Accepted types: mp4, webm.');

        $this->validator->validate(
            $this->file('images/2x2.png', 'clip.mp4'),
            ['video/mp4' => 'mp4', 'video/webm' => 'webm'],
            self::MAX_BYTES
        );
    }

    /**
     * A text file named like an image is refused
     *
     * @return void
     */
    public function testRejectsTextNamedAsImage(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The file type is not accepted.');

        $this->validator->validate(
            $this->file('images/not-an-image.txt', 'photo.jpg'),
            self::IMAGE_TYPES,
            self::MAX_BYTES
        );
    }

    /**
     * SVG is refused even when a caller lists it as allowed
     *
     * @return void
     */
    public function testRejectsSvgEvenWhenListed(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The file type is not accepted.');

        $this->validator->validate(
            $this->file('images/2x2.svg', 'logo.svg'),
            self::IMAGE_TYPES + ['image/svg+xml' => 'svg'],
            self::MAX_BYTES
        );
    }

    /**
     * A received HTTP upload of a fixture file
     *
     * @param string $fixture Path below the fixture folder
     * @param string $clientName
     * @return UploadedFile
     */
    private function file(string $fixture, string $clientName): UploadedFile
    {
        return new UploadedFile(self::FILES . $fixture, $clientName, 123, UPLOAD_ERR_OK, true);
    }
}
