<?php
/**
 * Minimal stubs for WordPress core classes referenced by the plugin.
 *
 * The plugin's PHPUnit suite runs without a WordPress runtime, so core
 * classes like WP_Post and WP_Taxonomy don't exist. Code under test uses
 * these only as type guards (is_a($x, 'WP_Post'), $tax instanceof WP_Taxonomy),
 * so empty user-defined classes in the global namespace are sufficient.
 *
 * This is the same pattern used by 10up/wp_mock and brain/monkey at
 * bootstrap time. Once the suite migrates to one of those libraries, this
 * file can be removed.
 */

if (!class_exists('WP_Post')) {
    class WP_Post
    {
    }
}

if (!class_exists('WP_Taxonomy')) {
    class WP_Taxonomy
    {
        public $public = true;
    }
}
