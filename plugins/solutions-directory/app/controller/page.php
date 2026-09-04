<?php

namespace Vvveb\Plugins\SolutionsDirectory\Controller;

/**
 * Thin delegate for the two static annuaire pages.
 *
 * The routes /annuaire and /annuaire/referencer-une-solution need a fixed slug,
 * which Vvveb supplies through a route data key. Data keys are merged into a
 * route's reverse-lookup "parameters" list (system/routes.php::processRoute),
 * and Routes::url() returns the FIRST route of a module whose parameters are
 * all satisfied. Registering those two routes directly on content/page/index
 * therefore hijacked the reverse map: every generated page URL (feeds,
 * sitemaps, menus) collapsed onto /annuaire/referencer-une-solution.
 *
 * Pointing them at this module keeps the fixed slug while leaving the
 * content/page/index reverse map untouched. Behaviour is the stock page
 * controller: the slug arrives in $_GET exactly as it would otherwise.
 */
class Page extends \Vvveb\Controller\Content\Page {
}
