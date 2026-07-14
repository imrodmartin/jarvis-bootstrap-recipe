<?php

declare(strict_types=1);

namespace Drupal\jarvis_canvas;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
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

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
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
    $image_style = ImageStyle::load($style);
    if (!$image_style instanceof ImageStyleInterface) {
      return NULL;
    }
    // image.src maps to src_with_alternate_widths — a URL whose query string
    // ends in a different filename (…jarvis-banner.png.avif?itok=…). Strip the
    // query before basename or the file lookup matches nothing.
    $path = (string) parse_url($src, PHP_URL_PATH);
    $files = $this->entityTypeManager->getStorage('file')
      ->loadByProperties(['filename' => urldecode(basename($path))]);
    $file = reset($files);
    if (!$file instanceof FileInterface) {
      return NULL;
    }
    $uri = (string) $file->getFileUri();
    if (!$image_style->supportsUri($uri)) {
      return NULL;
    }
    return $this->fileUrlGenerator->transformRelative($image_style->buildUrl($uri));
  }

}
