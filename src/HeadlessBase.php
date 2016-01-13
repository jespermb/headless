<?php

/**
 * @file
 * Definition of Drupal\headless\Controller\HeadlessBase
 */

namespace Drupal\headless;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormState;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Serializer;

/**
 * Headless utility base class.
 */
class HeadlessBase implements ContainerInjectionInterface {

  /**
   * Request stack instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * Serializer instance.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  private $serializer;

  /**
   * General constructor.
   *
   * @param RequestStack $requestStack
   * @param Serializer   $serializer
   */
  public function __construct(RequestStack $requestStack, Serializer $serializer) {
    $this->requestStack = $requestStack;
    $this->serializer   = $serializer;
  }

  /**
   * @inheritdoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('serializer')
    );
  }

  /**
   * Retrieves an instance of the JsonResponse object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse | null
   *   Response represents an HTTP response in JSON format.
   */
  public function response($data = NULL, $status = 200, $headers = array()) {
    return new JsonResponse($data, $status, $headers);
  }

  /**
   * Retrieves an instance of the Request object.
   *
   * @return \Symfony\Component\HttpFoundation\Request | null
   *   Request represents an HTTP request or null.
   */
  public function request() {
    return $this->requestStack->getCurrentRequest();
  }

  /**
   * Retrieves, populates, and processes a form.
   *
   * @param string $class
   *   Defines a class.
   *
   * @param array $params
   *  HTTP request parameters.
   *
   * @return mixed
   *   Form values or errors | undefined
   */
  public function submitForm($class, $params) {

    // Create FormState instance, set values, and submit.
    $form_state = (new FormState())->setValues($params);

    \Drupal::formBuilder()->submitForm($class, $form_state);

    if ($form_state->hasAnyErrors()) {

      // Returns an associative array of error messages.
      return array(
        'error' => $form_state->getErrors(),
      );
    }
    else {

      // Returns the submitted and sanitized form values.
      return array(
        'data' => $form_state->getValues(),
      );
    }
  }

  /**
   * Process the client-side POST request and send response.
   *
   * @param string $class
   *   Defines a form class.
   *
   * @param callable $callback
   *   Defines a callback function.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response represents an HTTP response in JSON format.
   */
  public function processRequest($class, $callback = NULL) {
    $response = $this->response();

    // Get the Request body JSON
    $content = $this->request()->getContent();

    // Decode as array and submit the form.
    $params = $this->serializer->decode($content, 'json');
    $output = $this->submitForm($class, $params);

    // Form submission success.
    if (isset($output['data'])) {

      // Execute pre-process callback, if provided.
      if (is_callable($callback)) {
        $callback($output['data']);
      }

      $response->setStatusCode($response::HTTP_ACCEPTED);
    }

    // Errors exist.
    elseif (isset($output['error'])) {
      $response->setStatusCode($response::HTTP_BAD_REQUEST);
    }
    else {
      $response->setStatusCode($response::HTTP_FORBIDDEN);
    }

    // Return the response.
    return $response->setData($output);
  }
}
