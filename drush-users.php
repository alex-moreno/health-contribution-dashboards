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
  if (!empty($node->field_issue_credit)) {

    echo PHP_EOL;
    echo PHP_EOL;

    echo " ______ " . PHP_EOL;
    echo PHP_EOL . PHP_EOL . "__ __ node with credits" . PHP_EOL;
    // This are the nodes where credit has been give.
    
    foreach($node->field_issue_credit['und'] as $comment) {
      
      echo " ______ COMMENT:: " . "CID:: " . $comment['target_id'];
      echo " node:: " . $result->nid;
      
      $commentObj = getComment($comment['target_id']);

      echo "cid: " . $commentObj->cid;
      echo PHP_EOL . "nid: " . $commentObj->nid;
      echo PHP_EOL . "date: " . getReadableDate($commentObj->created);
      echo PHP_EOL . "date: " . getReadableDate($commentObj->changed);
      
      // TODO:
      // Store each date of this user, identified by user->nid->cid
      // Search all nodes created by this user
      // Store in the same format = user->nid->cid
      // Use this format to compare if there are contributions previous to the current date
      // marking first and last contributions.

      echo " ______ " . PHP_EOL;

      echo " ______ ::COMMENT " . PHP_EOL;
    }

    //print_r($node);
    echo PHP_EOL;
    echo PHP_EOL;

  }

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

//  $makers = getMakers($node, $makers);
  $makers = getCreditedUsers($node, $makers);
}

//echo "finished.";
//print_r($uniqueAuthors);

echo "Authors / Makers";
//print_r($makers);
echo PHP_EOL;


storeMakersCSV($makers);

// Find if only those makers in $makers have previous content with patches created in the past.
findPreviousPatches($currentComment->uid, $makers[$currentComment->uid]);

