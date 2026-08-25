<?php

declare(strict_types=1);

namespace Drupal\jarvis_canvas;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig filter to apply an image style to a Canvas image src URL.
 *
 * The Jarvis image SDC receives its image as a resolved public URL
 * (canvas.module/image → src built with File::createFileUrl()). Image styles,
 * however, need the original stream-wrapper URI (public://…). Canvas never
 * populates a stream-wrapper prop on a plain component insert, so the built-in
 * |image_style filter has nothing to work with.
 *
 * This filter bridges the gap the same way Canvas's own ImageAndStyleAdapter
 * does: look the file up by basename, then build the styled URL from its URI.
 *
 * ponytail: basename lookup, exactly like ImageAndStyleAdapter. Collides only
 * if two managed files share a filename; upgrade to a fid-carrying prop if that
 * ever bites.
 */
final class JarvisCanvasTwigExtension extends AbstractExtension {

  /**
   * Resolved styled URLs, keyed by "$style|$path".
   *
   * The filter runs once per rendered image and the file lookup is an
   * unindexed property query, so without this a listing of N images issued N
   * queries for the same handful of files.
   */
  private array $cache = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly RendererInterface $renderer,
    private readonly FileSystemInterface $fileSystem,
    private readonly OverlayAlpha $overlayAlphaCalculator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      new TwigFilter('jarvis_image_style', [$this, 'imageStyle']),
      new TwigFilter('jarvis_overlay_alpha', [$this, 'overlayAlpha']),
    ];
  }

  /**
   * Returns the styled, root-relative URL for an image, or NULL to fall back.
   *
   * @param string|null $src
   *   The image src as delivered to the component (a public file URL).
   * @param string $style
   *   The image style machine name.
   */
  public function imageStyle(?string $src, string $style): ?string {
    if (!$src) {
      return NULL;
    }
    // image.src maps to src_with_alternate_widths — a URL whose query string
    // ends in a different filename (…jarvis-banner.png.avif?itok=…). Strip the
    // query before basename or the file lookup matches nothing.
    $path = (string) parse_url($src, PHP_URL_PATH);
    $key = $style . '|' . $path;
    if (array_key_exists($key, $this->cache)) {
      $this->bubble($this->cache[$key]['cacheability']);
      return $this->cache[$key]['url'];
    }

    $cacheability = new CacheableMetadata();
    $url = NULL;

    $image_style = $this->entityTypeManager->getStorage('image_style')
      ->load($style);
    if ($image_style instanceof ImageStyleInterface) {
      // Editing the style must invalidate every page that rendered a
      // derivative through it.
      $cacheability->addCacheableDependency($image_style);

      $file = $this->resolveFile($path);
      if ($file !== NULL) {
        // Replacing the file must invalidate the same pages.
        $cacheability->addCacheableDependency($file);
        $uri = (string) $file->getFileUri();
        if ($image_style->supportsUri($uri)) {
          $url = $this->fileUrlGenerator->transformRelative($image_style->buildUrl($uri));
        }
      }
    }

    $this->cache[$key] = ['url' => $url, 'cacheability' => $cacheability];
    $this->bubble($cacheability);
    return $url;
  }

  /**
   * Looks up the permanent public file behind a rendered image path.
   *
   * A basename match can hit a temporary upload or a private-scheme file that
   * happens to share the name. Only permanent public files are valid here —
   * the src we were handed is a public URL by construction.
   */
  private function resolveFile(string $path): ?FileInterface {
    $files = $this->entityTypeManager->getStorage('file')
      ->loadByProperties(['filename' => urldecode(basename($path))]);
    foreach ($files as $candidate) {
      if ($candidate instanceof FileInterface
        && $candidate->isPermanent()
        && str_starts_with((string) $candidate->getFileUri(), 'public://')) {
        return $candidate;
      }
    }
    return NULL;
  }

  /**
   * Returns the overlay opacity that keeps text on this image at WCAG AA.
   *
   * Replaces the browser-side js/contrast.js pass. Doing it here means the
   * correct value is in the markup on first paint instead of being corrected
   * after the image loads, and the file is sampled off disk at a resolution
   * the old 16x16 canvas could not reach. See OverlayAlpha for why that
   * mattered.
   *
   * Anything we cannot read — a remote src, a Views-fed URL, a file that is
   * not a managed public file, a build without GD — falls back to the alpha
   * that passes against the worst image that could exist. That is darker than
   * such an image probably needs, and it is never wrong.
   *
   * @param string|null $src
   *   The image src as delivered to the component.
   * @param bool $light_overlay
   *   TRUE for a white overlay under dark text, FALSE for black under light.
   * @param float|int|string $floor
   *   The author's minimum opacity, 0..1. Only ever raised.
   *
   * @return string
   *   The opacity, to two decimals, for the style attribute.
   */
  public function overlayAlpha(?string $src, bool $light_overlay, float|int|string $floor = 0): string {
    $floor = max(0.0, min(1.0, (float) $floor));
    $fallback = static fn (float $value): string => number_format($value, 2, '.', '');

    $path = $src === NULL ? '' : (string) parse_url($src, PHP_URL_PATH);
    if ($path === '') {
      return $fallback(max($floor, $this->overlayAlphaCalculator->guaranteedFloor($light_overlay)));
    }

    $key = 'overlay|' . (int) $light_overlay . '|' . $path;
    if (!array_key_exists($key, $this->cache)) {
      $cacheability = new CacheableMetadata();
      $alpha = NULL;
      $file = $this->resolveFile($path);
      if ($file !== NULL) {
        // Replacing the file must invalidate every page that sampled it.
        $cacheability->addCacheableDependency($file);
        $uri = (string) $file->getFileUri();
        $real = $this->fileSystem->realpath($uri);
        if (is_string($real)) {
          // The cache id carries size and mtime so a replaced file resamples
          // without anyone having to clear anything.
          $identity = $uri . '|' . (int) @filesize($real) . '|' . (int) @filemtime($real);
          $alpha = $this->overlayAlphaCalculator->forFile($real, $light_overlay, 0.0, $identity);
        }
      }
      $this->cache[$key] = ['alpha' => $alpha, 'cacheability' => $cacheability];
    }

    $this->bubble($this->cache[$key]['cacheability']);
    $alpha = $this->cache[$key]['alpha']
      ?? $this->overlayAlphaCalculator->guaranteedFloor($light_overlay);
    return $fallback(max($floor, $alpha));
  }

  /**
   * Bubbles cacheability into the surrounding render context.
   *
   * A Twig filter returns a bare string, so there is no render array to hang
   * metadata on. Rendering a metadata-only stub inside the active context is
   * the standard way to get it to bubble (twig_tweak does the same).
   */
  private function bubble(CacheableMetadata $cacheability): void {
    $build = [];
    $cacheability->applyTo($build);
    $this->renderer->render($build);
  }

}
