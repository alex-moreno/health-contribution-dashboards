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
$long_options = ["help", "filename:", "status:", "limit:", "verbose:", 
"env:", "datecreated:", "dateend:", "datechanged:"];
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

$query = buildQuery($limit);
if ($verbose) {
  echo "Executing query: ";
}

$results = fetchResults($query, $status, $datecreated, $datechanged);

$uniqueAuthors = array();
$makers = array();

foreach($results as $result) {
  $node = node_load($result->nid);
    // Store the current author/user UID.
    if (isset($uniqueAuthors[$result->uid])) {
      // Store and array with what issues this user has created.
      $uniqueAuthors[$result->uid][0][] = $result->nid;
      $uniqueAuthors[$result->uid][1]++;
    }
    else {
      $uniqueAuthors[$result->uid][0][] = $result->nid;
      // First time finding this user, so set counter to 1.
      $uniqueAuthors[$result->uid][1] = 1;
    }

  if ($verbose) {
    echo PHP_EOL . "NID :: " . $result->nid . " title :: " . $result->title . " - Status :: "
    . $result->field_issue_status_value . " project ID :: " . $result->field_project_target_id
    . " Author UID: " . $result->uid
    . " CREATED: " . date("d m Y",$result->created)
    . " CHANGED: " . date("d m Y",$result->changed)
    ;

  }

  $makers = getMakers($node, $makers);
}

//echo "finished.";
//print_r($uniqueAuthors);

echo "Authors / Makers";
//print_r($makers);
echo PHP_EOL;


storeMakersCSV($makers);

// Find if only those makers in $makers have previous content with patches created in the past.
findPreviousPatches($currentComment->uid, $makers[$currentComment->uid]);


/*
* Find if the user has created content previously, and 
* if that content contained any patches.
*/
function findPreviousPatches($uid, $maker) {

  echo "finding more patches.";

  foreach($makers as $maker) {
    echo "maker:: " . $maker['uid'];
    // Does $maker['uid'] have more patches?
    findContentsByUser($maker['uid']);
    // Does any of these content exist in $maker['node'][NID] Array?
  }

  /*
  $patches = Array();
  // Build the query.
  $queryUser = db_select('node', 'n');
  $queryUser->fields('n', array('nid', 'title', 'created', 'changed', 'uid'));
  $queryUser->condition('uid', $uid);
  $authorNids = $queryUser->execute();

  // Author NIDs:
  echo PHP_EOL . PHP_EOL . " Author NIDs";
  foreach($authorNids as $node) {
    echo PHP_EOL . "node::: ";
    print_r($node);

    $nodeLoaded = node_load($node->nid);
    getMakers($nodeLoaded, $patches);

  }
  */
  // are thoser NIDs already in the $maker Array?
}

/*
*
*/
function findContentsByUser($uid) {

  echo "finding other patches created by the current user: " . $maker['uid'];

  $queryUser = db_select('node', 'n');
  $queryUser->fields('n', array('nid', 'title', 'created', 'changed', 'uid'));
  $queryUser->condition('uid', $maker['uid']);
  $authorNids = $queryUser->execute();

  echo "all nids: ";
  print_r($authorNids);
}
