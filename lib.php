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
function buildQuery($limit, $type) {
    // Build the query.
    if ($type == "users") {
      $query = db_select('users', 'u');
    } else {
      $query = db_select('node', 'n');

      $query->join('field_data_field_issue_status','fis','n.nid = fis.entity_id');
      $query->join('field_data_field_project','fdp','n.nid = fdp.entity_id');
      // Filter by issue credits.
      // $query->join('field_issue_credit','fic','n.nid = fdp.entity_id');
    }

    // Temporarily limit number of queries if needed for debugging.
    if ($limit) {
      $query->range(0, $limit);
      if ($verbose) {
        echo PHP_EOL . "NOTE: limiting to $limit queries by cli." . PHP_EOL . PHP_EOL;
      }
    }

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
* Fetch users
*/
function fetchUsers($query, $status, $datecreated, $datechanged) {
  $results = $query
  ->fields('u', array('uid', 'name', 'created', 'changed', 'login', 'status'))
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
* Fetch comments
*/
function fetchAllCommentsWithCredits($uid) {
  $queryComments = db_select('comment', 'c');
  $queryComments->join('field_data_field_issue_credit','fic','c.cid = fic.field_issue_credit_target_id');
  $queryComments->fields('c', array('cid', 'uid', 'nid', 'created', 'changed'));
  $queryComments->fields('fic', array('field_issue_credit_target_id'));
  $queryComments->orderBy('created', 'ASC');

  $queryComments->condition('uid', $uid, "=");
  // Order so the first result will be the oldest comment.
  // TODO.

  return $queryComments->execute();
}

/*
* Get a specific comment.
*/
function getComment($cid) {
  $result = db_query('SELECT cid, nid, created, changed FROM comment WHERE cid = :cid',array(':cid' => $cid));

  return $result->fetchObject();
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
* Find if the current node contains users who have been credited for their contribution.
*/
function getCreditedUsers($node, $makers) {

  $comments = comment_get_thread($node, COMMENT_MODE_FLAT, 100);

  $filesize = 0;
  foreach($comments as $comment) {
    $currentComment = comment_load($comment);

    foreach($currentComment->field_issue_changes['und'] as $field_file) {




          // If we find a credit.
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
            // Store the UID again for later easier reference.
            $makers[$currentComment->uid]['uid'] = $currentComment->uid;
            // Store the CID where the patch was posted.
            $makers[$currentComment->uid]['node'][$currentComment->nid]['nid'] = $currentComment->nid;
            $makers[$currentComment->uid]['node'][$currentComment->nid]['cid'] = $currentComment->cid;
            $makers[$currentComment->uid]['node'][$currentComment->nid]['created'] = $currentComment->created;
            
          }
        
      
    }
  }


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

function getReadableDate($unixdate) {
  return date('F j, Y, g:i a', $unixdate);
}


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
