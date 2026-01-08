<?php

/**
 * @file
 *  RusaPermRegForm.php
 *
 * @Created 
 *  2020-06-05 - Paul Lieberman
 *
 * RUSA Permanents Registration
 *
 * Provides registration for the Perm Program
 * as well as ride registration.
 *
 * @todo
 *    Add validation for membership on prog reg submit
 *
 * ----------------------------------------------------------------------------------------
 * 
 */

namespace Drupal\rusa_perm_reg\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\rusa_perm_reg\RusaPermReg;
use Drupal\rusa_perm_reg\RusaRideRegData;
use Drupal\rusa_perm_reg\RusaPermRegUtil;
use Drupal\rusa_api\RusaPermanents;
use Drupal\rusa_api\Client\RusaClient;

/**
 * RusaPermRegForm
 *
 * This is the Drupal Form class.
 * All of the form handling is within this class.
 *
 */
class RusaPermRegForm extends FormBase {

  protected $settings;          // Array from config form
  protected $uinfo;             // Array of user data
  protected $permReg;           // RusaPermReg object 
  protected $rideregdata;       // RusaRideRegData object
  protected $regstatus;         // Array of registration status keyed by year
  protected $step = 'start';    // Text
  protected $pid;               // Perm ID
  protected $this_year;         // Year
  protected $next_year;         // Year

  /**
   * @getFormID
   *
   * Required
   *
   */
  public function getFormId() {
    return 'rusa_perm_reg_form';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxy $current_user) {

    // Store some account data 
    $this->uinfo = RusaPermRegUtil::get_user_info($current_user);

    // Create a permReg object and inititate a query
    $this->permReg = new RusaPermReg();
    $this->permReg->query($this->uinfo['uid']);

    // Create a rideReg object and initiate a query
    $this->rideregdata = new RusaRideRegData($this->uinfo['uid']);

    // Read the settings from the config entities        
    $this->settings['prog'] = \Drupal::config('rusa_perm_reg.settings')->getRawData();
    $this->settings['ride'] = \Drupal::config('rusa_perm_ride.settings')->getRawData();

    // Store current year
    $this->this_year = date('Y');

    // Get the program registration status
    $this->regstatus[$this->this_year] = $this->permReg->getRegStatus($this->this_year);

    // In December start checking next year's registration
    if (date('m') > 11) {
      $this->next_year = date('Y', strtotime('+1 year'));
      $this->regstatus[$this->next_year] = $this->permReg->getRegStatus($this->next_year);
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
    );
  }


  /**
   * @buildForm
   *
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Start the form
    $form = $this->startForm();

    // Good to ride message
    $form += $this->getStatusMessage();

    // December only message
    $form += $this->decemberMessage();

    // Program registration
    $form += $this->getProgReg();

    // PayPal Payment
    $form += $this->getPayLink();

    // Ride Registration
    $form += $this->getRideReg();

    // Attach the Javascript and CSS, defined in rusa_api.libraries.yml.
    // $form['#attached']['library'][] = 'rusa_api/rusa_script';
    $form['#attached']['library'][] = 'rusa_api/rusa_style';

    return $form;
  }

  /**
   * @validateForm
   *
   * Required
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Triggering element will be either 'submitride' or 'submitprog'
    $action = $form_state->getTriggeringElement()['#parents'][0];

    // Validate ride registration    
    if ($this->step === 'ridereg' && $action === 'submitride') {
      // Check route validity
      $pid = trim($form_state->getValue('pid'));
      if (!empty($pid)) {
        $route_valid = $this->is_route_valid($pid);

        if ($route_valid == 'sr') {
          // Compute the error message for SR-600                
          $link = $this->get_sr_link();
          $msg = $this->settings['ride']['sr'];
          $msg = str_replace('[perm:id]', $pid, $msg);
          $msg = str_replace('[perm:link]', $link, $msg);

          $form_state->setErrorByName('pid', $this->t($msg));
        }
        elseif ($route_valid == 'inactive') {
          $form_state->setErrorByName('pid', $this->t('Permanent route %pid is not active.', ['%pid' => $pid]));
        }
        elseif ($route_valid == 'invalid') {
          $form_state->setErrorByName('pid', $this->t('%pid is not a valid permanent route number.', ['%pid' => $pid]));
        }
        elseif ($route_valid == 'valid') {
          $this->pid = $pid;
        }
      }
    }

  } // End function validate


  /**
   * @submitForm
   *
   * Required
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Triggering element will be either 'submitride' or 'submitprog'
    $action = $form_state->getTriggeringElement()['#parents'][0];

    // For ridereg we redirect to select form
    if ($action === 'submitride') {
      $pid = $form_state->getValue('pid');
      if (empty($pid)) {
        $form_state->setRedirect('rusa_perm.select');
      }
      else {
        $form_state->setRedirect('rusa_perm.select', ['pid' => $pid]);
      }
    }
    elseif ($action === 'submitprog') {
      // If it's December then we no longer care about this year
      $year = empty($this->next_year) ? $this->this_year : $this->next_year;

      // Check for existing program registration       
      if (!$this->regstatus[$year]['reg_exists']) {

        // New registration
        $reg = $this->permReg->newProgReg($this->uinfo['uid'], $this->uinfo['mid'], $year);

        // Could add a test to the returned $reg object

        $this->messenger()->addStatus($this->t('Your perm program registration has been saved.', []));
        $this->logger('rusa_perm_reg')->notice('New perm program registration.', []);

        $form_state->setRedirect('rusa_perm.reg', ['user' => $this->uinfo['uid']]);
      }
      else {
        $this->messenger()->addStatus($this->t('Program registration aleady exists for %year.', ['%year' => $year]));
      }

    }

  }

  /* Private Functions */

  /**
   * Get a table of current registrations
   *
   */
  protected function get_current_registrations($ridedata) {
    $rows = [];

    // Step through each registration
    foreach ($ridedata as $id => $reg) {
      $row = [];
      $links = [];

      // Build the RWGPS link
      if (!empty($reg['url'])) {
        $url = URL::fromUri($reg['url']);
        $url->setOption('attributes', ['target' => '_blank']);
        $reg['url'] = Link::fromTextAndUrl('Ride With GPS', $url)->toString();
      }

      // Add the data
      foreach ($reg as $key => $val) {
        $row[] = $val;
      }

      // If ridedate is future we show cancel
      if ($reg['ride_date'] > date('Y-m-d')) {

        $url = Url::fromRoute('rusa_perm.cancel', ['regid' => $id]);
        $url->setOption('attributes', ['onclick' => 'if(!confirm("Do you really want to cancel this ride registration?")){return false;}']);
        $links['cancel'] = [
          'title' => $this->t('Cancel registration'),
          'url' => $url,
        ];
      }
      else {
        $url =
          $links['results'] = [
            'title' => $this->t('Submit results'),
            'url' => Url::fromRoute('rusa_perm.submit', ['regid' => $id]),
          ];
      }


      // Add operations links
      $row[] = [
        'data' => [
          '#type' => 'operations',
          '#links' => $links,
        ],
      ];

      $rows[] = $row;
    }
    ;

    return [
      '#type' => 'table',
      '#header' => ['Route #', 'Ride Date', 'Name', 'Km (unpaved)', 'Climb (ft.)', 'Description', 'Route', 'Operations'],
      '#rows' => $rows,
      '#responsive' => TRUE,
      '#attributes' => ['class' => ['rusa-table']],
    ];
  }


  /**
   * Get a table of current registrations
   *
   */
  protected function get_perm($pid) {

    $perm = $this->rideregdata->getPerm($pid);

    $dist_display = $perm->dist;
    if ($perm->dist_unpaved > 0) {
      $dist_display .= " ($perm->dist_unpaved u)";
    }
    $row = [
      'pid' => $pid,
      'pname' => $perm->name,
      'pdist' => $dist_display,
      'pclimb' => $perm->climbing,
      'pdesc' => $perm->description,
    ];

    return [
      '#type' => 'table',
      '#header' => ['Route #', 'Name', 'Km', 'Climb (ft.)', 'Description'],
      '#rows' => [$row],
      '#responsive' => TRUE,
      '#attributes' => ['class' => ['rusa-table']],
    ];
  }


  /**
   * Build a link to the payment page
   *
   */
  protected function get_pay_link($year) {
    $regid = $this->permReg->get_reg_id($year);
    $url = Url::fromRoute('rusa_perm.pay');
    $url->setOption('query', ['mid' => $this->uinfo['mid'],
      'regid' => $regid,
      'year' => $year]);

    $url->setOption('attributes', ['target' => '_blank']);
    return Link::fromTextAndUrl('Proceed to the payment page', $url)->toString();
  }

  /**
   * Check if route is valid
   *
   */
  protected function is_route_valid($pid) {
    $perms = new RusaPermanents(['key' => 'pid', 'val' => $pid]);
    if (!$perms->getPermanents()) {
      return 'invalid';
    }
    if ($perms->isInactive($pid)) {
      return 'inactive';
    }
    if ($perms->isSr($pid) == '1') {
      return 'sr';
    }
    return 'valid';

  }

  /**
   *
   * Build a link to the SR-600 page
   *
   */
  protected function get_sr_link() {
    $url = Url::fromRoute('rusa_perm.sr');
    return Link::fromTextAndUrl('SR-600 page', $url)->toString();
  }

  /**
   *
   * Return the new $form array
   */
  protected function startForm() {

    $form[] = [
      '#title' => $this->t('Program Registration'),
    ];

    // Display member name and # so they know who they are
    $form['user'] = [
      '#type' => 'item',
      '#markup' => $this->t($this->uinfo['name'] . ' RUSA #' . $this->uinfo['mid']),
    ];
    return $form;
  }

  /**
   *
   * Returns form elements for new registration
   */
  protected function getProgReg() {
    $form = [];

    // If it's December then we no longer care about this year
    $year = empty($this->next_year) ? $this->this_year : $this->next_year;

    // If no prog registration then show new registration button        
    if (!$this->regstatus[$year]['reg_exists']) {
      // New Program Registration

      // Check to see if membership is valid for this year
      $expyear = date("Y", strtotime($this->uinfo['expdate']));
      if ($year > $expyear) {
        // Membership not valid for registration year
        $form['memberexp'] = [
          '#type' => 'item',
          '#markup' =>
            $this->t('Your RUSA membership expires on %expdate. You must renew your membership before you can register for the %year Perm Program',
              ['%expdate' => $this->uinfo['expdate'], '%year' => $year]),
        ];
      }
      else {
        // Display a submit button to create a new program registration          
        $this->step = 'progreg';

        $form['submitprog'] = [
          '#type' => 'submit',
          '#value' => 'Register for the ' . $year . ' Perm Program',
        ];
      }
    }
    return $form;
  }

  /**
   *
   * Returns form elements for payment link
   */
  protected function getPayLink() {
    $form = [];

    foreach ($this->regstatus as $year => $reg) {
      if ($reg['reg_exists'] && !$reg['payment']) {
        // Display payment link
        $form['payment'] = [
          '#type' => 'item',
          '#markup' => $this->t('You must now submit payment for the %year perm program.', ['%year' => $year]),
        ];

        // Display a link to the payment page
        $pay_link = $this->get_pay_link($year);

        $form['paylink'] = [
          '#type' => 'item',
          '#markup' => $pay_link,
        ];
      }
    }
    return $form;
  }

  /**
   *
   * Returns form elements for a good to go status message
   */
  protected function getStatusMessage() {
    $form = [];
    foreach ($this->regstatus as $year => $reg) {
      if ($reg['payment']) {
        $form['regstatus' . $year] = [
          '#type' => 'item',
          '#markup' => $this->t('You are registered to ride permanents for %year', ['%year' => $year]),
        ];
      }
    }
    return $form;
  }

  /**
   *
   * Returns form elements for the December message
   */
  protected function decemberMessage() {
    $form = [];
    if (!empty($this->next_year)) {
      $form['decmessage'] = [
        '#type' => 'item',
        '#markup' => $this->t('Registration for the %next_year permanents program is now open.  If you register now, your registration will include permanents program registration for December %this_year.',
          ['%next_year' => $this->next_year, '%this_year' => $this->this_year]),
      ];
    }
    return $form;
  }



  /*
   *
   * Returns form elements for ride registration
   */
  protected function getRideReg() {
    $form = [];

    // Ride regitration only if program registration is complete
    // If it's December then next year's registration is good enough
    // So we only care if there is ANY valid registration

    // Note: At this point we don't know the data of the ride they want to do,
    // So we cannot trap if they don't have a program registration for next year yet.
    // We will have to do that after the fact.

    // Show existing perm ride registrations
    if ($ridedata = $this->rideregdata->get_registrations()) {
      $form['rideregtop'] = [
        '#type' => 'item',
        '#markup' => $this->t('<h3>Your current perm registrations.</h3>')
      ];
      $form['ridereg'] = $this->get_current_registrations($ridedata);
    }


    $do_rde_reg = FALSE;
    foreach ($this->regstatus as $year => $status) {
      if ($status['reg_exists'] && $status['payment']) {
        $do_ride_reg = TRUE;
      }
    }

    if ($do_ride_reg) {
      // Register for perm ride             
      $form['rideperm'] = [
        '#type' => 'item',
        '#markup' => $this->t('<h3>Register to ride a permanent.</h3>'),
      ];

      $form['rideinstruct'] = [
        '#type' => 'item',
        '#markup' => $this->t($this->settings['ride']['instructions']),
      ];

      $form['pid'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Route #'),
        '#size' => 6,
      ];

      $form['submitride'] = [
        '#type' => 'submit',
        '#value' => 'Find a route and register to ride',
      ];

      $this->step = 'ridereg';
    }
    return $form;
  }




} // End of class  

