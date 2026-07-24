<?php

declare(strict_types=1);

namespace Drupal\jarvis_canvas;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      new TwigFilter('jarvis_image_style', [$this, 'imageStyle']),
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

      $files = $this->entityTypeManager->getStorage('file')
        ->loadByProperties(['filename' => urldecode(basename($path))]);
      // A basename match can hit a temporary upload or a private-scheme file
      // that happens to share the name. Only permanent public files are valid
      // here — the src we were handed is a public URL by construction.
      $file = NULL;
      foreach ($files as $candidate) {
        if ($candidate instanceof FileInterface
          && $candidate->isPermanent()
          && str_starts_with((string) $candidate->getFileUri(), 'public://')) {
          $file = $candidate;
          break;
        }
      }
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
