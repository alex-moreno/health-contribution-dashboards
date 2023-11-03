<?php

/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * The routines here dispatch control to the appropriate handler, which then
 * prints the appropriate page.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 * 
 * TODO: find age of the users in Drupal, and mark them as novices, advanced, etc.
 * TODO: Loop over active users to find their age in the community. Is it a healthy number?
 * 
 * nohup sudo php scripts/drush-script.php --verbose yes --status 13  --filename /home/alexmoreno/needs-work.csv &
 * 
 */

/**
 * Root directory of Drupal installation.
 * 
 */
// From current folder.
// Stage.
//define('DRUPAL_ROOT', "/var/www/staging.devdrupal.org/htdocs/");
// Dev.
define('DRUPAL_ROOT', "/var/www/dev/alexmor-drupal.dev.devdrupal.org/htdocs/");
chdir(DRUPAL_ROOT);
define('SMALL_FILE', 1000);

require_once 'lib.php';

// Fetch command line options.
$short_options = "hl::f::st:lm:vb:env:dcr:de:dc";
$long_options = ["help", "filename:", "status:", "limit:", "verbose:", "env:", "datecreated:", "dateend:", "datechanged:"];
$options = getopt($short_options, $long_options);

helpMessage($options);

$verbose = getVerbose($options);
$fileoutput = getFileOutput($options, $verbose);
$limit = getLimit($options, $verbose);

$datecreated = dateCreated($options, $verbose);
$dateend = dateEnd($options, $verbose);
$datechanged = datechanged($options, $verbose);
$status = getStatus($options, $verbose);

setEnvironment($options);

// Bootstrapping Drupal.
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

$query = buildQuery();
if ($verbose) {
  echo "Executing query: ";
}

$results = fetchResults($query, $status, $datecreated, $datechanged);

foreach($results as $result) {
  
  if ($verbose) {
    echo PHP_EOL . "NID :: " . $result->nid . " title :: " . $result->title . " - Status :: "
    . $result->field_issue_status_value . " project ID :: " . $result->field_project_target_id
    . " Author UID: " . $result->uid
    . " CREATED: " . date("d m Y",$result->created)
    . " CHANGED: " . date("d m Y",$result->changed);

  }
}


echo "finished.";
