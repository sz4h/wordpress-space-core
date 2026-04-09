<?php

namespace Space\Core\Contracts;

defined( 'ABSPATH' ) || exit;

interface ModuleInterface {

    /** Called when the module is enabled and should register its hooks. */
    public function boot(): void;

    public function get_slug(): string;

    public function get_label(): string;

    public function get_description(): string;
}
