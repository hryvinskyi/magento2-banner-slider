# Magento 2 & Adobe Commerce Banner Slider — core

Persistence and business logic of the Banner Slider: it implements every contract of
[`hryvinskyi/magento2-banner-slider-api`](https://github.com/hryvinskyi/magento2-banner-slider-api) and owns the
database schema, the media files, the image pipeline, the video providers, the CLI commands and the cron jobs.

> **Part of [hryvinskyi/magento2-banner-slider-pack](https://github.com/hryvinskyi/magento2-banner-slider-pack)**,
> together with the admin UI and the storefront UI. Those packages talk to this one only through the API package.

## Requirements

- PHP `~8.3.0 || ~8.4.0`
- Magento 2.4.7 or later (`magento/framework ^103.0.7`)
- `Magento_Store`, `Magento_Customer`, `Magento_MediaStorage`, `Magento_Cron`
- `symfony/process` and `symfony/console`, `psr/log`, `psr/clock`
- `hryvinskyi/image-binaries` (ships the `cwebp` and `cavif` command-line encoders in `vendor/bin`)

## Installation

Install it through the pack:

```bash
composer require hryvinskyi/magento2-banner-slider-pack:^2.0
bin/magento setup:upgrade
bin/magento cache:flush
```

Upgrading from 1.x? Read [Upgrading from 1.x](#upgrading-from-1x) **before** running `setup:upgrade`.

## What the core provides

### Entities and storage

| Table | Holds |
|-------|-------|
| `hryvinskyi_banner_slider` | Sliders: carousel options, responsive items (JSON list), active window, custom CSS, `all_customer_groups` flag |
| `hryvinskyi_banner_slider_store` | Store views a slider shows in (store `0` = all store views) |
| `hryvinskyi_banner_slider_customer_group` | Customer groups a slider shows to, when `all_customer_groups` is off |
| `hryvinskyi_banner_slider_banner` | Banners: image (with its pixel size), video, custom HTML, link, active window, position |
| `hryvinskyi_banner_slider_breakpoint` | Breakpoints of a slider: identifier, media query, minimum width, target size |
| `hryvinskyi_banner_slider_responsive_crop` | One crop per banner and breakpoint: source image, crop area, original-format output |
| `hryvinskyi_banner_slider_crop_variant` | Extra formats of a crop (WebP, AVIF, …): format, quality, generated path |

Visibility rules:
- A slider with no store rows is visible nowhere. The save validator requires at least one store view.
- With `all_customer_groups` off, only the listed groups see the slider; no group rows means **nobody** sees it.
- Dates are stored in UTC and exposed as an `ActiveWindow`.

The models (`Model/Slider`, `Banner`, `Breakpoint`, `ResponsiveCrop`) never throw on stored data: an unknown banner
type or an unparsable value falls back to a documented default, so the admin can always open a row to fix it.
Their cache identities include their parents (a crop tags its banner, a breakpoint its slider).

### Services (all behind API interfaces, wired in `etc/di.xml`)

| API interface | Core implementation | What it does |
|---------------|--------------------|--------------|
| `SliderRepositoryInterface`, `BannerRepositoryInterface`, `BreakpointRepositoryInterface`, `ResponsiveCropRepositoryInterface` | `Model/Repository/*` | Single-entity CRUD; `save()` validates first. Deleting a banner or a slider removes the crop folders of the deleted banners once the delete is committed. |
| `Validation\*ValidatorInterface` | `Model/Validation/*` | One composite per entity over a `di.xml` pool of rules; every error is reported in one `ValidationException`. |
| `Slider\SliderEditorInterface` | `Model/Slider/SliderEditor` | Saves a slider with its full breakpoint set in one transaction. A new slider saved without breakpoints gets the default set (desktop, tablet, mobile; `DefaultBreakpoints` in `di.xml`). |
| `Banner\BannerEditorInterface` | `Model/Banner/BannerEditor` | Saves a banner with its crops in one transaction (see [Crop pipeline](#crop-pipeline)). |
| `ResponsiveCrop\CropRegeneratorInterface` | `Model/ResponsiveCrop/CropRegenerator` | Re-encodes the crops of a banner on the server. |
| `Slider\SliderLocatorInterface` | `Model/Slider/SliderLocator` | The enabled, visible, active slider for a location or an id; the lowest `priority` value wins, then the lowest id. |
| `Banner\VisibleBannersProviderInterface` | `Model/Banner/VisibleBannersProvider` | Enabled banners active at a moment, by position. |
| `Picture\PictureSourcesProviderInterface` | `Model/Picture/PictureSourcesProvider` | `<picture>` sources of many banners in a few queries, widest breakpoint first, preferred format first. |
| `Media\ImageUploadInterface`, `Media\VideoUploadInterface` | `Model/Media/ImageUpload`, `VideoUpload` | Uploads, type sniffed from the bytes, size capped by configuration. |
| `Media\MediaUrlResolverInterface` | `Model/Media/MediaUrlResolver` | Media URL of a stored path for the current store. |
| `Image\ImageFormatRegistryInterface` | `Model/Image/ImageFormatRegistry` | The image formats the package knows (`di.xml` pool). |
| `Config\ImageConfigInterface`, `Config\VideoConfigInterface` | `Model/Config/*` | The admin settings below. |
| `Video\ProviderResolverInterface` | `Model/Video/ProviderResolver` | Picks the video provider for a URL or a stored path. |

The package dispatches no events of its own. Cache cleaning goes through Magento's `clean_cache_by_tags` event, so
Varnish is purged too.

### Crop pipeline

A banner save runs in this order:
1. Every input is checked first (banner rules, image size, crop areas, source images, requested formats).
2. A transaction starts and the banner is saved, so a new banner has the id its file names need.
3. The new crop files are written, then the crop and variant rows are saved or deleted.
4. The transaction commits. Only then are the replaced files deleted.

On any failure the transaction rolls back and only the files this save created are deleted.

- **File names** come from the core, never from the request:
  `banner_slider/responsive/<banner id>/<breakpoint identifier>_<first 12 characters of the SHA-256 of the bytes>.<extension>`.
  Any change of source, area, target size or quality gives a new name, so browsers and CDNs never serve a stale crop.
- **Original format:** the crop's original-format output is JPEG when the source is JPEG, and PNG for every other
  source (PNG, GIF, WebP, AVIF). It keeps transparency and every browser can decode it. A requested variant in the
  same format as the original is ignored.
- **Browser-encoded crops** are untrusted. They must be within the upload size cap, their sniffed type must match
  the declared format, they must decode, and their size must match the breakpoint target within 1 px. Anything
  missing or rejected is encoded on the server. If a requested format can be produced neither way, the save fails
  and names the breakpoint and the format.
- **Allowed crop sources:** the crop's current source, the banner image, or a file under the package upload folders
  (DI argument `sourceRoots` of `Model/ResponsiveCrop/CropChangeValidator`; default `image` and `breakpoint`). Other
  media files, such as customer uploads, are refused.
- **Moving a banner to another slider** deletes its crops for the previous slider's breakpoints in the same
  transaction; their files are removed after the commit.
- Replaced files are deleted only under `banner_slider/responsive/`, and never while a row still references them.
- Inside an outer transaction (a data patch), the editor's commit does not reach the database until the outer
  commit, but replaced files are already removed. Patches that only create banners are unaffected.

### Media

All file access goes through Magento's `Filesystem` (`MediaStorage`), so remote media storage works. Libraries
that need a local path get a temporary copy under `var/tmp/hryvinskyi_banner_slider` (`LocalFileWorkspace`).

- **Read:** any safe media-relative path (no `..`, NUL byte, backslash or scheme), wherever it lives.
- **Write, move, delete:** only under the package roots `banner_slider/{image,responsive,video,breakpoint,tmp}`
  (DI argument `roots` of `Model/Media/MediaPaths`).
- Uploads land in `banner_slider/{image,video}/<yyyy>/<mm>/<name>-<hash>.<ext>`. `is_uploaded_file()` is required
  for HTTP uploads.

### Video providers

| Provider | Code | Source |
|----------|------|--------|
| `Model/Video/Provider/YouTube` | `youtube` | `watch?v=`, `youtu.be/`, `shorts/`, `embed/`; privacy-enhanced embeds use `youtube-nocookie.com` |
| `Model/Video/Provider/Vimeo` | `vimeo` | vimeo.com URLs; privacy-enhanced embeds add `dnt=1` |
| `Model/Video/Provider/LocalFile` (virtual types) | `local_mp4`, `local_webm` | Uploaded files under `banner_slider/video/` |

## Configuration

Defaults live in `etc/config.xml`; the admin fields (section `hryvinskyi_banner_slider`) ship with the admin UI
package.

| Path | Default | Meaning |
|------|---------|---------|
| `hryvinskyi_banner_slider/image/default_formats` | `webp` | Variant formats pre-selected for new crops |
| `hryvinskyi_banner_slider/image/webp_quality` | `85` | Default WebP quality |
| `hryvinskyi_banner_slider/image/avif_quality` | `80` | Default AVIF quality |
| `hryvinskyi_banner_slider/image/max_upload_size_mb` | `10` | Image upload cap, also the cap for browser-encoded crops |
| `hryvinskyi_banner_slider/video/privacy_enhanced` | `1` | Privacy-enhanced YouTube and Vimeo embeds |
| `hryvinskyi_banner_slider/video/max_upload_size_mb` | `100` | Video upload cap |
| `hryvinskyi_banner_slider/media/orphan_sweep_enabled` | `0` | Lets the daily orphan sweep cron delete files |

## CLI commands

```bash
# Re-encode crops on the server from their source image and crop area
bin/magento banner-slider:crops:regenerate [--banner=<id>] [--breakpoint=<id>]

# Delete media files under the package folders that nothing references (older than the grace period)
bin/magento banner-slider:media:cleanup [--dry-run]
```

The regenerator continues past a failing crop and reports every failure at the end. The cleanup command always
runs when invoked, whatever the cron setting. Run it with `--dry-run` first.

## Cron jobs

| Job | Schedule | What it does |
|-----|----------|--------------|
| `hryvinskyi_banner_slider_refresh_scheduled_content` | every 5 minutes | Cleans the cache tags of sliders and banners whose active window opened or closed since the last run (last run stored in the flag `hryvinskyi_banner_slider_schedule_refresh`), plus their location tags |
| `hryvinskyi_banner_slider_sweep_orphan_media` | daily 03:30 | Deletes unreferenced files older than the grace period. **Does nothing** until `hryvinskyi_banner_slider/media/orphan_sweep_enabled` is `1` |

The sweep counts a file as referenced when any banner, crop or variant row points at it, including legacy values
in the form they are migrated to, or when a banner's content or a slider's custom CSS quotes it. The grace period is
the DI argument `gracePeriodSeconds` of `Model/Media/OrphanMediaSweeper` (default `86400`, 24 hours), so an upload
whose form was never saved survives a day.

## Extending

### Add an image format

1. Register the format in `etc/di.xml` on `Hryvinskyi\BannerSlider\Model\Image\ImageFormatRegistry`, argument
   `formats`: `mime`, `extension`, optional `aliases`, `variant` (`true` to offer it as a crop variant) and
   `preference` (higher is preferred in `<picture>`).
2. Add at least one encoder: a class implementing `Hryvinskyi\BannerSlider\Model\Image\Encoder\ImageEncoderInterface`
   (`getFormatCode()`, `isAvailable()`, `encode($source, $destination, $quality)`), listed under the format code in
   the `encoders` argument of `Hryvinskyi\BannerSlider\Model\Image\ImageConverter`. Encoders are tried in order; the
   first available one that succeeds wins. Command-line encoders can extend `AbstractBinaryEncoder`; binaries are
   looked up in the `directories` of `Model/Process/BinaryLocator` (default `vendor/bin`).

No contract changes: the admin, the storefront `<picture>` and the variant table pick up the new format from the
registry.

Built-in encoders:

| Format | Encoders, in order |
|--------|--------------------|
| WebP | GD (`imagewebp`), `cwebp` (lossy, `-q <quality> -m 6`, full-quality alpha) |
| AVIF | Imagick, GD (`imageavif`), `cavif` |
| PNG | GD |

### Add a video provider

Implement `Hryvinskyi\BannerSliderApi\Api\Video\ProviderInterface` (`getCode()`, `getPriority()`, `supports()`,
`parse()`, `getEmbedKind()`, `getEmbedUrl()`, `getEmbedAttributes()`) and add it to the `providers` argument of
`Hryvinskyi\BannerSlider\Model\Video\ProviderResolver`. The resolver asks the highest priority first.

For another local file type, add a virtual type of `Model/Video/Provider/LocalFile` with its `code` and `extension`,
and the extension with its MIME type to the `allowedTypes` argument of `Model/Media/VideoUpload`.

### Add a validation rule

Implement `Model/Validation/Rule/{Slider,Banner,Breakpoint,ResponsiveCrop}RuleInterface` and add it to the `rules`
argument of the entity's validator in `di.xml`.

## Upgrading from 1.x

**The migration is one-way.** It moves data into new tables and drops the old columns, and 1.x cannot run on the
migrated schema. **Back up the `hryvinskyi_banner_slider*` tables before `setup:upgrade`**; that dump is the only
way back.

Install all Banner Slider packages at 2.0 together (the pack does this). After the deploy, flush the full-page
cache and purge Varnish, because cached pages reference 1.x storefront assets.

What the data patches do (each logs what it changed to the Magento log):

- **Store views and customer groups** move from the comma-separated `store_ids` / `customer_group_ids` columns into
  `hryvinskyi_banner_slider_store` and `hryvinskyi_banner_slider_customer_group`. The legacy "all groups" value
  becomes the `all_customer_groups` flag. The old columns are then dropped.
- **Sliders 1.x hid stay hidden.** 1.x hid a slider with an empty store or group list, and so does 2.0: such a
  slider gets no store rows (visible nowhere) or no group rows (visible to nobody). It is never published by the
  upgrade.
- **Some rows are disabled:** a slider or banner whose window ended before it started was never shown by 1.x. It is
  disabled and its end is set to its start, so it can be opened and fixed. The log lists their ids.
- **Crop formats** move from the `generate_webp`, `webp_image`, `webp_quality`, `generate_avif`, `avif_image` and
  `avif_quality` columns into `hryvinskyi_banner_slider_crop_variant`. The old columns are then dropped.
- **Responsive items** are converted from the object shape keyed by viewport width to a list of
  `{min_width, per_page, gap}`, ascending (min-width semantics). 1.x handed its keys to the slider as **maximum**
  widths, so `{"0":{"items":1},"768":{"items":3}}` showed 3 slides up to 768 px and one above. The migration keeps
  that layout: it becomes `[{"min_width":0,"per_page":3},{"min_width":769,"per_page":1}]`, not the list the keys
  would give read as minimum widths. A key of `0` matched no screen in 1.x and is not carried over. Every slider
  whose list differs from that naive reading is logged as a warning with both values: review those sliders.
  Sliders whose `is_responsive` flag was off (1.x ignored their items) get no responsive items, and the flag
  column is dropped. Out-of-range values are clamped and logged.
- **Slider locations** must be location codes (letters, digits, `_`, `-`, at most 255 characters). A 1.x location
  that is not one is rewritten: surrounding spaces go, every other character becomes `_` (`home page` → `home_page`),
  and a location with nothing left is removed. Each rewrite is logged as a warning `slider <id> location "<old>" →
  "<new>"`. **Change every layout `location` argument and widget that names an old location to the new one**; two
  sliders may now share a location, and the lower priority value shows.
- **Media paths** become media-relative. A bare 1.x `video_path` file name becomes `banner_slider/video/<name>`,
  which is where 1.x played it from. An absolute URL is reduced to its media path only when it points into media on
  one of the store views' base URL hosts; a URL on another host is kept and logged. Paths outside the package
  folders are kept, and they are read and rendered but never written or deleted.
- **Image sizes and whole-image crops:** every banner image gets its pixel size stored (1.x read it from disk on
  every render). 1.x stored "show the source as it is" in two ways: an area of all zeros, or a crop whose output
  file is its source image itself.
  - When that source is the banner image (the crop has no source of its own, or its own is the banner image), the
    crop is removed: the storefront shows the banner image whole, as 1.x did.
  - A crop with a source of its own gets the largest area of that source with the breakpoint's aspect ratio,
    centred (the whole source when the breakpoint leaves the height open), and is regenerated, so the storefront
    gets a file of the size it reserves.

  A missing or unreadable file, or a crop that cannot be regenerated, is logged with its id and skipped; it does
  not stop the upgrade. **Copy `pub/media` from the 1.x site before `setup:upgrade`.** Crops whose source was missing
  keep their 1.x file; once the media is in place, run `bin/magento banner-slider:crops:regenerate` (it cuts such a
  crop from the same centred area).
- Banner `created_at` / `updated_at` values that were never written are filled with the current time.
- Existing sliders without breakpoints get the default breakpoints.
- Dropped by the declarative schema: the `hryvinskyi_banner_slider_image` table and the crop `sort_order`
  column. Redundant indexes are dropped, and the banner full-text index no longer covers
  `image`.

Behaviour to know about:

- **Dates:** 1.x compared the stored schedule with UTC "now", so the stored values were already UTC. 2.0 keeps
  them, so the storefront behaves the same, but the admin form now shows them in the store's time zone: a schedule
  entered as `10:00` on a UTC+2 store appears as `12:00`.
- **The orphan media sweep is off** until you set `hryvinskyi_banner_slider/media/orphan_sweep_enabled` to `1`. Run
  `bin/magento banner-slider:media:cleanup --dry-run` first and check its list.
- **ACL:** the ACL resources moved to the admin UI package and keep their `Hryvinskyi_BannerSlider::` ids, so role
  assignments keep working. The `Hryvinskyi_BannerSlider::image*` resources are gone.
- **Removed from core:** the image entity (model, resource model, collection, repository, search results), the
  search criteria filter classes, the EntityManager wiring, `UploadImage`, `Video/Upload`, `Video/VideoPathConfig`,
  the `Image/{BinaryPathResolver,FormatConverter,CropProcessor,ResponsiveImageGenerator,UploadCompressedImages,ImagePathConfig}`
  classes and the binary converters, the `CreateDefaultBreakpointsOnSliderSave` observer and `DefaultBreakpointsCreator`,
  and `etc/acl.xml`. Removed API contracts are listed in the API package changelog.
- `hryvinskyi/magento2-media-uploader` is no longer used: uploads are handled by this package.
- Server-encoded WebP is lossy (quality from configuration); 1.x encoded lossless.

## License

MIT. Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
