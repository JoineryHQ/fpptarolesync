# WordPress: CiviCRM Membership Role Sync for FPPTA

Provides synchronization of a single WordPress role based on CiviCRM data related to Memberships, Relationships, and Contributions, per the unique needs of FPPTA.

## Functionality
Note these definitions:

* **Managed Role**: A single WordPress user role to be managed (automatically added to or removed from all WordPress users) by this plugin.
* **Relevant Membership Types**: One or more CiviCRM membership types which will be monitored in the adding or removing of the Managed Role.

Once installed and configured (see below), this plugin will automatically add or remove the Managed Role for all WordPress users under certain situations and based on the user's qualification for the role as descrcibed here.

### Which users are qualified for the Managed Role?

To be qualified for the Manged Role, the user must meet these criteria:
* The user has a linked CiviCRM contact.
* The linked CiviCRM contat has a CiviCRM membership of a Relevant Membership Type (or a current "Employee of" relationship to a contact holding such a membership), which membership EITHER
  * has a status which CiviCRM consides "current" (per CiviCRM's Membership Status Rules configuration); OR
  * has an associated confribution with a status of "pending".

### When is the Managed Role added or removed?

* Upon user login
* Upon modification, creation, or deletion of any of the following CiviCRM entities which are relevant to the qualification; this includes Memberships, Contributions, and Relationships.

At those times, the user's qualification for the Managed Role is calculated, and the role is added or removed accordingly.


## Installation
* Copy this package to the `/plugins`directory on your WordPress site.
* Activate the plugin "CiviCRM Membership Role Sync for FPPTA".

## Configuration
There is no user-interface for configuration within WordPress, but configuration is required as explained below.

Even if activated, this plugin will take no action if any of the required configurations (marked "REQUIRED" below) are not set.

### Configuration in wp-config.php
Plugin configuration is by PHP constants defined in wp-config.php (or in another file which is run in an equivalent context in the WordPress loading sequence).

Add the following code to your wp-config.php:

```php
// REQUIRED: Machine name of the Managed Role.
// To get the machine name, you may, for example, note the value of the 'name' column
// in the output of the wp-cli command `wp role list`.
define( 'FPPTAROLESYNC_ROLENAME', 'FIXME' );

// REQUIRED: Array of CiviCRM membership type IDs, for Relevant Membership Types.
// To get the membership type IDs, you may, for example, note ethe value of the
// 'id' property in the output of the cv command `cv api MembershipType.get sequential=1 return="id,name"`
define( 'FPPTAROLESYNC_MEMBERSHIP_TYPE_IDS', array( 1, 2 ) );

// OPTIONAL: If this configuration is defined and set to a "true" value, this
// plugin will log its actions in a custom log file under CiviCRM's ConfigAndLog/
// directory. The log filename will contain the string 'fpptarolesync'.
define('FPPTAROLESYNC_LOG', 1);
```

## Support

This is a custom plugin written for a specific site; it will have no relevant 
functionality on other sites.

Please contact the developer at allen@joineryhq.com to request help with similar
custom functionality for your own WordPress site.
