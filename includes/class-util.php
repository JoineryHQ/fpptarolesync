<?php

/**
 * Utility methods for fpptarolesync plugin.
 */
class FpptarolesyncUtil {

  /**
   * Array of relevant relationship types.
   */
  const RELATIONSHIP_TYPE_IDS = [
    // "Employee of":
    '5',
  ];

  /**
   * Write a message to the plugin log file, if plugin is so configured.
   * @param String $message The message to write.
   * @param String $messagePrefix An option string to prepend to $message.
   */
  public static function debugLog($message, $messagePrefix = NULL) {
    $options = get_option('fpptarolesync_options');
    if ($options['logging'] ?? 0) {
      if ($messagePrefix) {
        $message = "{$messagePrefix} :: $message";
      }
      // Log to our own 'fpptarolesync' log file in ConfigAndLog with
      // See also: https://docs.civicrm.org/dev/en/latest/framework/logging/
      civicrm_initialize();
      CRM_Core_Error::debug_log_message($message, FALSE, 'fpptarolesync');
    }
  }

  /**
   * Update the given user by adding or removeing the managed role.
   *
   * @param WP_User $user
   * @param bool $addRole If true, add the role; otherwise remove it.
   */
  public static function setUserRole(WP_User $user, $addRole = TRUE) {
    $options = get_option('fpptarolesync_options');
    $roleName = $options['role'] ?? NULL;
    if ($addRole) {
      self::debugLog('Add role "' . $roleName . '" for user ' . $user->ID, __METHOD__);
      $user->add_role($roleName);
    }
    else {
      self::debugLog('Remove role "' . $roleName . '" for user ' . $user->ID, __METHOD__);
      $user->remove_role($roleName);
    }
  }

  /**
   * For a given WP user id, check if the user is qualified for the Managed Role.
   *
   * @param Int $userId WP user ID.
   * @return boolean
   */
  public static function userIsCurrentMember($userId) {
    FpptarolesyncUtil::debugLog("Test if user '{$userId}' qualifies for Member Access.", __METHOD__);
    // Get this user's civicrm contact id, or return false if none found.
    $cid = CRM_Core_BAO_UFMatch::getContactId($userId);
    if (!$cid) {
      FpptarolesyncUtil::debugLog("User'{$userId}' does NOT qualify (No contact found for user).", __METHOD__);
      return FALSE;
    }
    // Add the main contact ID to a list of contact IDs, which we'll scan in a moment.
    $cids = [$cid];
    // Add to this list the cids for all appropriately related contacts.
    $relatedCids = FpptarolesyncUtil::getRelatedCidsForContact($cid);
    $cids = array_merge($cids, $relatedCids);
    // Find all memberships for our list of contacts, where membership_type is one
    // of our relevant types, and contact is primary member (not inherited).
    $options = get_option('fpptarolesync_options');
    $memberships = \Civi\Api4\Membership::get()
      ->setCheckPermissions(FALSE)
      ->addWhere('membership_type_id', 'IN', $options['membership_type_ids'])
      ->addWhere('owner_membership_id', 'IS NULL')
      ->addWhere('contact_id', 'IN', $cids)
      ->setLimit(0)
      ->addChain('membership_status', \Civi\Api4\MembershipStatus::get()
        ->setCheckPermissions(FALSE)
        ->addWhere('id', '=', '$status_id'),
      0)
      ->execute();
    if ($memberships->rowCount == 0) {
      // No memberships found? User is not qualified.
      FpptarolesyncUtil::debugLog("User'{$userId}' does NOT qualify (No related memberships found).", __METHOD__);
      return FALSE;
    }
    foreach ($memberships as $membership) {
      // If membership status is valid, that qualifies this user, so return true.
      if ($membership['membership_status']['is_current_member']) {
        FpptarolesyncUtil::debugLog("User'{$userId}' DOES qualify (Found current related membership '{$membership['id']}').", __METHOD__);
        return TRUE;
      }
    }
    // If we're still here, check if one of the memberships has a 'pending' payment.
    $membershipIds = $memberships->column('id');
    $membershipPaymentGet = civicrm_api3('MembershipPayment', 'get', [
      'sequential' => 1,
      'membership_id' => ['IN' => $membershipIds],
      'api.Contribution.get' => ['id' => "\$value.contribution_id", 'contribution_status_id' => "pending"],
    ]);
    foreach ($membershipPaymentGet['values'] as $membershipPayment) {
      if ($membershipPayment['api.Contribution.get']['count']) {
        // There's at least one pending payment on this membership; so we know
        // user has some kind of a related membership with a pending payment.
        // This qualifies the user. Return true.
        FpptarolesyncUtil::debugLog("User'{$userId}' DOES qualify (Found pending payment on related membership '{$membershipPayment['membership_id']}').", __METHOD__);
        return TRUE;
      }
    }

    // If we're still here, no qualifying condition was found, so return false.
    FpptarolesyncUtil::debugLog("User'{$userId}' does NOT qualify (No qualifications found).", __METHOD__);
    return FALSE;
  }

  /**
   * For a given membershipId, get contact IDs for all contacts that have a current
   * relationship (of the appropriate relationship type) to the member contact.
   *
   * @param Int $membershipId
   * @return Array
   */
  public static function getRelatedCidsForMembership($membershipId) {
    // Static cache, since we may be called multiple times.
    static $cids = [];
    if (empty($cids[$membershipId])) {
      // Start with an empty set.
      $cids[$membershipId] = [];
      // Get membership type and owner_membership_id
      $membership = \Civi\Api4\Membership::get()
        ->setCheckPermissions(FALSE)
        ->addSelect('membership_type_id', 'owner_membership_id', 'contact_id')
        ->addWhere('id', '=', $membershipId)
        ->setLimit(1)
        ->execute()
        ->single();
      $options = get_option('fpptarolesync_options');
      if (
        in_array($membership['membership_type_id'], $options['membership_type_ids'])
        && is_null($membership['owner_membership_id'])
      ) {
        // Only if this membership is of a relevant type, and it's primary
        // get all related contacts for the member contact.
        $cids[$membershipId] = FpptarolesyncUtil::getRelatedCidsForContact($membership['contact_id']);
      }
    }
    return $cids[$membershipId];
  }

  /**
   * For a given contact ID, get the WordPress user ID, if any.
   * @param Int $cid
   * @return Int|null
   */
  public static function getUserIdForContactId($cid) {
    return CRM_Core_BAO_UFMatch::getUFId($cid);
  }

  /**
   * For a given contribution ID, check some criteria and either return one
   * membership ID, or NULL.  We only return a membership ID if ALL of these are true:
   * - The given contribution is a membership payment on a primary membership of
   *   the appropriate membership_type.
   * - The given contribution has a status of 'pending'.
   * @param Int $contributionId
   * @return Int|null
   */
  public static function getMembershipIdForContributionId($contributionId) {
    // Initialize a null membership ID.
    $membershipId = NULL;

    // We only care about the membership if this contribution is a membership
    // payment on a primary membership of a relevant type, and the contribution
    // status is 'pending'.
    // So fetch any membershipPayment linked to this contribution, and a count
    // of linked memberships which meet the criteria (should be 0 or 1 of them);
    // also fetch the count of contributions with this Contribution ID which
    // have a status of 'pending'.
    $options = get_option('fpptarolesync_options');
    $membershipPaymentGetParams = [
      'sequential' => 1,
      'contribution_id' => $contributionId,
      'api.Membership.getCount' => [
        'id' => "\$value.membership_id",
        'owner_membership_id' => ['IS NULL' => 1],
        'membership_type_id' => ['IN' => $options['membership_type_ids']],
      ],
      'api.Contribution.getCount' => [
        'id' => "\$value.contribution_id",
        'contribution_status_id' => "Pending",
      ],
    ];
    $membershipPaymentGet = civicrm_api3('MembershipPayment', 'get', $membershipPaymentGetParams);
    if (
      $membershipPaymentGet['count']
      && $membershipPaymentGet['values'][0]['api.Membership.getCount']
      && $membershipPaymentGet['values'][0]['api.Contribution.getCount']
    ) {
      // We can know that we have a relevant membership only if we found a membership payment,
      // and a membership of the right type, and the right contribution status.
      // If so, use this membereship ID.
      $membershipId = $membershipPaymentGet['values'][0]['membership_id'];
    }
    // Return any membership ID we've found.
    return $membershipId;
  }

  /**
   * For a given contact, find other contacts who have a current relationship
   * of the appropriate relationship type.
   *
   * @staticvar array $cids Static caching.
   * @param Int $cid
   * @return array An array of the related contact IDs.
   */
  public static function getRelatedCidsForContact($cid) {
    // Static cache, since we may be called multiple times.
    static $cids;
    if (!isset($cids[$cid])) {
      // Define an array of related contacts for the given cid.
      $cids[$cid] = [];
      // Get all current employee relationships for this contact, so that we can
      // get related organizations.
      $relationships = \Civi\Api4\Relationship::get()
        ->setCheckPermissions(FALSE)
        ->addWhere('is_current', '=', TRUE)
        ->addWhere('relationship_type_id', 'IN', self::RELATIONSHIP_TYPE_IDS)
        ->addClause('OR', ['contact_id_a', '=', $cid], ['contact_id_b', '=', $cid])
        ->setLimit(0)
        ->execute();
      // For each of these relationships, get the contact ID of the related contact,
      // and add it to our list of contact IDs.
      foreach ($relationships as $relationship) {
        $relatedCid = (($relationship['contact_id_a'] != $cid) ? $relationship['contact_id_a'] : $relationship['contact_id_b']);
        $cids[$cid][] = $relatedCid;
      }
    }
    return $cids[$cid];
  }

  /**
   * For a given array of contact IDs, add or remove the managed role on the
   * contact's WP user record.
   *
   * @param array $cidsToUpdate
   */
  public static function updateRolesForCids($cidsToUpdate) {
    foreach ($cidsToUpdate as $cidToUpdate) {
      if ($userId = FpptarolesyncUtil::getUserIdForContactId($cidToUpdate)) {
        $user = new WP_User($userId);
        // Update the role appropriately for this user/contact's calculated status.
        FpptarolesyncUtil::setUserRole($user, FpptarolesyncUtil::userIsCurrentMember($userId));
      }
    }
  }

  /**
   * Check whether the plugin is sufficiently (and correctly) configured.
   *
   * @return boolean True if sufficiently configured; otherwise false.
   * @throws Exception If incorrectly configured.
   */
  public static function pluginIsConfigured() {
    // Static cache of return, since we may be called multiple times.
    static $isConfigured;
    if (!isset($isConfigured)) {
      // Start by assuming we're configured. We'll change this below if it's not really so.
      $isConfigured = TRUE;
      $required_configs = [
        'role' => 'string',
        'membership_type_ids' => 'array',
      ];
      $options = get_option('fpptarolesync_options');
      foreach ($required_configs as $required_config_name => $required_config_type) {
        if (
          empty($options[$required_config_name])
        ) {
          // Either this config has not been defined, or it's empty. So we're not fully
          // configured.
          $isConfigured = FALSE;
          break;
        }
        // If we're fully configured, make sure we're correctly configured.
        if ($isConfigured) {
          // validate config type. If config is defined, but it's the wrong type,
          // throw an exception.
          $config_value = $options[$required_config_name];
          $config_value_type = gettype($config_value);
          if ($config_value_type != $required_config_type) {
            throw new Exception("Configuration $required_config_name should be of type: $required_config_type; $config_value_type found.");
          }
        }
      }
    }
    return $isConfigured;

  }

  public static function getPluginData() {
    static $pluginData;
    if (!isset($pluginData)) {
      $pluginData = get_plugin_data(__DIR__ . '/../fpptarolesync.php');
    }
    return $pluginData;
  }

}
