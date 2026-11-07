/**
 * @file
 * Canvas client-side transform: keep a text item's chosen format.
 *
 * Canvas's stock `mainProperty` transform reduces the text_textarea widget's
 * `{value, format}` item to the bare value, so the format the editor picked
 * never reaches the component model and the server resets it to the first
 * allowed format. Passing the whole item through fixes that -- but only when
 * the item is complete. The format <select> can fire a form-state change on
 * its own (before CKEditor has registered the textarea value), and a
 * `{format}`-only item makes the server treat the prop as empty and DROP it,
 * which then crashes the component's Twig render. Mirror `mainProperty` for
 * that case: no value, no change.
 *
 * Load order: this file ships in Drupal's classic JS aggregate, while the
 * Canvas UI bundle is a `type=module` script that runs later and REPLACES
 * `Drupal.canvasTransforms` wholesale (ui/src/main.tsx). A plain property
 * assignment here would be wiped. Install through an accessor instead, so the
 * transform is re-attached to whatever object Canvas assigns, whichever side
 * runs first.
 *
 * @see \Drupal\jarvis_canvas\Hook\JarvisCanvasHooks::fieldWidgetInfoAlter()
 * @see canvas/docs/redux-integrated-field-widgets.md, "Defining your own transform"
 */
((Drupal) => {
  const jarvisTextItem = (value) => {
    const item = Array.isArray(value) ? value[0] : value;
    if (item === null || typeof item !== 'object' || !('value' in item)) {
      return null;
    }
    return item;
  };
  const install = (transforms) => {
    if (transforms && typeof transforms === 'object') {
      transforms.jarvisTextItem = jarvisTextItem;
    }
    return transforms;
  };
  let current = install(Drupal.canvasTransforms || {});
  Object.defineProperty(Drupal, 'canvasTransforms', {
    configurable: true,
    enumerable: true,
    get: () => current,
    set: (transforms) => {
      current = install(transforms);
    },
  });
})(Drupal);
