<?php
// Run: ddev drush scr scripts/altcheck.php
// Checks the rendered <img alt> of every image SDC across the alt-text states:
// media alt (default), Decorative, per-placement override, empty override.
$r = \Drupal::service('renderer');
$img = ['src' => '/core/misc/druplicon.png', 'alt' => 'MEDIA', 'width' => 88, 'height' => 100];
$sdcs = [
  'jarvis:image' => ['image', []],
  'jarvis:card' => ['image', ['title' => 'T', 'variant' => 'image-top']],
  'jarvis:card ' => ['image', ['title' => 'T', 'variant' => 'image-left']],
  'jarvis:card-full-image' => ['image', []],
  'jarvis:image-overlay' => ['image', ['heading' => 'H']],
  'jarvis:person' => ['image', ['name' => 'N', 'variant' => 'card']],
  'jarvis:stat' => ['icon', ['title' => '1']],
];
$states = [
  'default' => [[], 'MEDIA'],
  'decorative' => [['decorative' => TRUE], ''],
  'override' => [['alt_override' => TRUE, 'alt_text' => 'CUSTOM'], 'CUSTOM'],
  'override, empty text' => [['alt_override' => TRUE], 'MEDIA'],
  'text without toggle' => [['alt_text' => 'CUSTOM'], 'MEDIA'],
  'decorative beats override' => [['decorative' => TRUE, 'alt_override' => TRUE, 'alt_text' => 'CUSTOM'], ''],
];
$fail = 0;
foreach ($sdcs as $id => [$prop, $base]) {
  foreach ($states as $label => [$extra, $want]) {
    $build = ['#type' => 'component', '#component' => trim($id), '#props' => $base + [$prop => $img] + $extra];
    $html = (string) $r->renderInIsolation($build);
    $got = preg_match('/<img[^>]*\salt="([^"]*)"/', $html, $m) ? $m[1] : '(no img alt)';
    $ok = $got === $want;
    $fail += $ok ? 0 : 1;
    printf("%s %-24s %-26s alt=\"%s\"\n", $ok ? 'ok  ' : 'FAIL', trim($id) . (($base['variant'] ?? '') ? " ({$base['variant']})" : ''), $label, $got);
  }
}
echo $fail === 0 ? "\nPASS\n" : "\n$fail FAILURE(S)\n";
