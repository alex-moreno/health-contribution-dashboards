<?php
// Avoiding warning messages
$_SERVER['SCRIPT_NAME'] = '/script.php';
$_SERVER['SCRIPT_FILENAME'] = '/path/to/this/script.php';
$_SERVER['HTTP_HOST'] = 'domain.com';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

$ip_address = $_SERVER['REMOTE_ADDR'];

  /*
  * Fetch status from the $options argument.
  */
function getStatus($options, $verbose){
  $status = NULL;
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
  
  }
  
  return $status;
}

  /*
  * Fetch dateCreated from the $options argument.
  */
function dateCreated($options, $verbose) {
  $datecreated = NULL;
  if(isset($options["dcr"]) || isset($options["datecreated"])) {
      $datecreated = $options["datecreated"];
      
      if($verbose) {
        echo PHP_EOL . "Date created:: " . $datecreated;
      }
    }
    
  return $datecreated;
}

  /*
  * Fetch dateEnd from the $options argument.
  */
function dateEnd($options, $verbose) {
    $dateend = NULL;

    if(isset($options["de"]) || isset($options["dateend"])) {
        $dateend = $options["dateend"];
      
        if($verbose) {
          echo PHP_EOL . "DateEnd:: " . $dateend;
        }
      }

    return $dateend;
}


/*
* Fetch datechanged from the $options argument.
*/
function datechanged($options, $verbose) {
    $datechanged = NULL;
    if(isset($options["dc"]) || isset($options["datechanged"])) {
        $datechanged = $options["datechanged"];
      
        if($verbose) {
          echo PHP_EOL . "Date Changed:: " . $datechanged;
        }
      }

      return $datechanged;
}

/*
* Fetch datechanged from the $options argument.
*/
function getFileOutput($options, $verbose) {
    $fileoutput = "";

    if(isset($options["f"]) || isset($options["filename"])) {
        $fileoutput = isset($options["f"]) ? $options["f"] : $options["filename"];
    }

    return $fileoutput;
}

/*
* Fetch datechanged from the $options argument.
*/
function getLimit($options, $verbose) {
    $limit = NULL;

    if(isset($options["lm"]) || isset($options["limit"])) {
        $limit = isset($options["lm"]) ? $options["lm"] : $options["limit"];
      }

    return $limit;
}

/*
* Print Help Message on screen.
*/
function helpMessage($options) {
    if(isset($options["hl"]) || isset($options["help"])) {
        $help = isset($options["hl"]) ? $options["help"] : $options["help"];
      
        print_help_message();
        exit(1);
      }
}

/*
* Fetch verbosity from the $options argument.
*/
function getVerbose($options) {
    $verbose = FALSE;

    if(isset($options["vb"]) || isset($options["verbose"])) {
        $verbose = TRUE;
        echo "Verbose enabled, being noisy.";
      }

    return $verbose;
}

/*
* Set environment variables.
*/
function setEnvironment($options) {
    // Maybe use $ENV instead?
    if(isset($options["env"]) || isset($options["environment"])) {
      $env = isset($options["env"]) ? $options["env"] : $options["environment"];
      if($env == "stage") {
      define('DRUPAL_ROOT', "/var/www/staging.devdrupal.org/htdocs/");
        chdir(DRUPAL_ROOT);
      }
    }
  
  }

/*
* Build the query.
*/
function buildQuery($limit) {
    // Build the query.
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
    
    return $query;
}

/*
* Fetch Results
*/
function fetchResults($query, $status, $datecreated, $datechanged) {
    $results = $query
    ->fields('n', array('nid', 'title', 'created', 'changed', 'uid'))
    ->fields('fis', array('field_issue_status_value'))
    ->fields('fdp', array('field_project_target_id'))
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

    return $results;
}

/*
* Find if the current node contains a patch, and store the user in the array if so
* TODO: separation of concerns is lacking here. Both functionalities, find patch and store in array
* should be split.
*/
function getMakers($node, $makers) {
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

            // Store the current User/Maker UID.
            if(isset($makers[$currentComment->uid])) {
              $makers[$currentComment->uid]['numberpatches']++;
            } else {
              $makers[$currentComment->uid]['numberpatches'] = 1;
            }

            // Store just once, unless we want to store all patches
            //if ($makers[$currentComment->uid]['numberpatches'] > 1) {
              // Store the UID again for later easier reference.
              $makers[$currentComment->uid]['uid'] = $currentComment->uid;
              // Store the CID where the patch was posted.
              $makers[$currentComment->uid]['node'][$currentComment->nid]['nid'] = $currentComment->nid;
              $makers[$currentComment->uid]['node'][$currentComment->nid]['cid'] = $currentComment->cid;
              $makers[$currentComment->uid]['node'][$currentComment->nid]['created'] = $currentComment->created;

            //}            
          }
        }
      }
    }
  }
  return $makers;
}

/*
* Store makers in a CSV file.
*/
function storeMakersCSV($makers, $fpMakersName = "makers.csv") {
  echo "file: " . $fpMakersName;

  $fpMakers = fopen($fpMakersName, 'w');
  fputcsv($fpMakers, Array('UID', 'User created', 'Last login', 'Num Patches', 'Patch (cid)', 'nid', 'created'));
  
  foreach($makers as $maker) {
    $userAccount = user_load($maker['uid']);
    $uid = $maker['uid'];

    echo PHP_EOL . PHP_EOL;
    echo PHP_EOL . "------- -------"; 
    echo PHP_EOL . " M a k e r";
    echo PHP_EOL . "------- -------";
    echo PHP_EOL . "User created: " . date('F j, Y, g:i a', $userAccount->created);
    echo PHP_EOL . "Last login: " . date('F j, Y, g:i a', $userAccount->login);
    echo PHP_EOL . "Patches: " . $maker['numberpatches'];
    echo " uid: " . $uid;
  
    echo PHP_EOL;
    echo PHP_EOL;
  
    echo " Patches by this maker";
    echo PHP_EOL . "------- -------";
    foreach($maker['node'] as $node) {
      echo PHP_EOL . PHP_EOL . "Comment CID: " . $node['cid'];
      echo PHP_EOL . " - created: " . date('F j, Y, g:i a', $node['created']);
      echo PHP_EOL . " - node: " . $node['nid'];
  
      $output = Array(
        $maker['uid'], 
        date('F j, Y, g:i a', $userAccount->created), 
        date('F j, Y, g:i a', $userAccount->login), 
        $maker['numberpatches'],
        $node['cid'],
        $node['nid'],
        $node['created'],
        date('F j, Y, g:i a', $userAccount->login)
      );

      // Writing to the csv.
      echo "writing csv";
      fputcsv($fpMakers, $output); 
    }

    echo PHP_EOL;
  
  
  }
  fclose($fpMakers);

}
