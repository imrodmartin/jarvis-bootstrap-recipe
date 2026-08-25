<?php

declare(strict_types=1);

namespace Drupal\jarvis_canvas;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Works out how opaque a component's image overlay has to be to pass WCAG AA.
 *
 * This is the server-side half of what js/contrast.js used to do in the
 * browser, and it exists because the browser version could not be trusted:
 *
 * - It downsampled every image to 16x16 and scored 4x4 blocks, so each block
 *   averaged a quarter of the image's width — far wider than a glyph. Two of
 *   the theme's own demo images came back needing *no* overlay when they
 *   actually needed ~0.48.
 * - It only ran after the image had loaded, so inaccessible text was painted
 *   first and corrected afterwards.
 * - A cross-origin image tainted the canvas and it could not sample at all.
 *
 * Computing it here fixes all three: the right opacity is in the markup on
 * first paint, the file is read directly off disk at whatever resolution we
 * like, and there is no canvas to taint. When the image cannot be read at all
 * we fall back to guaranteedFloor(), which is solved against the worst image
 * that could possibly exist rather than being a hand-picked constant.
 *
 * The solver mirrors js/wcag.js step for step so the editor badge and the
 * rendered page cannot disagree; JarvisOverlayAlphaTest pins them together.
 */
final class OverlayAlpha {

  /**
   * WCAG AA contrast ratio for body text.
   */
  public const NEED = 4.5;

  /**
   * Edge length, in pixels, the image is sampled down to.
   *
   * 64 with a 4px block makes each scored block 1/16 of the sampled edge —
   * about 6% of the image's width, which is glyph-scale. The old 16px sample
   * made each block a quarter of the width and averaged bright patches away.
   */
  private const SAMPLE = 64;

  /**
   * Edge length, in sampled pixels, of one scored block.
   */
  private const BLOCK = 4;

  /**
   * Relative luminance of white (#fff) — the light text colour.
   */
  private const TEXT = 1.0;

  public function __construct(
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * Relative luminance of #212529, the dark text colour.
   *
   * Computed rather than pasted: a hand-copied value shipped once in wcag.js
   * and every light overlay stopped raising short of AA.
   */
  public function darkText(): float {
    return $this->luminance([0x21, 0x25, 0x29]);
  }

  /**
   * The alpha that passes against the worst image that could exist.
   *
   * Pure white under a black overlay, pure black under a white one. Used
   * whenever the real image cannot be read, so the fallback is derived from
   * the same solver as everything else instead of being a magic number.
   */
  public function guaranteedFloor(bool $light_overlay): float {
    return $this->solve($light_overlay ? [0, 0, 0] : [255, 255, 255], $light_overlay, 0.0);
  }

  /**
   * The alpha needed over a real image file, at or above the author's floor.
   *
   * @param string $path
   *   An absolute, readable path to the image.
   * @param bool $light_overlay
   *   TRUE for a white overlay under dark text, FALSE for black under light.
   * @param float $floor
   *   The author's minimum, 0..1. Never lowered.
   * @param string $cache_key
   *   Identity of the file's current contents, so a replaced file resamples.
   *
   * @return float|null
   *   The alpha, or NULL when the file could not be sampled.
   */
  public function forFile(string $path, bool $light_overlay, float $floor, string $cache_key): ?float {
    $cid = 'jarvis_canvas:overlay_alpha:' . $cache_key . ':' . (int) $light_overlay;
    $cached = $this->cache->get($cid);
    if ($cached) {
      // Cached without the floor applied, so moving the slider does not
      // re-sample the image.
      return $cached->data === NULL ? NULL : max($floor, (float) $cached->data);
    }
    $extreme = $this->extremeBlock($path, $light_overlay);
    $needed = $extreme === NULL ? NULL : $this->solve($extreme, $light_overlay, 0.0);
    // Permanent: the key already carries the file's identity, so a changed
    // file lands on a different entry rather than needing invalidation.
    $this->cache->set($cid, $needed, CacheBackendInterface::CACHE_PERMANENT);
    return $needed === NULL ? NULL : max($floor, $needed);
  }

  /**
   * Smallest alpha at or above $floor that clears NEED over $colour.
   *
   * Steps by 0.02 exactly as js/wcag.js does; the two must agree.
   */
  public function solve(array $colour, bool $light_overlay, float $floor): float {
    $text = $light_overlay ? $this->darkText() : self::TEXT;
    $a = $floor;
    while ($a < 0.95) {
      $blended = $light_overlay ? $this->lighten($colour, $a) : $this->darken($colour, $a);
      if ($this->contrast($text, $this->luminance($blended)) >= self::NEED) {
        break;
      }
      $a += 0.02;
    }
    return $a;
  }

  /**
   * The worst-case block in the image: brightest for light text, darkest for
   * dark text. Scoring the extreme rather than the whole-image average is the
   * point — text can cross a bright patch an average would hide.
   *
   * @return array|null
   *   An [r, g, b] average, or NULL when the image could not be decoded.
   */
  private function extremeBlock(string $path, bool $light_overlay): ?array {
    if (!function_exists('imagecreatetruecolor') || !is_readable($path)) {
      return NULL;
    }
    $source = @imagecreatefromstring((string) file_get_contents($path));
    if ($source === FALSE) {
      return NULL;
    }
    $canvas = imagecreatetruecolor(self::SAMPLE, self::SAMPLE);
    // A transparent PNG samples as black without this, which would tell us a
    // white overlay is needed on an image that is really whatever shows
    // through. White is the safer assumption for light text.
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagealphablending($canvas, TRUE);
    imagecopyresampled(
      $canvas, $source,
      0, 0, 0, 0,
      self::SAMPLE, self::SAMPLE,
      imagesx($source), imagesy($source)
    );
    imagedestroy($source);

    $best = NULL;
    $best_luminance = $light_overlay ? 2.0 : -1.0;
    for ($by = 0; $by < self::SAMPLE; $by += self::BLOCK) {
      for ($bx = 0; $bx < self::SAMPLE; $bx += self::BLOCK) {
        $r = $g = $b = 0;
        for ($y = $by; $y < $by + self::BLOCK; $y++) {
          for ($x = $bx; $x < $bx + self::BLOCK; $x++) {
            $rgb = imagecolorat($canvas, $x, $y);
            $r += ($rgb >> 16) & 0xFF;
            $g += ($rgb >> 8) & 0xFF;
            $b += $rgb & 0xFF;
          }
        }
        $n = self::BLOCK ** 2;
        $colour = [$r / $n, $g / $n, $b / $n];
        $luminance = $this->luminance($colour);
        if ($light_overlay ? $luminance < $best_luminance : $luminance > $best_luminance) {
          $best_luminance = $luminance;
          $best = $colour;
        }
      }
    }
    imagedestroy($canvas);
    return $best;
  }

  /**
   * A black overlay at alpha $a scales each sRGB channel by (1 - a).
   */
  private function darken(array $c, float $a): array {
    return [$c[0] * (1 - $a), $c[1] * (1 - $a), $c[2] * (1 - $a)];
  }

  /**
   * A white overlay at alpha $a blends each channel toward 255.
   */
  private function lighten(array $c, float $a): array {
    return [
      $c[0] + (255 - $c[0]) * $a,
      $c[1] + (255 - $c[1]) * $a,
      $c[2] + (255 - $c[2]) * $a,
    ];
  }

  private function channel(float $c): float {
    $c /= 255;
    return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
  }

  private function luminance(array $c): float {
    return 0.2126 * $this->channel($c[0])
      + 0.7152 * $this->channel($c[1])
      + 0.0722 * $this->channel($c[2]);
  }

  private function contrast(float $a, float $b): float {
    $hi = max($a, $b);
    $lo = min($a, $b);
    return ($hi + 0.05) / ($lo + 0.05);
  }

}
