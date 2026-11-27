<?php

/**
 * @file
 * Checks the Hero text card keeps its text at WCAG AA on real images.
 *
 * Run: ddev drush scr scripts/check-hero-text-card.php
 *
 * Renders jarvis:hero over up to six of the site's own managed images, with
 * and without a text card, and asserts for every combination:
 *
 * - no card: output is the auto-raised overlay, as before the card existed;
 * - card on: the image overlay is exactly the author's value, the card is
 *   never below the author's floor, and overlay + card together
 *   (1 - (1 - overlay)(1 - card)) reach what OverlayAlpha says the image
 *   needs.
 *
 * ponytail: checks the opacity arithmetic in hero.twig against the solver, not
 * pixels. A screenshot-based contrast pass lives in nobody's CI; add one if
 * the card ever gains colours other than black and white.
 */

use Drupal\Core\Render\RenderContext;

$renderer = \Drupal::service('renderer');
$extension = \Drupal::service('jarvis_canvas.twig_extension');
$url_generator = \Drupal::service('file_url_generator');

$images = [NULL];
$storage = \Drupal::entityTypeManager()->getStorage('file');
$fids = $storage->getQuery()->accessCheck(FALSE)
  ->condition('status', 1)
  ->condition('filemime', 'image/%', 'LIKE')
  ->condition('uri', 'public://%', 'LIKE')
  ->range(0, 6)
  ->execute();
foreach ($storage->loadMultiple($fids) as $file) {
  $images[] = $url_generator->generateString($file->getFileUri());
}

$cases = [
  'no card, overlay 10' => ['overlay' => 10],
  'no card, overlay 0' => ['overlay' => 0],
  'card none, overlay 10' => ['overlay' => 10, 'box_background' => 'none', 'box_opacity' => 85],
  'dark card, overlay 0, card 0' => ['overlay' => 0, 'box_background' => 'dark', 'box_opacity' => 0],
  'dark card, overlay 20, card 30' => ['overlay' => 20, 'box_background' => 'dark', 'box_opacity' => 30],
  'dark card, overlay 0, card 85' => ['overlay' => 0, 'box_background' => 'dark', 'box_opacity' => 85],
  'dark card, overlay 100, card 0' => ['overlay' => 100, 'box_background' => 'dark', 'box_opacity' => 0],
  'dark card, nothing set' => ['box_background' => 'dark'],
  'light card, overlay 0, card 0' => ['overlay' => 0, 'box_background' => 'light', 'box_opacity' => 0, 'text_color' => 'light'],
  'light card, overlay 30, card 10' => ['overlay' => 30, 'box_background' => 'light', 'box_opacity' => 10],
];

$failures = 0;
foreach ($images as $src) {
  foreach ($cases as $label => $props) {
    $build = [
      '#type' => 'component',
      '#component' => 'jarvis:hero',
      '#props' => $props + ['heading' => 'Heading', 'subheading' => 'Subheading'],
    ];
    if ($src) {
      $build['#props']['image'] = ['src' => $src, 'alt' => '', 'width' => 1600, 'height' => 900];
    }
    $html = (string) $renderer->renderInIsolation($build);

    preg_match('/<section class="([^"]*)"/', $html, $section);
    preg_match('/class="jarvis-bg__overlay[^"]*" style="opacity: ([^;]*);/', $html, $overlay);
    preg_match('/class="jarvis-hero__card-bg" style="opacity: ([^;]*);/', $html, $card);
    $wants_card = in_array($props['box_background'] ?? 'none', ['dark', 'light'], TRUE);
    $dark_text = str_contains($section[1], 'jarvis-hero--text-dark');
    $overlay_alpha = isset($overlay[1]) ? (float) $overlay[1] : 0.0;
    $card_alpha = isset($card[1]) ? (float) $card[1] : 0.0;

    $problems = [];
    if ($wants_card !== isset($card[1])) {
      $problems[] = 'card layer ' . ($wants_card ? 'missing' : 'present without a card');
    }
    if ($wants_card && $dark_text !== ($props['box_background'] === 'light')) {
      $problems[] = 'text colour does not follow the card';
    }
    if ($wants_card && isset($props['box_opacity']) && $card_alpha + 1e-9 < $props['box_opacity'] / 100) {
      $problems[] = 'card below the author floor';
    }
    if ($src) {
      $need = (float) $renderer->executeInRenderContext(new RenderContext(), fn () => $extension->overlayAlpha($src, $dark_text, 0));
      if ($wants_card && isset($props['overlay']) && abs($overlay_alpha - $props['overlay'] / 100) > 1e-9) {
        $problems[] = 'overlay is not the exact author value';
      }
      if (1 - (1 - $overlay_alpha) * (1 - $card_alpha) + 1e-9 < $need) {
        $problems[] = sprintf('overlay %.2f + card %.2f is short of the %.2f the image needs', $overlay_alpha, $card_alpha, $need);
      }
    }
    if ($problems) {
      $failures++;
      print 'FAIL ' . ($src ?? '(no image)') . " | $label | " . implode('; ', $problems) . "\n";
    }
  }
}

print $failures
  ? "$failures FAILED\n"
  : 'OK: ' . count($images) * count($cases) . ' hero renders across ' . (count($images) - 1) . " images\n";
