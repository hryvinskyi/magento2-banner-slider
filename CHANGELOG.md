# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
