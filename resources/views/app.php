<?php
$this->set_layout($GLOBALS['template_factory']->open('layouts/base'));

$plugin = app()->get(StudIPPlugin::class);

$vite = new Studip\Vite\Manifest(
    manifestPath: __DIR__ . '/../../dist/.vite/manifest.json',
    basePath: $plugin->getPluginUrl() . '/dist/',
    dev: false
);
$tags = $vite->createTags('resources/js/app.js');

foreach ($tags->preload as $tag) {
    PageLayout::addHeadElement(...$tag);
}
foreach ($tags->css as $tag) {
    PageLayout::addHeadElement(...$tag);
}
foreach ($tags->js as [, $attributes]) {
    PageLayout::addScript($attributes['src'], array_diff_key($attributes, ['src' => true]));
}

// TODO:
PageLayout::addStylesheet($vite->getURL('style.css'));
?>

<div id="app" data-page="<?php echo htmlReady(json_encode($page)); ?>"></div>
