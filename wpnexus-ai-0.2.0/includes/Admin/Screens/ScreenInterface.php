<?php
namespace WPNexusAI\Admin\Screens;

if (!defined('ABSPATH')) { exit; }

interface ScreenInterface {
    public function render(): void;
}
