<?php

/**
 * @file
 *  RusaPermSelectForm.php
 *
 * @Created 
 *  2020-05-14 - Paul Lieberman
 *
 * Provide a form for selecting permanents
 * Part of a Perm registration system
 *
 * @Modified
 *   3/4/2021 - PL Added last reviewed and previous results fields.
 * ----------------------------------------------------------------------------------------
 * 
 */

namespace Drupal\rusa_perm_reg\Form;

use Drupal\rusa_api\RusaPermanents;
use Drupal\rusa_api\RusaStates;
use Drupal\rusa_perm_reg\RusaPermReg;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * RusaPermForm
 *
 * This is the Drupal Perm class.
 * All of the form handling is within this class.
 *
 */
class RusaPermSelectForm extends ConfirmFormBase {

  // Instance variables
  protected $step;
  protected $uinfo;
  protected $perms;
  protected $pid;
  protected $perm;
  protected $progreg;

  /**
   * @getFormID
   *
   * Required
   *
   */
  public function getFormId() {
    return 'rusa_perm_select_form';
  }

  /**
   * @Constructor
   *
   * Initialize our region data before we do anything else
   */
  public function __construct(AccountProxy $current_user) {

    $this->uinfo = $this->get_user_info($current_user);
    $this->progreg = new RusaPermReg();
    $this->progreg->query($this->uinfo['uid']);
    $this->step = 'search';
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
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('rusa_perm.select');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t("Is this the perm you want to ride?");
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t("Please confirm your selection.");
  }

  /**
   * @buildForm
   *
   * Required
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Don't continue unless user has valid program registration
    if (!$this->progreg->progRegIsValid()) {
      $this->messenger()->addWarning($this->t("You are not registered for the perm program."));
      return $this->redirect('rusa_perm.reg', ['user' => $this->uinfo['uid']]);
    }

    // PID can be passed as a query parameter
    $pid = \Drupal::request()->query->get('pid');
    if (!empty($pid)) {
      $this->pid = trim($pid);
      // get the selected perm
      $perm = $this->get_perm($this->pid);

      // If perm is not valid
      if (!$perm) {
        $this->step = 'invalid';
        return $this->redirect('rusa_perm.select');
      }
      else {
        $this->perm = $perm;
        $this->step = 'confirm';
      }
    }

    /**
     * Search form
     *
     */
    if ($this->step === 'search') {

      // Get the states from the database
      $stateobj = new RusaStates();
      $states = $stateobj->getStates(3);
      array_unshift($states, "All States");

      $form['state'] = [
        '#type' => 'select',
        '#title' => $this->t('Starting location'),
        '#options' => $states,
        '#required' => FALSE,
      ];

      // Distance select
      $form['dist'] = [
        '#type' => 'select',
        '#title' => $this->t('Distance'),
        '#options' => [
          '0' => 'All distances',
          '100' => '100-199 km',
          '200' => '200-299 km',
          '300' => '300-399 km',
          '400' => '400-599 km',
          '600' => '600+ km'],
      ];

      // Minimum unpaved
      $form['min_dist_unpaved'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Min unpaved'),
        '#size' => '10',
        '#default_value' => '0',
      ];

      // Minimum climbing
      $form['min_climbing'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Min climbing (ft)'),
        '#size' => '10',
        '#default_value' => '0',
      ];

      // Shape
      $form['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Shape'),
        '#options' => [
          'ALL' => 'All shapes',
          'LOOP' => 'Loop',
          'OB' => 'Out and Back',
          'PP' => 'Point to Point',
        ],
      ];

      // Name
      $form['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name includes'),
        '#size' => '40',
      ];

      // Actions wrapper
      $form['actions'] = [
        '#type' => 'actions'
      ];

      // Default submit button 
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Find permanents'),
      ];

    }

    /**
     * Select form
     *
     */
    elseif ($this->step === 'select') {

      // build a table of perms 
      if (empty($this->perms)) {
        $form['empty'] = [
          '#type' => 'item',
          '#markup' => $this->t('There are no routes that match your query'),
        ];
      }
      else {
        foreach ($this->perms as $pid => $perm) {
          $dist_display = $perm->dist;
          if ($perm->dist_unpaved > 0) {
            $dist_display .= " ($perm->dist_unpaved)";
          }
          $row = [
            $perm->pid,
            $perm->startstate . ': ' . $perm->startcity,
            $dist_display,
            $perm->climbing,
            $perm->name,
            $perm->statelist,
            $perm->datereviewed,
            $this->t($perm->description),
          ];

          $links['select'] = [
            'title' => $this->t('Ride this'),
            'url' => Url::fromRoute('rusa_perm.select', ['pid' => $perm->pid]),
          ];

          // Add operations links
          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];

          $rows[] = $row;
        }

        $form['select'] = [
          '#type' => 'table',
          '#header' => ['Route #', 'Location', 'Km (unpaved)', 'Climb (ft)', 'Name', 'States', 'Last reviewed', 'Description'],
          '#rows' => $rows,
          '#responsive' => TRUE,
          '#attributes' => ['class' => ['rusa-table']],
        ];
      }
      // Actions wrapper
      $form['actions'] = [
        '#type' => 'actions'
      ];

      // Default submit button 
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Search again'),
      ];
    }

    // Confirmation step
    elseif ($this->step === 'confirm') {

      $form = parent::buildForm($form, $form_state);
      $form['perm'] = $this->perm;
      $form['remember'] = [
        '#type' => 'item',
        '#markup' => $this->t('Remember the Route #, you’ll need to re-enter it on the waiver.'),
      ];

      $form['actions']['submit']['#value'] = $this->t('Sign the waiver');

    }

    // Set class and attach the Javascript and CSS
    $form['#attributes']['class'][] = 'rusa-form';
    $form['#attached']['library'][] = 'rusa_api/chosen';
    $form['#attached']['library'][] = 'rusa_api/rusa_script';
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
    $action = $form_state->getTriggeringElement();
    if ($this->step === 'select' && $action['#type'] == 'submit') {
      $form_state->setRebuild();
      $this->step = 'search';
      return;
    }
  }

  /**
   * @submitForm
   *
   * Required
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getTriggeringElement();
    $values = $form_state->getValues();

    // Don't submit the form until after confirmation
    if ($this->step === 'select') {
      $form_state->setRebuild();
      $this->step = 'confirm';
      return;
    }
    elseif ($this->step == 'search') {

      $params = [];
      // State is the only valid query param we can use with the gdbm2json gateway.
      if (!empty($values['state'])) {
        $params = ['key' => 'startstate', 'val' => $values['state']];
      }

      // Get all active perms
      $permobj = new RusaPermanents($params);

      // Now set the filters
      $filters['active'] = TRUE; // Only active perms
      $filters['nosr'] = TRUE; // No SR600s

      if (!empty($values['dist'])) {
        $filters['dist'] = $values['dist'];
      }
      if (!empty($values['min_dist_unpaved'])) {
        $filters['min_dist_unpaved'] = $values['min_dist_unpaved'];
      }
      if (!empty($values['min_climbing'])) {
        $filters['min_climbing'] = $values['min_climbing'];
      }
      if ((!empty($values['type'])) && ($values['type'] != 'ALL')) {
        $filters['type'] = $values['type'];
      }
      if (!empty($values['name'])) {
        $filters['name'] = $values['name'];
      }

      // Now get the perms
      $this->perms = $permobj->getPermanentsQuery($filters);

      $this->step = 'select';
      $form_state->setRebuild();
      return;
    }
    elseif ($this->step === 'confirm') {
      // Route has been selected redirect to SmartWaiver
      $url = $this->smartwaiver_url($this->pid);
      $response = new TrustedRedirectResponse($url);
      $form_state->setResponse($response);

    }
  }



  /* Private Functions */


  /** 
   * Get user info
   *
   */
  protected function get_user_info($current_user) {
    $user_id = $current_user->id();
    $user = User::load($user_id);

    $uinfo['uid'] = $user_id;
    $uinfo['name'] = $user->get('field_display_name')->getValue()[0]['value'];
    $uinfo['fname'] = $user->get('field_first_name')->getValue()[0]['value'];
    $uinfo['lname'] = $user->get('field_last_name')->getValue()[0]['value'];
    $dob_field = $user->get('field_date_of_birth')->getValue();
    if (isset($dob_field) && isset($dob_field[0])) {
      $uinfo['dob'] = str_replace('-', '', $dob_field[0]['value']);
    }
    else {
      $uinfo['dob'] = '';
    }
    $uinfo['mid'] = $user->get('field_rusa_member_id')->getValue()[0]['value'];
    return ($uinfo);
  }

  /**
   * Generate Smartwaiver URl
   *
   */
  protected function smartwaiver_url($pid) {
    // Get URL from settings
    $swurl = $this->progreg->getSwUrl();
    $swurl .= '?wautofill_firstname=' . $this->uinfo['fname'];
    $swurl .= '&wautofill_lastname=' . $this->uinfo['lname'];
    $swurl .= '&wautofill_dobyyyymmdd=' . $this->uinfo['dob'];
    $swurl .= '&wautofill_tag=' . $this->uinfo['mid'] . ':' . $pid;

    return $swurl;        
  }

  /**
   * Show table of perm details.
   *
   */
  protected function get_perm($pid) {
    // We have to go back and get it again
    $permobj = new RusaPermanents(['key' => 'pid', 'val' => $pid]);

    // If they bypass the select form route may not be valid
    if ($permobj->isInactive($pid)) {
      // Display error and reload the form
      $this->messenger()->addError($this->t('The route you selected is not active.'));
      return FALSE;

    }
    if ($permobj->isSr($pid)) {
      $this->messenger()->addError($this->t('The route you selected is an SR600.'));
      return FALSE;
    }

    // Perm is good so load it
    $perm = $permobj->getPermanent($pid);

    // Get shapes
    $ptypes = ['LOOP' => 'Loop', 'OB' => 'Out and back', 'PP' => 'Point to point'];

    // Get location
    if ($perm->type == 'PP') {
      $loc = "From $perm->startcity, $perm->startstate to $perm->endcity, $perm->endstate";
    }
    else {
      $loc = "Starts and ends in $perm->startcity, $perm->startstate";
    }

    // Get RwGPS link
    if (!empty($perm->url)) {
      $url = URL::fromUri($perm->url);
      $url->setOption('attributes', ['target' => '_blank']);
      $rwgps = Link::fromTextAndUrl($perm->url, $url)->toString();
    }

    // Build link to results search
    // $uri = "https://dev.rusa.org/cgi-bin/resultsearch_PF.pl";
    $url = URL::fromRoute("rusa_perm.result_search");
    ;
    $url->setOption('query', ['permid' => $pid,
      'permdate' => '',
      'collapse' => '1']);

    $url->setOption('attributes', ['target' => '_blank']);
    $reslink = Link::fromTextAndUrl("View previous results", $url)->toString();

    $max_time_allowed = ResultSubmit::hours_and_minutes(ResultSubmit::calculate_time($perm->dist, $perm->dist_unpaved ?? 0));
    $dist_display = $perm->dist;
    if ($perm->dist_unpaved > 0) {
      $dist_display .= " (Unpaved:$perm->dist_unpaved)";
      $max_time_allowed .= " (may be less depending on actual distance ridden on gravel)";
    }
    $rows = [
      ['Route #', ['data' => "$pid   <-- Copy this so you can paste it in the waiver.", 'class' => ['rusa-em']]],
      ['Route name', $perm->name],
      ['Distance (km)', $dist_display],
      ['Max ride time', $max_time_allowed],
      ['Shape', $ptypes[$perm->type]],
      ['Climbing (ft)', $perm->climbing],
      ['Location', $loc],
      ['Dates available', $perm->dates],
      ['States covered', $perm->statelist],
      ['Last reviewed', $perm->datereviewed],
      ['Description', $this->t($perm->description)],
      ['Ride With GPS', $rwgps],
      ['Results', $reslink],
    ];

    return [
      '#type' => 'table',
      '#rows' => $rows,
      '#responsive' => TRUE,
      '#attributes' => ['class' => ['rusa-table']],
    ];
  }

} // End of class  

