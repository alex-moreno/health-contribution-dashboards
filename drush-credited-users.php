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

$query = buildQuery($limit, "users");
if ($verbose) {
  echo "Executing query: ";
}

$fpMakersName = "crediter-makers.csv";
$fpMakers = fopen($fpMakersName, 'w');
fputcsv($fpMakers, Array('UID','NID', 'Name', 'Status', 'User Created','User Created Unix', 'User Changed', 'User login', 'cid', 
  'Comment UID', 'Comment created', 'Comment Created Unix', 'Comment changed', 'TTFC'));

// Time to first registration.
$fpTTFName = "makers-timetofirst.csv";
$fpMakersTTF = fopen($fpTTFName, 'w');
  fputcsv($fpMakersTTF, Array('UID','NID', 'Name', 'Status', 'User Created','User Created Unix', 'User Changed', 'User login', 'cid', 
  'Comment UID', 'Comment created', 'Comment Created Unix', 'Comment changed', 'TTFC'));


$uniqueAuthors = array();
$makers = array();
$firstTimeReg = array();

// Fetch users registered in a given date.
$results = fetchUsers($query, $datecreated, $datechanged);
foreach($results as $user) {
  // DEBUGGING THIS USER.
  //$user->uid = 2416470; $user->uid
  // All credits received by this user.
  $nodesWithCredits = fetchAllNodesWithCredits($user->uid = $user->uid, $limit); // 2416470

  if (sizeof($nodesWithCredits) > 0) {
    if ($verbose) {
      echo " NUMBER OF CONTRIBS TOTAL :: " . sizeof($nodesWithCredits);
      echo PHP_EOL . " ------------- " . " ------------- " . PHP_EOL;
      echo PHP_EOL . "USER UID :: " . $user->uid . " Name :: " . $user->name . " - Status :: " . $user->status
      . PHP_EOL . " CREATED: " . getReadableDate($user->created)
      . PHP_EOL . " CHANGED: " . getReadableDate($user->changed)
      . PHP_EOL . " LOGIN: " . getReadableDate($user->login)
      . PHP_EOL . " ------------- " . PHP_EOL . PHP_EOL;
      ;

    }
  }

  // Loop over all creditted nodes this user has created.
  $ttc = NULL;
  $contribDateCreated = BIGDATE;
  $firstContribDate = NULL;
  $ttcDate = 0;
foreach ($nodesWithCredits as $node) {

    echo PHP_EOL . "CREDITTED COMMENT - NID :: " . $node->nid
    . PHP_EOL . "    - CREATED: " . getReadableDate($node->created)
    . PHP_EOL . "    - CHANGED: " . getReadableDate($node->changed);

    // CREATE A FUNCTION WITH THIS RECEIVING TWO DATES.
    // Debugging.
    $contribDate = getCommentsNode($node, $user->uid);

    echo "Date found: " .  getReadableDate($contribDate->created) . PHP_EOL;
    //if (getReadableDate($contribDate->created) < getReadableDate($contribDateCreated)) {
    // If date has not been set.
    // TODO: Compare dates instead, so it's more robust.
    if ($contribDateCreated == BIGDATE) {
      echo "earlier date found" . PHP_EOL;
      // Keep the current date.
      $contribDateCreated = $contribDate->created;

      echo "found earliest: " . getReadableDate($contribDate->created) . " - unix: " . $contribDate->created;
      echo PHP_EOL . " previous: " . getReadableDate($firstContribDate->created) . " - unix: " . $firstContribDate->created;

      $firstContribDate = $contribDate;
      $ttc = date_diff(date_create(getReadableDate($user->created)), date_create(getReadableDate($firstContribDate->created)));
      echo PHP_EOL . PHP_EOL . "TTFC ::: TADAAA:: " . $ttc->format('%R%a');
      $ttcDate = $ttc->format('%R%a');
    } else {
      echo "this date: " . getReadableDate($contribDate->created) . " - unix: " . $contribDate->created;
      echo " is not older than this one: " . getReadableDate($firstContribDate->created) . " - unix: " . $firstContribDate->created;
    }
    // /CREATE A FUNCTION WITH THIS RECEIVING TWO DATES.

    $output = Array(
      $user->uid,
      $node->nid,
      $user->name,
      $user->status,
      getReadableDate($user->created),
      $user->created,
      getReadableDate($user->changed),
      $user->login,
      $contribDate->cid,
      $contribDate->uid,
      getReadableDate($contribDate->created),
      $contribDate->created,
      getReadableDate($contribDate->changed),
      $ttcDate
      );
    fputcsv($fpMakers, $output); 
}

//if ($ttcDate!= 0) {
  echo PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . "TTFC ::: TADAAA END:: " . $ttcDate;
  fputcsv($fpMakersTTF, $output); 

//}



  }

  fclose($fpMakers);
  // When used.
  fclose($fpMakersTTF);
