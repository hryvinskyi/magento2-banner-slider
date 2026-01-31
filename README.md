# Magento 2 & Adobe Commerce Banner Slider

Core business logic and data persistence layer for the Banner Slider system.

> **Part of [hryvinskyi/magento2-banner-slider-pack](https://github.com/hryvinskyi/magento2-banner-slider-pack)** - Complete Banner Slider solution for Magento 2

## Description

This module implements all API contracts from BannerSliderApi and provides the database schema, data models, image processing capabilities, and video handling functionality.

## Features

- Complete implementation of all API interfaces
- Database schema with 5 optimized tables
- Image processing with WebP and AVIF conversion
- Video provider support (YouTube, Vimeo, Local MP4/WebM)
- Responsive image generation with breakpoint-based cropping
- Automatic default breakpoint creation for new sliders

## Database Schema

### Tables

| Table | Description |
|-------|-------------|
| `hryvinskyi_banner_slider` | Main slider storage with carousel configuration |
| `hryvinskyi_banner_slider_banner` | Banner content including images, videos, and custom HTML |
| `hryvinskyi_banner_slider_image` | Responsive image variants per banner |
| `hryvinskyi_banner_slider_breakpoint` | Viewport breakpoints per slider |
| `hryvinskyi_banner_slider_responsive_crop` | Image crops per banner/breakpoint combination |

### Slider Configuration

The slider table supports extensive configuration:

- **Animation**: Effect type, auto width/height, loop, lazy loading
- **Autoplay**: Enable autoplay, timeout duration
- **Navigation**: Arrow navigation, dot pagination
- **Responsive**: Responsive mode, items per breakpoint, preload count
- **Visibility**: Date range (from/to), store IDs, customer group IDs

### Banner Types

| Type | Value | Features |
|------|-------|----------|
| Image | 0 | Desktop image, responsive variants, alt text, link URL |
| Video | 1 | YouTube/Vimeo URL or local file, aspect ratio, background mode |
| Custom HTML | 2 | Full HTML content support |

## Image Processing

### WebP Conversion
- Uses cwebp binary for optimal compression
- Configurable quality settings (1-100)
- Automatic fallback if binary unavailable

### AVIF Conversion
- Modern next-gen image format support
- Configurable quality settings (1-100)
- Smaller file sizes than WebP

### Responsive Crops
- Per-breakpoint cropping with precise coordinates
- Source image preservation
- Automatic generation of WebP and AVIF variants

## Video Support

### Supported Providers

| Provider | Detection | Features |
|----------|-----------|----------|
| YouTube | youtube.com, youtu.be URLs | Iframe embed, background mode |
| Vimeo | vimeo.com URLs | Iframe embed, background mode |
| Local MP4 | .mp4 file extension | Native video element |
| Local WebM | .webm file extension | Native video element |

### Video Options
- Custom aspect ratio
- Background mode (no controls, autoplay, loop)
- Responsive sizing

## Observers

### CreateDefaultBreakpointsOnSliderSave
Automatically creates default responsive breakpoints when a new slider is saved:
- Desktop (1200px+)
- Tablet (768px - 1199px)
- Mobile (up to 767px)

## Dependencies

- PHP 8.1+
- magento/framework
- magento/module-store
- magento/module-customer
- hryvinskyi/magento2-banner-slider-api
- hryvinskyi/image-binaries
- symfony/process ^5.4 || ^6.0 || ^7.0

## Installation

This module is typically installed as part of the `hryvinskyi/magento2-banner-slider-pack` metapackage:

```bash
composer require hryvinskyi/magento2-banner-slider-pack
php bin/magento module:enable Hryvinskyi_BannerSlider
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Author

**Volodymyr Hryvinskyi**
- Email: volodymyr@hryvinskyi.com

## License

MIT
