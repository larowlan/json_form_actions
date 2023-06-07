<?php

declare(strict_types=1);

namespace Drupal\json_form_actions\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines a route subscriber to duplicate all form routes to support JSON.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($collection as $name => $route) {
      if ($route->hasDefault('_form') && !$route->hasDefault('_controller')) {
        $get_route = clone $route;
        $get_route->setDefault('_controller', 'json_form_actions.form_controller:getContentResult');
        $get_route->setRequirement('_format', 'json');
        $get_route->setMethods(['GET']);
        $get_route->setOption('no_cache', TRUE);
        $collection->add(sprintf('%s.json_form_actions.get', $name), $get_route);

        $post_route = clone $get_route;
        $post_route->setDefault('_controller', 'json_form_actions.form_controller:getSubmittedResult');
        $post_route->setRequirement('_format', 'json');
        $post_route->setMethods(['POST']);
        $post_route->setOption('no_cache', TRUE);
        $collection->add(sprintf('%s.json_form_actions.post', $name), $post_route);
      }
    }
  }

}
