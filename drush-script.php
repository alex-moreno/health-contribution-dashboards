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

// Fetch command line options.
$short_options = "hl::f::st:lm:vb:env:dcr:de:dc";
$long_options = ["help", "filename:", "status:", "limit:", "verbose:", "env:", "datecreated:", "dateend:", "datechanged:"];
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

if(isset($options["dcr"]) || isset($options["datecreated"])) {
  $datecreated = $options["datecreated"];

  if($verbose) {
    echo PHP_EOL . "Date created:: " . $datecreated;
  }
}

if(isset($options["de"]) || isset($options["dateend"])) {
  $dateend = $options["dateend"];

  if($verbose) {
    echo PHP_EOL . "DateEnd:: " . $dateend;
  }
}

if(isset($options["dc"]) || isset($options["datechanged"])) {
  $datechanged = $options["datechanged"];

  if($verbose) {
    echo PHP_EOL . "Date Changed:: " . $datechanged;
  }
}

if(isset($options["hl"]) || isset($options["help"])) {
  $help = isset($options["hl"]) ? $options["help"] : $options["help"];

  print_help_message();
  exit(1);

}

if(isset($options["st"]) || isset($options["status"])) {
  $status = isset($options["st"]) ? $options["st"] : $options["status"];

  // All active issues
  if($status == "active") {
    $status = [1,13,8,14,15,4,16];
  }

  // All closed issues
  if($status == "fixed") {
    $status = [2,7];
  }

  // All closed issues
  if($status == "closed") {
    $status = [2,3,5,6,18,17,7];
  }

    // All issues
  if($status == "all") {
    $status = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18];
  }

  if ($verbose) {
    echo PHP_EOL;
    echo "Setting up status: ";
    print_r($status);  
  }

  // Maybe use $ENV instead?
  if(isset($options["env"]) || isset($options["environment"])) {
    $env = isset($options["env"]) ? $options["env"] : $options["environment"];
    if($env == "stage") {
      define('DRUPAL_ROOT', "/var/www/staging.devdrupal.org/htdocs/");
      chdir(DRUPAL_ROOT);
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
//$query->join('field_data_field_tags','fdt','n.nid = fdp.entity_id');



if ($verbose) {
  echo "Executing query: ";
}

// TODO: Add date ranges.
$results = $query
  ->fields('n', array('nid', 'title', 'created', 'changed', 'uid'))
  ->fields('fis', array('field_issue_status_value'))
  ->fields('fdp', array('field_project_target_id'))
  //->fields('fdt', array('field_tags_tid'))
  ->condition('status', 1)
  ->condition('field_issue_status_value', $status)
  ->orderBy('created', 'DESC');

  if(isset($datecreated)) {
    echo "Filtering by date created" . PHP_EOL;
    $my_start_date = date('U', mktime(0, 0, 0, "1", "1", $datecreated));
    $my_end_date = date('U', mktime(0, 0, 0, "12", "1", $datecreated));

    $query->condition('created', array($my_start_date, $my_end_date), 'BETWEEN');
  }

  if(isset($dc) || isset($datechanged)) {
    echo "Filtering by date changed/updated" . PHP_EOL;

    $changed_start_date = date('U', mktime(0, 0, 0, "1", "1", $datechanged));
    $changed_end_date = date('U', mktime(0, 0, 0, "12", "1", $datechanged));
    
    $query->condition('changed', array($changed_start_date, $changed_end_date), 'BETWEEN');
  }

  // Get the queries.
  $results = $query->execute();

  // Prepare to write the csv.
  $csvfile = "issues.csv";
  $fileusers = "users.csv";
  if ($fileoutput != "") {
    $csvfile = $fileoutput;
    $fileusers =  $fileoutput . '-unique-authors.csv';
    $filecommenters =  $fileoutput . '-commenters.csv';

  }
  $fp = fopen($csvfile, 'w');

  if ($verbose) {
    echo "users cvs: " . $fileusers;
    echo "commenters cvs: " . $filecommenters;
  }

  $fpusers = fopen($fileusers, 'w');
  $fpcommenters = fopen($filecommenters, 'w');

  $issues = Array();
  //array_push($issues, Array('Node ID','Title','Created','Updated','Interval (days)','Number comments', 'Status', 'Num. authors', 'Patch size'));
  fputcsv($fp, Array('Node ID','Title','Created','Updated','Interval (days)','Number comments', 'Status', 'Num. authors', 'Patch size', 'tags'));

  $interval = 0;

//  $commenters = array();
  $uniqueAuthors = array();
  // Ready to iterate.
  foreach($results as $result) {
    $created = date('F j, Y, g:i a', $result->created);
    $changed = date('F j, Y, g:i a', $result->changed);
    $interval = diffDates($created, $changed);

    $node = node_load($result->nid);
    // Store the current author/user UID.
    if (isset($uniqueAuthors[$result->uid])) {
      $uniqueAuthors[$result->uid][1]++;
    }
    else {
      //$uniqueAuthors[$result->uid] = 1;
      $uniqueAuthors[$result->uid][0] = $result->uid;
      $uniqueAuthors[$result->uid][1] = 1;
    } 

    if ($verbose) {
      echo "NID :: " . $result->nid . " title :: " . $result->title . " - Status :: "
      . $result->field_issue_status_value . " project ID :: " . $result->field_project_target_id
      . " Author UID: " . $result->uid
      . " CREATED: " . date("d m Y",$result->created)
      . " CHANGED: " . date("d m Y",$result->changed);

    }

    // Get the tags.
    $tags = "";
    foreach($node->taxonomy_vocabulary_9[und] as $taxonomy) {
      $term = taxonomy_term_load($taxonomy['tid']);
      $tags = $tags . "," . $term->name;
    }

    if($tags != "") {
      $tags = trim($tags, ',');
      if($verbose) {
        echo "all tags: ";
        print_r($tags);  
        echo PHP_EOL;
      }
    }

    $comments = comment_get_thread($node, COMMENT_MODE_FLAT, 100);

    $filesize = 0;
    $numAuthors = array();
    foreach($comments as $comment) {
      $currentComment = comment_load($comment);

      // Get current Author.
      $numAuthors[$currentComment->name] = $currentComment->name;

/*      // Store the user as commenter.
      if (isset($commenters[$currentComment->name])) {
        $commenters[$currentComment->name]++;
      }
      else {
        $commenters[$currentComment->name] = 1;
      }
*/
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
  count($comments), $result->field_issue_status_value, sizeof($numAuthors), $filesize, $tags);
  // Writing to the csv.
  fputcsv($fp, $output);
}

fclose($fp);

/*
echo "Commenters: ";
print_r($commenters);
echo "Unique Authors: ";
print_r($uniqueAuthors);
foreach($uniqueAuthors as $uniqueAuthor) {
  fputcsv($fpusers, $uniqueAuthor);
}
fclose($fpusers);


fputcsv($fpcommenters, $commenters);

fclose($fpcommenters);
*/


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
  echo PHP_EOL . " -- DateStart";
  echo PHP_EOL . " All statuses";
  echo PHP_EOL . " 1|Active";
  echo PHP_EOL . " 13|Needs work";
  echo PHP_EOL . " 8|Needs review";
  echo PHP_EOL . " 14|Reviewed & tested by the community";
  echo PHP_EOL . " 15|Patch (to be ported)";
  echo PHP_EOL . "4|Postponed";
  echo PHP_EOL . "16|Postponed (maintainer needs more info)";

  echo PHP_EOL . "2|Fixed --> NOT USED FOR HISTORIC DATA. After 3 months they are moved into Closed(Fixed)";
  echo PHP_EOL . "3|Closed (duplicate)";
  echo PHP_EOL . "5|Closed (won't fix)";
  echo PHP_EOL . "6|Closed (works as designed)";
  echo PHP_EOL . "18|Closed (cannot reproduce)";
  echo PHP_EOL . "17|Closed (outdated)";
  echo PHP_EOL . "7|Closed (fixed)";
  echo PHP_EOL;
  echo PHP_EOL . " - Examples:";
  echo PHP_EOL . " php scripts/drush-script.php  --status 14";
  echo PHP_EOL . " php scripts/drush-script.php  --status active";
  echo PHP_EOL . " php scripts/drush-script.php  --status active --datestart 2018";

  echo PHP_EOL . PHP_EOL . "--environment: stage";

  echo PHP_EOL . PHP_EOL ."Example: nohup sudo php scripts/drush-script.php --verbose yes --status active  --filename /home/alexmoreno/needs-work.csv &";
  
  echo PHP_EOL . PHP_EOL;
}

/* TODO: CLEANUP */
function fetch_arguments() {

}

?>
