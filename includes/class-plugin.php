<?php

/**
 * The core plugin class.
 */
class FpptarolesyncPlugin {

  /**
   * Execute all of the hooks with WordPress.
   */
  public function run() {
    // Implement hook_civicrm_post
    add_action('civicrm_postCommit', [$this, 'civicrm_postCommit'], 10, 4);
    // Implement hook_civicrm_pre
    add_action('civicrm_pre', [$this, 'civicrm_pre'], 10, 4);
    // Implement wp_login action hook
    add_action('wp_login', [$this, 'sync_user'], 10, 2);
    // For easier in-browser testing, you can enable this line, which will,
    // whenever you view your own WP user profile, fire the same checks as
    // would be done upon login:
    add_action('show_user_profile', [$this, 'show_user_profile']);
  }

  /**
   * Action handler for show_user_profile action hook. Not used unless the line
   * adding this action has been un-commented (near the top of ths file).
   */
  public function show_user_profile(WP_User $user) {
    if (!FpptarolesyncUtil::pluginIsConfigured()) {
      return;
    }
    FpptarolesyncUtil::debugLog('Show profofile for user: ' . $user->user_login, __METHOD__);
    $this->sync_user($user->user_login, $user);
  }

  /**
   * Action handler for user_login action hook.
   */
  public function sync_user(string $user_login, WP_User $user) {
    if (!FpptarolesyncUtil::pluginIsConfigured()) {
      return;
    }
    FpptarolesyncUtil::debugLog('User login: ' . $user_login, __METHOD__);
    // Update this user's managed role, as appropriate.
    FpptarolesyncUtil::setUserRole($user, FpptarolesyncUtil::userIsCurrentMember($user->ID));
  }

  /**
   * Action handler for hook_civicrm_pre.
   */
  public function civicrm_pre($op, $objectName, $objectId, &$params) {
    if (!FpptarolesyncUtil::pluginIsConfigured()) {
      return;
    }
    if (
      $objectName == 'Membership' 
      && $op == 'delete' 
      && empty($params[$objectId]['owner_membership_id']) 
      && in_array($params[$objectId]['membership_type_id'], FPPTAROLESYNC_MEMBERSHIP_TYPE_IDS)
    ) {
      // Only when deleting a primary membership of the relevant type.
      $membershipId = $objectId;
      FpptarolesyncUtil::debugLog('Deleting membership: ' . $membershipId, __METHOD__);
      Civi::$statics[__CLASS__]['cidsToUpdate']['membership'][$membershipId] = FpptarolesyncUtil::getRelatedCidsForMembership($membershipId);
    }
    elseif (
      $objectName == 'Contribution' 
      && $op == 'delete'
    ) {
      // Only when deleting a contribution.
      $contributionId = $objectId;
      if ($membershipId = FpptarolesyncUtil::getMembershipIdForContributionId($contributionId)) {
        FpptarolesyncUtil::debugLog("Deleting membership payment '{$contributionId}' on membershipId '{$membershipId}'", __METHOD__);
        Civi::$statics[__CLASS__]['cidsToUpdate']['contribution'][$contributionId] = FpptarolesyncUtil::getRelatedCidsForMembership($membershipId);
      }
    }
  }

  /**
   * Action handler for hook_civicrm_postCommit.
   */
  public function civicrm_postCommit($op, $objectName, $objectId, &$objectRef) {
    if (!FpptarolesyncUtil::pluginIsConfigured()) {
      return;
    }
    if ($objectName == 'Membership' && empty($objectRef->owner_membership_id)) {
      // Only if this is a primary membership:
      // Initialize an empty set of contact IDs.
      $cidsToUpdate = [];
      if ($op == 'delete') {
        // If deleting, get contact ids from stored static veriable: reference self::civicrm_pre().
        $cidsToUpdate = Civi::$statics[__CLASS__]['cidsToUpdate']['membership'][$objectId];
      }
      elseif (
        $op == 'create' || $op == 'edit'
      ) {
        FpptarolesyncUtil::debugLog("Operation '$op' on membershipId '{$objectId}'", __METHOD__);
        // If creating or editing, get contact ids from the membership itself.
        $cidsToUpdate = FpptarolesyncUtil::getRelatedCidsForMembership($objectId);
      }
      FpptarolesyncUtil::updateRolesForCids($cidsToUpdate);
    }
    elseif ($objectName == 'Contribution') {
      // Initialize an empty set of contact IDs.
      $cidsToUpdate = [];
      if ($op == 'delete') {
        // If deleting, get contact ids from stored static veriable: reference self::civicrm_pre().
        $cidsToUpdate = Civi::$statics[__CLASS__]['cidsToUpdate']['contribution'][$objectId];
      }
      elseif (
        $op == 'create' || $op == 'edit'
      ) {
        // If creating or editing, get any relevant membership id, and then get
        // contact ids from the membership itself.
        if ($membershipId = FpptarolesyncUtil::getMembershipIdForContributionId($objectId)) {
          FpptarolesyncUtil::debugLog("Operation '$op' on contributionId '{$objectId}'", __METHOD__);
          $cidsToUpdate = FpptarolesyncUtil::getRelatedCidsForMembership($membershipId);
        }
      }
      FpptarolesyncUtil::updateRolesForCids($cidsToUpdate);
    }
    elseif (
      $objectName == 'Relationship' && in_array($objectRef->relationship_type_id, FpptarolesyncUtil::RELATIONSHIP_TYPE_IDS)
    ) {
      $cidsToUpdate = [
        $objectRef->contact_id_a,
        $objectRef->contact_id_b,
      ];
      FpptarolesyncUtil::updateRolesForCids($cidsToUpdate);
    }
  }

}
