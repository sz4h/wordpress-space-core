<?php

namespace Space\Core\Abstracts;

defined( 'ABSPATH' ) || exit;

use Space\Core\Contracts\ModuleInterface;

abstract class AbstractModule implements ModuleInterface {

    public function __construct( protected string $slug ) {}

    public function get_slug(): string {
        return $this->slug;
    }

    /** Override in subclass to run on plugin activation. */
    public function on_activate(): void {}
}
