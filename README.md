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
* Upon modification, creation, or deletion of any CiviCRM entities which are relevant to the qualification; this includes Memberships, Contributions, and Relationships.

At those times, the user's qualification for the Managed Role is calculated, and the role is added or removed accordingly.


## Installation
* Copy this package to the `/plugins`directory on your WordPress site.
* Activate the plugin "CiviCRM Membership Role Sync for FPPTA".

## Configuration
Configuration settings are under Uesrs > CiviCRM Membership Role Sync for FPPTA (url: /wp-admin/users.php?page=fpptarolesync_settings)

### Required settings

Even if activated, this plugin will take no action if any of these required settings are not set:

* Relevant Membership Types
* Managed Role

### Additional settings
* Log to File: If yes, this plugin will log its actions in a custom log file under CiviCRM's ConfigAndLog/ directory. The log filename will contain the string 'fpptarolesync'.

## Support

This is a custom plugin written for a specific site; it will have no relevant 
functionality on other sites.

Please contact the developer at allen@joineryhq.com to request help with similar
custom functionality for your own WordPress site.
