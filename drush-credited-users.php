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

$results = fetchUsers($query, $status, $datecreated, $datechanged);

$fpMakersName = "crediter-makers.csv";
$fpMakers = fopen($fpMakersName, 'w');
fputcsv($fpMakers, Array('UID', 'Name', 'Status', 'User Created','User Created Unix', 'User Changed', 'User login', 'cid', 
  'Comment UID', 'Credit target', 'Comment created', 'Comment Created Unix', 'Comment changed', 'TTFC'));

// Time to first registration.
$fpTTFName = "makers-timetofirst.csv";
$fpMakersTTF = fopen($fpTTFName, 'w');
fputcsv($fpMakersTTF, Array('UID', 'Name', 'Status', 'User Created','User Created Unix', 'User Changed', 'User login', 'cid', 
  'Comment UID', 'Credit target', 'First Comment created', 'First Comment Created Unix', 'Comment changed', 'TTFC'));

$uniqueAuthors = array();
$makers = array();
$firstTimeReg = array();
foreach($results as $user) {

  // Get all comments by current uid.
  $userCommentsWithCredits = fetchAllCommentsWithCredits($user->uid);
  // TODO: debug, can delete.
  if ($userCommentsWithCredits->rowCount() > 0) {
    if ($verbose) {
      echo PHP_EOL . " ------------- " . " ------------- " . PHP_EOL;
      echo PHP_EOL . "USER UID :: " . $user->uid . " Name :: " . $user->name . " - Status :: " . $user->status
      . PHP_EOL . " CREATED: " . getReadableDate($user->created)
      . PHP_EOL . " CHANGED: " . getReadableDate($user->changed)
      . PHP_EOL . " LOGIN: " . getReadableDate($user->login)
      . PHP_EOL . " ------------- " . PHP_EOL . PHP_EOL;
      ;
    }      
  }

  foreach ($userCommentsWithCredits as $comment) {
    if ($verbose) {
      // All credits received by this user->uid
      echo PHP_EOL . "CREDITTED COMMENT";

      echo PHP_EOL . " - UID :: " . $comment->uid . " CID :: " . $comment->cid 
      . " - field_issue_credit_target_id :: " . $comment->field_issue_credit_target_id
      . PHP_EOL . "    - CREATED: " . getReadableDate($comment->created)
      . PHP_EOL . "    - CHANGED: " . getReadableDate($comment->changed);
    }

      $interval = date_diff(date_create(getReadableDate($user->created)), date_create(getReadableDate($comment->created)));   
      $output = Array(
        $user->uid,
        $user->name,
        $user->status,
        getReadableDate($user->created),
        $user->created,
        getReadableDate($user->changed),
        $user->login,
        $comment->cid,
        $comment->uid,
        $comment->field_issue_credit_target_id,
        getReadableDate($comment->created),
        $comment->created,
        getReadableDate($comment->changed),
        $interval->format('%R%a')
      );
      fputcsv($fpMakers, $output); 

      // We will store just the first contribution.
      if(empty($firstTimeReg[$user->uid])) {
        $firstTimeReg[$user->uid] = $user->uid;
        fputcsv($fpMakersTTF, $output); 
      }

    }

  }

fclose($fpMakers);
