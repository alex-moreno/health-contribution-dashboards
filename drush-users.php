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

$query = buildQuery();
if ($verbose) {
  echo "Executing query: ";
}

$results = fetchResults($query, $status, $datecreated, $datechanged);

$uniqueAuthors = array();
$maker = array();

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

  $comments = comment_get_thread($node, COMMENT_MODE_FLAT, 100);

  $filesize = 0;
  foreach($comments as $comment) {
    $currentComment = comment_load($comment);

    foreach($currentComment->field_issue_changes['und'] as $field_file) {
      foreach($field_file['new_value'] as $file) {
        if (!empty($file['filename'])) {

          // If we find a patch.
          if (strpos($file['filename'], '.patch')!== false) {
            if ($verbose) {
              echo "Patch found, user is a maker. Storing.";
              echo PHP_EOL . "fid:: " . $file['fid'];
              echo PHP_EOL . "uri:: " . $file['uri'] . PHP_EOL;
            }
            echo "disecting comment";
            print_r($currentComment);

            // Store the current User/Maker UID.
            if(isset($maker[$currentComment->uid])) {
              $maker[$currentComment->uid]['numberpatches']++;
            } else {
              $maker[$currentComment->uid]['numberpatches'] = 1;
            }

            // Store the UID anyway for faster, easier reference.
            $maker[$currentComment->uid]['uid'] = $currentComment->uid;
            // Store the CID where the patch was posted.
            $maker[$currentComment->uid]['node'][$currentComment->nid]['cid'] = $currentComment->cid;
            $maker[$currentComment->uid]['node'][$currentComment->nid]['created'] = $currentComment->created;
            
            //echo "disecting";
            //print_r($maker);


          }
        }
      }
    }

}
}

echo "finished.";
print_r($uniqueAuthors);

echo "Authors / Makers";
print_r($maker);

/*
* Find if the user has created content previously, and 
* if that content contained any patches.
*/
function userPreviousNodes() {
  
}
