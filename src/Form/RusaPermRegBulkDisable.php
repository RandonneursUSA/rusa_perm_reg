<?php

/**
 * @file
 *  RusaPermRegBulkDisable.php
 *
 * @Created 
 *  2020-12-31 - Paul Lieberman
 *
 * RUSA Permanents Registration
 *
 * Bulk disable last years program registrations
 *
 * ----------------------------------------------------------------------------------------
 * 
 */

namespace Drupal\rusa_perm_reg\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Query;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;


class RusaPermRegBulkDisable extends FormBase {

    protected $storage;
 
    /**
     * @getFormID
     *
     * Required
     *
     */
    public function getFormId() {
        return 'rusa_perm_reg_bulk_disable';
    }

    /**
     * {@inheritdoc}
     */   
    public function __construct() {       

        // Get the entity query storage
		$this->storage = \Drupal::service('entity_type.manager')->getStorage('rusa_perm_registration');	
	}
	
	 /**
     * @buildForm
     *
     *
     */
    public function buildForm(array $form, FormStateInterface $form_state) {  
    
        // We could run this on New Years eve or New Years day so get both years.
        $this_year = date('Y');      
        $last_year = date('Y', strtotime('-1 year'));
        
        $form['message'] = [
            '#type'     => 'item',
            '#markup'   => $this->t('Select the year for which you want to disable all Program Registrations and then click the button'),
        ];
        
        $form['year'] = [
            '#type'     => 'select',
            '#title'    => $this->t('Select year'),
            '#options'  => [$last_year => $last_year, $this_year => $this_year],
        ];
        
        $form['submit'] = [
            '#type'     => 'submit',
            '#value'    => $this->t('Bulk disable registrations'),
        ];
        
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
    
    }
    
    
    /**
     * @submitForm
     *
     * Required
     *
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
   
        $year = $form_state->getValue('year');
	
		$query = $this->storage->getQuery()
            ->condition('status', 1)
            ->condition('field_registration_year', $year);
  
        $query_result = $query->execute();
        dpm($query_result);
        /*
        $count = 0;
        foreach ($query_result as $id) {
            $reg = $this->storage->load($id);
            $reg->set('status', 0);
            $reg->save();
            $count++;
        }
        */
        $this->messenger()->addStatus($this->t('%count Program Registrations have been disabled for %year',
                ['%count' => $count, '%year' => $year]));
        
    }
}
