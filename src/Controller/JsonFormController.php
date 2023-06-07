<?php

declare(strict_types=1);

namespace Drupal\json_form_actions\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\HtmlFormController;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a controller for responding to forms with JSON.
 */
final class JsonFormController extends HtmlFormController {

  /**
   * {@inheritdoc}
   */
  public function getContentResult(Request $request, RouteMatchInterface $route_match): JsonResponse {
    // Todo #process, #pre_render, #lazy_builder, #access.
    $form = parent::getContentResult($request, $route_match);
    if (array_key_exists('form_token', $form)) {
      $token = $this->formBuilder->renderFormTokenPlaceholder($form['form_token']['#default_value']);
      $form['form_token']['#default_value'] = $token['#markup'];
    }
    return (new JsonResponse($form))->setPrivate();
  }

  /**
   * Submits the form and returns the result.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Form response.
   */
  public function getSubmittedResult(Request $request, RouteMatchInterface $route_match): Response {
    $body = $request->getContent();
    $fields = Json::decode($body);
    if (!is_array($fields)) {
      return (new JsonResponse(['errors' => ['form' => new TranslatableMarkup('Invalid JSON')]], 401))->setPrivate();
    }
    foreach ($fields as $field_name => $value) {
      $request->request->set($field_name, $value);
    }
    $form_arg = $this->getFormArgument($route_match);
    $form_object = $this->getFormObject($route_match, $form_arg);

    // Add the form and form_state to trick the getArguments method of the
    // controller resolver.
    $form_state = new FormState();
    $request->attributes->set('form', []);
    $request->attributes->set('form_state', $form_state);
    $args = $this->argumentResolver->getArguments($request, [
      $form_object,
      'buildForm',
    ]);
    $request->attributes->remove('form');
    $request->attributes->remove('form_state');

    // Remove $form and $form_state from the arguments, and re-index them.
    unset($args[0], $args[1]);
    $form_state->addBuildInfo('args', array_values($args));

    try {
      $this->formBuilder->buildForm($form_object, $form_state);
    }
    catch (EnforcedResponseException $e) {
      $form_state->setResponse($e->getResponse());
    }
    if ($errors = $form_state->getErrors()) {
      return (new JsonResponse(['errors' => $errors], 401))->setPrivate();
    }
    if (!$form_state->isSubmitted()) {
      return (new JsonResponse(['errors' => ['form' => new TranslatableMarkup('An error occurred submitting the form')]], 401))->setPrivate();
    }
    if ($form_state->getResponse() && $form_state->isSubmitted()) {
      new JsonResponse(['info' => ['form' => new TranslatableMarkup('Submitted')]], 201);
    }
    if ($redirect = $form_state->getRedirect()) {
      if ($redirect instanceof RedirectResponse) {
        $redirect->setContent(Json::encode(['notice' => ['form' => new TranslatableMarkup('Redirecting to :uri', [':uri' => $redirect->getTargetUrl()])]]))->setPrivate();
        $redirect->headers->set('Content-Type', 'application/json');
        return $redirect;
      }
      if ($redirect instanceof Url) {
        $redirect_url = $redirect->setAbsolute()->toString();
        $response = (new JsonResponse([
          'notice' => [
            'form' => new TranslatableMarkup('Redirecting to :uri', [
              ':uri' => $redirect_url
            ])
          ]
        ], 301))->setPrivate();
        $response->headers->set('Location', $redirect_url);
        return $response;
      }
    }
    return new JsonResponse(['info' => ['form' => new TranslatableMarkup('Submitted')]], 201);
  }


}
