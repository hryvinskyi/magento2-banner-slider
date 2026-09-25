<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Banner;

use Hryvinskyi\BannerSlider\Model\Banner\BannerEditPlan;
use Hryvinskyi\BannerSlider\Model\Banner\BannerEditPlanner;
use Hryvinskyi\BannerSlider\Model\Banner\BannerImageSizer;
use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChange;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeSet;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeValidator;
use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Validation\BannerValidatorInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(BannerEditPlanner::class)]
#[CoversClass(BannerEditPlan::class)]
class BannerEditPlannerTest extends TestCase
{
    /**
     * @var BannerRepositoryInterface&MockObject
     */
    private MockObject $bannerRepository;

    /**
     * @var ResponsiveCropRepositoryInterface&MockObject
     */
    private MockObject $cropRepository;

    /**
     * @var BannerValidatorInterface&MockObject
     */
    private MockObject $bannerValidator;

    /**
     * @var BannerImageSizer&MockObject
     */
    private MockObject $imageSizer;

    /**
     * @var CropChangeValidator&MockObject
     */
    private MockObject $changeValidator;

    /**
     * @var BannerEditPlanner
     */
    private BannerEditPlanner $planner;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->bannerRepository = $this->createMock(BannerRepositoryInterface::class);
        $this->cropRepository = $this->createMock(ResponsiveCropRepositoryInterface::class);
        $this->bannerValidator = $this->createMock(BannerValidatorInterface::class);
        $this->imageSizer = $this->createMock(BannerImageSizer::class);
        $this->changeValidator = $this->createMock(CropChangeValidator::class);
        $this->planner = new BannerEditPlanner(
            $this->bannerRepository,
            $this->cropRepository,
            $this->bannerValidator,
            $this->imageSizer,
            $this->changeValidator,
            new MediaPaths([
                'image' => 'banner_slider/image',
                'responsive' => 'banner_slider/responsive',
                'breakpoint' => 'banner_slider/breakpoint',
            ])
        );
    }

    /**
     * A stored banner is checked against its stored version and its stored crops by breakpoint
     *
     * @return void
     */
    public function testPlansStoredBanner(): void
    {
        $banner = $this->banner(8);
        $stored = $this->banner(8);
        $crop = $this->createMock(ResponsiveCropInterface::class);
        $crop->method('getBreakpointId')->willReturn(3);
        $inputs = [new CropInput(3, null, null, [], [], false, true)];
        $change = new CropChange($inputs[0], $this->createMock(BreakpointInterface::class), $crop, null);
        $this->bannerRepository->method('getById')->with(8)->willReturn($stored);
        $this->cropRepository->method('getByBannerId')->with(8)->willReturn([$crop]);
        $this->imageSizer->method('resolve')->with($banner, $stored)->willReturn(new Dimensions(10, 20));
        $this->changeValidator->expects(self::once())->method('check')->with($banner, $inputs, [3 => $crop], null)
            ->willReturn(new CropChangeSet([$change], []));

        $plan = $this->planner->plan($banner, $inputs);

        self::assertSame(20, $plan->getImageDimensions()?->getHeight());
        self::assertSame([$change], $plan->getCropChanges());
    }

    /**
     * A banner that moves to another slider keeps none of its stored crops: they are planned for deletion
     *
     * @return void
     */
    public function testBannerMovingToAnotherSliderReleasesItsStoredCrops(): void
    {
        $banner = $this->banner(8, 2);
        $stored = $this->banner(8, 1);
        $first = $this->createMock(ResponsiveCropInterface::class);
        $first->method('getBreakpointId')->willReturn(3);
        $second = $this->createMock(ResponsiveCropInterface::class);
        $second->method('getBreakpointId')->willReturn(4);
        $this->bannerRepository->method('getById')->with(8)->willReturn($stored);
        $this->cropRepository->method('getByBannerId')->with(8)->willReturn([$first, $second]);
        $this->changeValidator->expects(self::once())->method('check')->with($banner, [], [], null)
            ->willReturn(new CropChangeSet([], []));

        $plan = $this->planner->plan($banner, []);

        self::assertSame([$first, $second], $plan->getStaleCrops());
        self::assertSame(1, $plan->getPreviousSliderId());
    }

    /**
     * A banner that stays on its slider has no stale crops
     *
     * @return void
     */
    public function testBannerStayingOnItsSliderHasNoStaleCrops(): void
    {
        $banner = $this->banner(8, 1);
        $crop = $this->createMock(ResponsiveCropInterface::class);
        $crop->method('getBreakpointId')->willReturn(3);
        $this->bannerRepository->method('getById')->willReturn($this->banner(8, 1));
        $this->cropRepository->method('getByBannerId')->willReturn([$crop]);
        $this->changeValidator->method('check')->with($banner, [], [3 => $crop], null)
            ->willReturn(new CropChangeSet([], []));

        self::assertSame([], $this->planner->plan($banner, [])->getStaleCrops());
    }

    /**
     * A new banner has no stored version and no stored crops
     *
     * @return void
     */
    public function testPlansNewBanner(): void
    {
        $banner = $this->banner(null);
        $this->bannerRepository->expects(self::never())->method('getById');
        $this->cropRepository->expects(self::never())->method('getByBannerId');
        $this->imageSizer->method('resolve')->with($banner, null)->willReturn(null);
        $this->changeValidator->method('check')->with($banner, [], [], null)->willReturn(new CropChangeSet([], []));

        $plan = $this->planner->plan($banner, []);

        self::assertNull($plan->getImageDimensions());
        self::assertNull($plan->getPreviousSliderId());
    }

    /**
     * Banner, image and crop errors are reported together in one exception
     *
     * @return void
     */
    public function testReportsEveryError(): void
    {
        $banner = $this->banner(null);
        $this->bannerValidator->method('validate')->willThrowException(new ValidationException(
            __('The banner is not valid: %1', 'x'),
            null,
            0,
            new ValidationResult([__('Upload or choose an image for the image banner.'), __('Name it.')])
        ));
        $this->imageSizer->method('resolve')->willThrowException(new LocalizedException(__('image unreadable')));
        $this->changeValidator->method('check')->willReturn(
            new CropChangeSet([], [__('Breakpoint 9 does not belong to the slider of this banner.')])
        );

        try {
            $this->planner->plan($banner, []);
            self::fail('The errors must be reported.');
        } catch (ValidationException $exception) {
            self::assertSame(
                [
                    'Upload or choose an image for the image banner.',
                    'Name it.',
                    'image unreadable',
                    'Breakpoint 9 does not belong to the slider of this banner.',
                ],
                array_map(
                    static fn (LocalizedException $error): string => $error->getMessage(),
                    $exception->getErrors()
                )
            );
        }
    }

    /**
     * A new or changed banner image outside the package's image upload folder is refused
     *
     * @param int|null $bannerId
     * @param string $image
     * @return void
     */
    #[TestWith([null, 'downloadable/files/secret.png'])]
    #[TestWith([null, 'customer/a/b/id-scan.jpg'])]
    #[TestWith([8, 'downloadable/files/secret.png'])]
    #[TestWith([8, 'banner_slider/responsive/8/desktop_a.jpg'])]
    #[TestWith([8, 'banner_slider/imagex/a.jpg'])]
    public function testImageOutsideUploadFolderIsRefused(?int $bannerId, string $image): void
    {
        $this->bannerRepository->method('getById')->willReturn($this->banner(8, 1, 'banner_slider/image/old.jpg'));
        $this->changeValidator->method('check')->willReturn(new CropChangeSet([], []));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(sprintf(
            'The banner image "%s" is not an image uploaded for the banner slider. Upload the image instead.',
            $image
        ));
        $this->planner->plan($this->banner($bannerId, 1, $image), []);
    }

    /**
     * An unchanged image stays valid wherever it lives, and a new upload is accepted; the crops are checked against
     * the stored image
     *
     * @param int|null $bannerId
     * @param string|null $storedImage
     * @param string $image
     * @return void
     */
    #[TestWith([8, 'legacy_slider/image/legacy.jpg', 'legacy_slider/image/legacy.jpg'])]
    #[TestWith([8, 'legacy_slider/image/legacy.jpg', 'banner_slider/image/2026/09/new-1a2b3c4d5e6f.jpg'])]
    #[TestWith([null, null, 'banner_slider/image/2026/09/new-1a2b3c4d5e6f.jpg'])]
    public function testUnchangedOrUploadedImageIsAccepted(?int $bannerId, ?string $storedImage, string $image): void
    {
        $banner = $this->banner($bannerId, 1, $image);
        $this->bannerRepository->method('getById')->willReturn($this->banner(8, 1, $storedImage));
        $this->changeValidator->expects(self::once())->method('check')->with($banner, [], [], $storedImage)
            ->willReturn(new CropChangeSet([], []));

        self::assertSame([], $this->planner->plan($banner, [])->getStaleCrops());
    }

    /**
     * When the image changes, the crops cut from the previous image are released, except those the save sends a new
     * crop for; crops cut from another source stay
     *
     * @return void
     */
    public function testImageChangeReleasesCropsOfThePreviousImage(): void
    {
        $banner = $this->banner(8, 1, 'banner_slider/image/new.jpg');
        $this->bannerRepository->method('getById')->willReturn($this->banner(8, 1, 'banner_slider/image/old.jpg'));
        $fromBannerImage = $this->crop(3, null);
        $fromOldImage = $this->crop(4, 'banner_slider/image/old.jpg');
        $ownSource = $this->crop(5, 'banner_slider/breakpoint/mobile.jpg');
        $replaced = $this->crop(6, null);
        $this->cropRepository->method('getByBannerId')
            ->willReturn([$fromBannerImage, $fromOldImage, $ownSource, $replaced]);
        $inputs = [new CropInput(6, null, null, [], [], false, true)];
        $this->changeValidator->expects(self::once())->method('check')
            ->with($banner, $inputs, [5 => $ownSource, 6 => $replaced], 'banner_slider/image/old.jpg')
            ->willReturn(new CropChangeSet([], []));

        self::assertSame([$fromBannerImage, $fromOldImage], $this->planner->plan($banner, $inputs)->getStaleCrops());
    }

    /**
     * An id no stored banner has is a validation error
     *
     * @return void
     */
    public function testUnknownBanner(): void
    {
        $this->bannerRepository->method('getById')->willThrowException(new NoSuchEntityException(__('gone')));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The banner with id "8" does not exist.');
        $this->planner->plan($this->banner(8), []);
    }

    /**
     * A banner with an id, on a slider, with an image
     *
     * @param int|null $bannerId
     * @param int|null $sliderId
     * @param string|null $image
     * @return BannerInterface
     */
    private function banner(?int $bannerId, ?int $sliderId = null, ?string $image = null): BannerInterface
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getBannerId')->willReturn($bannerId);
        $banner->method('getSliderId')->willReturn($sliderId);
        $banner->method('getImage')->willReturn($image);

        return $banner;
    }

    /**
     * A stored crop for a breakpoint, cut from a source (null: the banner image)
     *
     * @param int $breakpointId
     * @param string|null $source
     * @return ResponsiveCropInterface
     */
    private function crop(int $breakpointId, ?string $source): ResponsiveCropInterface
    {
        $crop = $this->createMock(ResponsiveCropInterface::class);
        $crop->method('getBreakpointId')->willReturn($breakpointId);
        $crop->method('getSourceImage')->willReturn($source);

        return $crop;
    }
}
