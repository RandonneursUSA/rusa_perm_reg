<?php

namespace Drupal\rusa_perm_reg\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the rusa perm registration entity edit forms.
 */
class RusaPermRegistrationForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New rusa perm registration %label has been created.', $message_arguments));
      $this->logger('rusa_perm_reg')->notice('Created new rusa perm registration %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The rusa perm registration %label has been updated.', $message_arguments));
      $this->logger('rusa_perm_reg')->notice('Updated new rusa perm registration %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.rusa_perm_registration.canonical', ['rusa_perm_registration' => $entity->id()]);
  }

}
