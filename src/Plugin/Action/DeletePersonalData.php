<?php

//************************************************************
// Creates VBO Action to delete all personal data
//  Drupal 8 version
//************************************************************

namespace Drupal\lalg_data_cleaning_utils\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Action to Delete Contacts and all Personal Data, with default confirmation form.
 *
 * @Action(
 *   id = "lalg_data_cleaning_utils_delete_personal_data",
 *   label = @Translation("LALG Delete all Personal Data"),
 *   type = "",
 *   confirm = TRUE,
 * )
 */
 
 
class DeletePersonalData extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
// dpm($entity);
// dpm(get_class_methods($entity));

	try {	
		// Get the Contact Id.
		if ($entity->getEntityTypeId() != "civicrm_contact") return;
		$cid = $entity->id();
	// dpm('Contact_id: ' . $cid);
		
		//Get the Drupal User ID
		$uFMatches = \Civi\Api4\UFMatch::get()
		  ->addSelect('uf_id')
		  ->addWhere('contact_id', '=', $cid)
		  ->execute();
	  
		if ($uFMatches->count() == 1) {
			$uid = $uFMatches[0]['uf_id'];
		}
		if ($uid && $uid == \Drupal::currentUser()) {
			drupal_set_message(t('You cannot delete yourself'), 'warning');
			return;
		}
	// dpm('Drupal User Id ' . $uid);

		// Delete related Activities
		$activityContacts = \Civi\Api4\ActivityContact::get()
		  ->addSelect('*')
		  ->addWhere('contact_id', '=', $cid)
		  ->addWhere('record_type_id', '=', 3)
		  ->setLimit(1000)
		  ->execute();
	  
		foreach ($activityContacts as $activityContact) {
			$results = \Civi\Api4\Activity::delete()
			  ->addWhere('id', '=', $activityContact['activity_id'])
			  ->execute();	
		}	
			
		// Delete related Contributions
		$results = \Civi\Api4\Contribution::delete()
		  ->addWhere('contact_id', '=', $cid)
		  ->execute();
		
		// Delete the Contact record
		// Flows down to associated address, Email, Membership, membership of Groups, Event Participant.
		$results = \Civi\Api4\Contact::delete()
		  ->addWhere('id', '=', $cid)
		  ->execute();
		
		// Delete associated Drupal User, Reassign content to Anonymous
		if (isset($uid) && $uid > 0) {
			user_cancel(
			  array(
				'user_cancel_notify' => FALSE,
				'user_cancel_method' => 'user_cancel_reassign',
			  ),
			  $uid,
			  'user_cancel_reassign'
			);
		}
		
	}
	catch(\Exception $e) {
		$msg = t($e->getMessage());
		drupal_set_message($msg, 'error');
		\Drupal::logger('lalg_data_cleaning_utils')->error($msg);
	}
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = $object->access('delete', $account, TRUE);
    if ($object->getEntityType() === 'node') {
      $access->andIf($object->status->access('delete', $account, TRUE));
    }
    return $return_as_object ? $access : $access->isAllowed();
  }

}
