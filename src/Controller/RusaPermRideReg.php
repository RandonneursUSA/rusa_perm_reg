<?php

namespace Drupal\rusa_perm_reg\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\rusa_api\RusaPermanents;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Messenger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;

/**
 * RusaPermReg Controller
 *
 */
class RusaPermRideReg extends ControllerBase {

    protected $mid; // Current users member id
    protected $fid;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxy $current_user) {
    // Get logged in user's RUSA #
    $user = User::load($current_user->id());
    $this->mid  = $user->get('field_rusa_member_id')->getValue()[0]['value'];

    // This gets the release form, which is not at all what we want here.
    $media = \Drupal::service('entity_type.manager')->getStorage('media');
      $query_result = $media->getQuery()
          ->condition('bundle', 'release_form')
          ->execute();
      if ($query_result) {
          // Justing getting the first one now
          // Need logic to select which one
          $id = array_shift($query_result);
          $release = $media->load($id);
          $field   = $release->get('field_media_file');
          $fid     = $field[0]->getValue()['target_id'];
          $this->fid = $fid;
      }

  } 

	/**
     * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container) {
		return new static(
		  $container->get('current_user')
		);
	 }

	 /**
	  * Display perm registration info
	  */
	 public function permRideInfo() {

		$file = File::load(279);

        $output = [
            '#type'       => 'container',
            '#attributes' => ['class'   => ['rusa-info']],
            '#attached'   => ['library' => ['rusa_api/rusa_style']],
        ];

        $output['info'] = [
            '#type'   => 'item',
            '#markup' => $this->t('Register for Perm Ride (Info goes here)'),
        ];



		$variables = [
		  'style_name' => 'large',
		  'uri' => $file->getFileUri(),
		];

		// The image.factory service will check if our image is valid.
		$image = \Drupal::service('image.factory')->get($file->getFileUri());
		if ($image->isValid()) {
		  $variables['width'] = $image->getWidth();
		  $variables['height'] = $image->getHeight();
		}
		else {
		  $variables['width'] = $variables['height'] = NULL;
		}

		$output['waiver'] = [
		  '#theme' => 'image_style',
		  '#width' => $variables['width'],
		  '#height' => $variables['height'],
		  '#style_name' => $variables['style_name'],
		  '#uri' => $variables['uri'],
		];

		$output['names'] = [
			'#type'   => 'item',
			'#markup' => $this->t('Enter your name in both text fields (fistname lastname)'),
		];

		$output['name1'] = [
			'#type'   	=> 'textfield',
			'#title'	=> $this->t('Name'),
			'#field_suffix' => $this->t('By typing my name I assert that his is the waiver that I have read and signed and this is my signature.'),
			'#required'		=> 1,
		];


		$output['name2'] = [
			'#type'   	=> 'textfield',
			'#title'	=> $this->t('Name'),
			'#field_suffix' => $this->t('By typing my name I agree that I am riding at my own risk and do not hold RUSA responsible.'),
			'#required'		=> 1,
		];

        return $output;
	}


} //EoC
