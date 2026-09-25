# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2026-09-25

Requires `hryvinskyi/magento2-banner-slider-api` 2.1.

### Added
- Column `hryvinskyi_banner_slider.show_autoplay_toggle` (on by default, so existing sliders keep their pause/play
  button; no data patch) and `Slider::isAutoPlayToggleEnabled()` / `setAutoPlayToggleEnabled()`. A stored value
  that is not a whole number reads as on.

## [2.0.0] - 2026-09-25

A breaking release built on `hryvinskyi/magento2-banner-slider-api` 2.0. **The database migration is one-way**: back
up the `hryvinskyi_banner_slider*` tables before `setup:upgrade` (see the README, "Upgrading from 1.x").

### Added
- Link tables `hryvinskyi_banner_slider_store` and `hryvinskyi_banner_slider_customer_group` with the
  `all_customer_groups` flag on the slider, replacing the comma-separated `store_ids` / `customer_group_ids`.
- `hryvinskyi_banner_slider_crop_variant`: one row per extra crop format (format, quality, path), replacing the
  WebP/AVIF columns of the crop table.
- Banner `image_width` / `image_height`, read from the file whenever the image changes.
- Write ports: `Model/Slider/SliderEditor` (a slider with its full breakpoint set, one transaction; a new slider
  without breakpoints gets the `di.xml` default set) and `Model/Banner/BannerEditor` (a banner with its crops, one
  transaction; crop files written first, replaced files deleted only after the commit, created files deleted on
  rollback).
- `Model/ResponsiveCrop/CropRegenerator` and the `banner-slider:crops:regenerate [--banner] [--breakpoint]` command.
- Read ports: `Model/Slider/SliderLocator` (moved from `Slider/Locator`; lowest `priority` value first, then lowest
  id), `Model/Banner/VisibleBannersProvider`, `Model/Picture/PictureSourcesProvider`.
- Image pipeline: an open format registry (`ImageFormatRegistry`, `di.xml`), encoder pool per format
  (`ImageConverter` with GD, Imagick, `cwebp` and `cavif` encoders behind the core `ImageEncoderInterface`),
  `CropRenderer`, `ImageInspector`, `RuntimeCapabilities`, `ProcessRunner` and `BinaryLocator`.
- Crop files are named `<breakpoint identifier>_<content hash>.<extension>` under
  `banner_slider/responsive/<banner id>/`; the original-format output is JPEG for a JPEG source and PNG otherwise.
- Browser-encoded crops are accepted after validation (size cap, sniffed type, decodable, target size); anything
  missing or rejected is encoded on the server.
- Media services: `MediaStorage` (all file access through Magento `Filesystem`), `LocalFileWorkspace`, `MediaPaths`
  (safe paths may be read anywhere; only the package roots are written or deleted), `MediaUrlResolver`,
  `ImageUpload`, `VideoUpload` (type sniffed from the bytes, size capped by configuration).
- Media lifecycle: `ObsoleteMediaRemover` (replaced crop output, only under `banner_slider/responsive/`, never while
  referenced), `BannerMediaCleaner` (a deleted banner's crop folder; also run for every banner of a deleted slider),
  `OrphanMediaSweeper` with the `banner-slider:media:cleanup [--dry-run]` command and a daily cron that stays off
  until `hryvinskyi_banner_slider/media/orphan_sweep_enabled` is `1`. DI arguments: `gracePeriodSeconds` of the
  sweeper, `sourceRoots` of `CropChangeValidator`.
- Cache: entity identities include their parents; slider saves clean the old and new location tags;
  `Cron/RefreshScheduledContent` (every 5 minutes) cleans the tags of sliders and banners whose active window
  opened or closed since the last run.
- Validation: one composite validator per entity over a `di.xml` rule pool, reporting every error at once.
- Configuration defaults for `hryvinskyi_banner_slider/{image,video,media}` in `etc/config.xml`, read through
  `ImageConfig`, `VideoConfig` and the core-only `MediaConfig`.
- Option sources: `SliderOptions`, `BannerTypeOptions`, `SlideEffectOptions`, `AspectRatioOptions`,
  `StatusOptions`, `VariantFormatOptions`.
- Data patches `MigrateSliderScope`, `MigrateCropVariants`, `MigrateResponsiveItems`, `NormaliseLegacyRows`,
  `NormaliseLegacyLocations`, `BackfillBannerTimestamps` and `BackfillLegacyMedia`:
  - responsive items are converted from the 1.x maximum widths to the minimum widths that keep the 1.x layout, and
    are dropped for sliders whose `is_responsive` flag was off; every slider whose list differs from reading the
    widths as minimum widths is logged;
  - slider locations that are not valid location codes are rewritten (invalid characters become `_`) and logged
    `old → new`;
  - absolute media URLs are reduced to media paths only on the site's own hosts;
  - banner image sizes are stored; a 1.x "show the source as it is" crop of the banner image is removed (the banner
    image shows whole, as in 1.x), and one with a source of its own gets the largest centred area with the
    breakpoint's aspect ratio and is regenerated; unreadable files and failed regenerations are logged by id and
    never stop the upgrade.
- Regenerating a 1.x "show the source as it is" crop cuts the largest centred area of its source with the
  breakpoint's aspect ratio, so running `banner-slider:crops:regenerate` after copying a missing source into media
  finishes its migration.
- Search criteria `store_id` and `customer_group_id` filters on sliders accept `neq` / `nin` (the other sliders)
  besides `eq` / `in`; any other condition is refused.
- Resource limits: images above `ImagePixelLimit` (`di.xml` `maxPixels`, default 50 megapixels) are refused as
  uploads and never decoded; breakpoint target width and height are at most 5000 pixels.
- Uploaded JPEGs stored rotated or mirrored (EXIF orientation other than 1) are turned upright before they are
  stored (needs the `exif` extension; without it they are stored as they are and that is logged once).
- `hryvinskyi_banner_slider/image/jpeg_quality` (default 90): the quality JPEG crops are rendered at.
- `video/x-m4v` uploads are accepted and stored as `.mp4`.
- Changing a breakpoint's target size fits its crops' areas to the new aspect ratio (the largest centred part of
  each area) in the same save, and renders them again after the commit.
- `i18n/en_US.csv` with every phrase of the package.
- Unit tests for every class with logic.

### Changed
- Resource models save normally and handle relations in `_afterLoad` / `_afterSave`; the EntityManager wiring and
  `Slider/Relation/*` are gone.
- Models are typed, expose value objects (`ActiveWindow`, `Visibility`, `AspectRatio`, `CropRect`, …) and never
  throw on stored data.
- Repositories moved to `Model/Repository/`, search results to `Model/SearchResults/`; repositories validate on
  save and wrap unexpected failures in generic messages (details are logged).
- Dates are stored and compared in UTC; "now" comes from a PSR-20 clock.
- `responsive_items` is a JSON list of `{min_width, per_page, gap}` (min-width semantics).
- Moving a banner to another slider deletes its crops for the previous slider's breakpoints and their files;
  changing a banner's image deletes the crops cut from the previous image, unless the same save sends new ones.
- A new or changed banner image must be an upload of the package (under `banner_slider/image/`); an unchanged one
  stays valid wherever it lives. A crop may be cut from the banner image only while it is the stored one or an
  upload, so a protected media file cannot be published through a crop.
- Every crop and format check runs before the save's transaction starts; inside it only storage can fail.
- Crop output files of deleted crops, breakpoints, banners and sliders are removed once the deletion is committed
  (inside an outer transaction, after the outer commit); a rollback removes nothing.
- Media URLs percent-encode each path segment, so file names with spaces, `#`, `?` or `%` resolve.
- A stored link URL the URL policy rejects and stored custom CSS containing `<` read as empty (logged once at debug
  level), so a row written outside the setters never reaches a page.
- The slider locator logs, at debug level and once per request, a requested location that is not a valid code.
- The banner editor cleans the banner's and its sliders' cache tags after the commit.
- Video URLs must be playable by a registered video provider.
- The package media folders are no longer offered as media gallery folders to content editors.
- The video providers: one `LocalFile` provider with `local_mp4` / `local_webm` virtual types; YouTube accepts
  `watch`, `youtu.be`, `embed` and `shorts` URLs and embeds from `youtube-nocookie.com` in privacy-enhanced mode;
  Vimeo adds `dnt=1`.
- Server-encoded WebP is lossy (`cwebp -q <quality> -m 6`); 1.x encoded lossless.
- The `system/media_storage_configuration` keys are spelled `banner_slider_folder`, `banner_slider_image_folder`,
  `banner_slider_video_folder`.
- Requires PHP `~8.3.0||~8.4.0` and Magento 2.4.7+; every dependency is declared with a real constraint
  (`magento/module-store`, `module-customer`, `module-media-storage`, `module-cron`, `symfony/console`,
  `symfony/process`, `psr/log`, `psr/clock`, `hryvinskyi/image-binaries`, the API package `^2.0`).

### Removed
- The image entity: `Model/Image`, its resource model, collection, repository, search results and the
  `hryvinskyi_banner_slider_image` table.
- The search criteria filter classes (`Model/Banner/SearchCriteria/*`, `Model/Slider/SearchCriteria/*`).
- `UploadImage`, `Video/Upload`, `Video/VideoData`, `Video/VideoPathConfig`, `Video/Provider/AbstractProvider`,
  `LocalMp4`, `LocalWebm`, `Image/BinaryPathResolver`, `Image/FormatConverter`, `Image/CropProcessor`,
  `Image/ResponsiveImageGenerator`, `Image/UploadCompressedImages`, `Image/ImagePathConfig`,
  `Image/Converter/*`.
- `Observer/CreateDefaultBreakpointsOnSliderSave`, `etc/events.xml` and `Breakpoint/DefaultBreakpointsCreator`:
  default breakpoints come from the slider editor.
- `etc/acl.xml`: the ACL moved to the admin UI package with the same resource ids; the `::image*` ids are gone.
- Columns `store_ids`, `customer_group_ids`, `is_responsive` (slider); `generate_webp`, `webp_image`,
  `webp_quality`, `generate_avif`, `avif_image`, `avif_quality`, `sort_order` (crop). Redundant indexes.
- The dependency on `hryvinskyi/magento2-media-uploader`.

## [1.0.4] - 2026-02-03

### Fixed
- PHP declaration compatibility error with `Magento\Framework\Api\SearchResults::getItems()` by adding explicit `getItems(): array` method overrides to all SearchResults classes:
  - `BannerSearchResults`
  - `SliderSearchResults`
  - `ImageSearchResults`
  - `BreakpointSearchResults`
  - `ResponsiveCropSearchResults`

## [1.0.3] - 2026-02-02

### Added
- `custom_css` column to `hryvinskyi_banner_slider` table for custom CSS styles per slider
- `getCustomCss()` and `setCustomCss()` methods to `Slider` model

## [1.0.2] - 2026-01-31

### Added
- EntityManager observers for automatic FPC cache invalidation on slider/banner save and delete
- `getById()` method to `SliderLocator` for retrieving slider by ID with context validation

### Changed
- Refactored `SliderLocator` to eliminate code duplication between `getByLocation()` and `getById()` methods
- Removed unused `StoreManagerInterface` and `CustomerSession` dependencies from `SliderLocator`

### Fixed
- Full Page Cache now properly invalidates when sliders or banners are saved in admin

## [1.0.1] - 2026-01-31

### Added
- Alias setter methods for DataObjectHelper compatibility
### Changed
- Renamed autoplay methods to follow snake_case naming convention:
  - `isAutoplayEnabled()` → `isAutoPlayEnabled()`
  - `setAutoplayEnabled()` → `setAutoPlayEnabled()`
  - `getAutoplayTimeout()` → `getAutoPlayTimeout()`
  - `setAutoplayTimeout()` → `setAutoPlayTimeout()`

## [1.0.0] - 2026-01-31

### Added
- Initial release of Banner Slider core module
- Database schema with declarative schema:
  - `hryvinskyi_banner_slider` - Slider configuration table
  - `hryvinskyi_banner_slider_banner` - Banner content table
  - `hryvinskyi_banner_slider_image` - Responsive image variants table
  - `hryvinskyi_banner_slider_breakpoint` - Viewport breakpoints table
  - `hryvinskyi_banner_slider_responsive_crop` - Image crop configuration table
- Data models implementing API interfaces:
  - `Slider` model with cache tags and event prefixes
  - `Banner` model with three content type support
  - `Image`, `Breakpoint`, `ResponsiveCrop` models
- Repository implementations with full CRUD support:
  - `SliderRepository`
  - `BannerRepository`
  - `ImageRepository`
  - `BreakpointRepository`
  - `ResponsiveCropRepository`
- Image processing services:
  - `ResponsiveImageGenerator` - Generates responsive crops
  - `CropProcessor` - Processes crop coordinates
  - `BinaryWebpConverter` - WebP conversion via cwebp
  - `BinaryAvifConverter` - AVIF conversion
  - `FormatConverter` - Format transformation logic
  - `BinaryPathResolver` - Binary path resolution
  - `ImagePathConfig` - Image storage path management
- Video handling services:
  - `ProviderResolver` - Video provider resolution
  - `YouTube` provider
  - `Vimeo` provider
  - `LocalMp4` provider
  - `LocalWebm` provider
  - `VideoPathConfig` - Video storage path configuration
  - `Upload` service for video files
- Observers:
  - `CreateDefaultBreakpointsOnSliderSave` - Auto-creates default breakpoints
- Data patches:
  - `CreateDefaultBreakpointsForExistingSliders` - Migration for existing sliders
- ACL configuration for admin access control
- Media storage folder configuration
