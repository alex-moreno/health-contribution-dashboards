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
// Prod.
//define('DRUPAL_ROOT', "/var/www/staging.devdrupal.org/htdocs/");
// Stage.
define('DRUPAL_ROOT', "/var/www/dev/alexmor-drupal.dev.devdrupal.org/htdocs/");
chdir(DRUPAL_ROOT);
echo "folder: " . DRUPAL_ROOT . PHP_EOL;

define('SMALL_FILE', 1000);

// Fetch command line options.
$short_options = "hl::f::st:lm:vb:env:ds:de";
$long_options = ["help", "filename:", "status:", "limit:", "verbose:", "env:", "datestart:", "dateend:"];
$options = getopt($short_options, $long_options);
$verbose = FALSE;

$fileoutput = "";
if(isset($options["f"]) || isset($options["filename"])) {
  $fileoutput = isset($options["f"]) ? $options["f"] : $options["filename"];
}

if(isset($options["lm"]) || isset($options["limit"])) {
  $limit = isset($options["lm"]) ? $options["lm"] : $options["limit"];
}

if(isset($options["vb"]) || isset($options["verbose"])) {
  $verbose = TRUE;
  echo "Verbose enabled, being noisy.";
}

if(isset($options["ds"]) || isset($options["datestart"])) {
  $datestart = $options["datestart"];
}

if(isset($options["de"]) || isset($options["dateend"])) {
  $datestart = $options["dateend"];
}

if(isset($options["hl"]) || isset($options["help"])) {
  $help = isset($options["hl"]) ? $options["help"] : $options["help"];

  print_help_message();
  exit(1);

}

if(isset($options["st"]) || isset($options["status"])) {
  $status = isset($options["st"]) ? $options["st"] : $options["status"];


  // All active issues
  //->condition('field_issue_status_value', [3,5,6,17,18], 'NOT IN')
  if($status == "active") {
    $status = [3,5,6,8,17,18];
  }

  if ($verbose) {
    echo PHP_EOL;
    echo "setting up status: ";
    print_r($status);  
  }

  // Maybe use $ENV instead?
  if(isset($options["env"]) || isset($options["environment"])) {
    $env = isset($options["env"]) ? $options["env"] : $options["environment"];
    if($env == "stage") {
      define('DRUPAL_ROOT', "/var/www/staging.devdrupal.org/htdocs/");
      chdir(DRUPAL_ROOT);
      echo "Active folder: " . DRUPAL_ROOT . PHP_EOL;
    }
  }
}

// Avoiding warning messages
$_SERVER['SCRIPT_NAME'] = '/script.php';
$_SERVER['SCRIPT_FILENAME'] = '/path/to/this/script.php';
$_SERVER['HTTP_HOST'] = 'domain.com';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

$ip_address = $_SERVER['REMOTE_ADDR'];
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

$query = db_select('node', 'n');
// Temporarily limit number of queries if needed for debugging.
if ($limit) {
  $query->range(0, $limit);
  if ($verbose) {
    echo PHP_EOL . "NOTE: limiting to $limit queries by cli." . PHP_EOL . PHP_EOL;
  }
}

$query->join('field_data_field_issue_status','fis','n.nid = fis.entity_id');
$query->join('field_data_field_project','fdp','n.nid = fdp.entity_id');

if ($verbose) {
  echo "Executing query: ";
}

$results = $query
  ->fields('n', array('nid', 'title', 'created', 'changed'))
  ->fields('fis', array('field_issue_status_value'))
  ->fields('fdp', array('field_project_target_id'))
  ->condition('status', 1)
  // RTBC
  ->condition('field_issue_status_value', $status)

  ->orderBy('created', 'DESC')
  ->execute();


// Prepare to write the csv.
$csvfile = "issues.csv";
if ($fileoutput != "") {
  $csvfile = $fileoutput;  
}
$fp = fopen($csvfile, 'w');

$issues = Array();
array_push($issues, Array('Node ID','Title','Created','Updated','Interval (days)','Number comments', 'Status', 'Num. authors', 'Patch size'));
fputcsv($fp, Array('Node ID','Title','Created','Updated','Interval (days)','Number comments', 'Status', 'Num. authors', 'Patch size'));

$interval = 0;

// Ready to iterate.
foreach($results as $result) {
	$created = date('F j, Y, g:i a', $result->created);
	$changed = date('F j, Y, g:i a', $result->changed);
  $interval = diffDates($created, $changed);

  $node = node_load($result->nid);
  if ($verbose) {
    echo "NID :: " . $result->nid . " title :: " . $result->title . " - Status :: " 
    . $result->field_issue_status_value . " project ID :: " . $result->field_project_target_id . PHP_EOL;
  }

	$comments = comment_get_thread($node, COMMENT_MODE_FLAT, 100);

  $filesize = 0;
  $numAuthors = array();;
	foreach($comments as $comment) {
		$currentComment = comment_load($comment);

    // Get current Author.
    $numAuthors[$currentComment->name] = $currentComment->name;

		// TODO: Get number of authors per issue. if author is not in array, add a new one
    // if patch does not exist, but there is a Gitlab MR, check if there is an API.
    // CHECK THE DIFF: https://git.drupalcode.org/project/entity_jump_menu/-/merge_requests/2.diff
    foreach($currentComment->field_issue_changes['und'] as $field_file) {
      foreach($field_file['new_value'] as $file) {
        if (!empty($file['filename'])) {

          // If we find a patch.
          if (strpos($file['filename'], '.patch')!== false) {
            if ($verbose) {
              echo "Patch found.";
              echo PHP_EOL . "fid:: " . $file['fid'];
              echo PHP_EOL . "uri:: " . $file['uri'] . PHP_EOL;
            }

            // If we find a patch, we store its size.
            $filesize = $file['filesize'];
          }

          if($file['filesize'] < SMALL_FILE) {
            // Not doing anything with this for now.
            if ($verbose) {
              echo "NOTE: Small patch found." . PHP_EOL;
            }
          }
        } 
      }

    }

	}

  // Move to array.
  $output = Array($result->nid,$result->title, $created, $changed, $interval, 
  count($comments), $result->field_issue_status_value, sizeof($numAuthors), $filesize);
  //array_push($issues, $output);
  // Move here writing to the csv.
  fputcsv($fp, $output);
}

fclose($fp);

/**
 * Calculating time lapsed between two dates.
 * 
 */
function diffDates($created, $updated) {
  $date1 = new DateTime($created);
  $date2 = new DateTime($updated);
  $interval = $date1->diff($date2);

  return $interval->days;
}

/**
 * Printing a help message.
 * 
 */
function print_help_message() {
  echo PHP_EOL . "Arguments: ";
  echo PHP_EOL . PHP_EOL . "--status: ";
  echo PHP_EOL . " - Fixed: 2";
  echo PHP_EOL . " - Works as designed: 6";
  echo PHP_EOL . " - Needs review: 8";
  echo PHP_EOL . " - Needs work: 13";
  echo PHP_EOL . " - RTBC: 14";
  echo PHP_EOL . " - All active issues: active";
  echo PHP_EOL;
  echo PHP_EOL . " - Example:";
  echo PHP_EOL . " php scripts/drush-script.php  --status 14";
  echo PHP_EOL . " php scripts/drush-script.php  --status active";

  echo PHP_EOL . PHP_EOL . "--environment: stage";

  echo PHP_EOL . PHP_EOL ."Example: nohup sudo php scripts/drush-script.php --verbose yes --status active  --filename /home/alexmoreno/needs-work.csv &";
  
  echo PHP_EOL . PHP_EOL;
}

?>
