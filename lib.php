<?php

// Avoiding warning messages
$_SERVER['SCRIPT_NAME'] = '/script.php';
$_SERVER['SCRIPT_FILENAME'] = '/path/to/this/script.php';
$_SERVER['HTTP_HOST'] = 'domain.com';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Mon Nov 22 2060 13:09:57 GMT+0000. This will cause the next year 2000 effect, I know.
define("BIGDATE", 2868354597);


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
      $my_end_date = date('U', mktime(0, 0, 0, "12", "31", $datecreated));
  
      $query->condition('created', array($my_start_date, $my_end_date), 'BETWEEN');
    }
  
    if(isset($dc) || isset($datechanged)) {
      echo "Filtering by date changed/updated" . PHP_EOL;
  
      $changed_start_date = date('U', mktime(0, 0, 0, "1", "1", $datechanged));
      $changed_end_date = date('U', mktime(0, 0, 0, "12", "31", $datechanged));
  
      $query->condition('changed', array($changed_start_date, $changed_end_date), 'BETWEEN');
    }
  
    // Get the queries.
    $results = $query->execute();

    return $results;
}


/*
* Fetch users
*/
function fetchUsers($query, $datecreated, $datechanged) {
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
    $changed_end_date = date('U', mktime(0, 0, 0, "12", "31", $datechanged));

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
  $queryComments->fields('c', array('cid', 'uid', 'nid', 'created', 'changed', 'status'));
  //$queryComments->fields('fic', array('field_issue_credit_target_id'));
  //$queryComments->leftJoin('field_data_field_issue_credit','fic','c.cid = fic.field_issue_credit_target_id');
  $queryComments->orderBy('created', 'ASC');

  $queryComments->condition('uid', $uid);
  // TODO: Needs to be fixed or closed/fixed
  //$queryComments->condition('status', 1); // STATUS 1 IS FOUND. Why?????
  // Order so the first result will be the oldest comment.
  // .

  return $queryComments->execute();
}

/*
* Fetch nodes where user has been credited.
*/
function fetchAllNodesWithCredits($uid, $limit = 10000) {
  // SELECT distinct node.nid FROM node  
  $queryComments = db_select('node', 'n');
  $queryComments->fields('n', array('nid', 'uid', 'created', 'changed', 'status'));
  if ($limit != NULL) {
    $queryComments->range(0, $limit);
  }

  // JOIN field_data_field_issue_credit ON field_data_field_issue_credit.entity_id = node.nid
  $queryComments->join('field_data_field_issue_credit','fdic','fdic.entity_id = n.nid');

  // JOIN comment ON comment.cid = field_data_field_issue_credit.field_issue_credit_target_id  
  $queryComments->join('comment','c','c.cid = fdic.field_issue_credit_target_id');

  // INNER JOIN field_data_field_issue_status ON node.nid = field_data_field_issue_status.entity_id 
  // AND (field_data_field_issue_status.entity_type = 'node' AND field_data_field_issue_status.deleted = '0')
  $queryComments->innerjoin('field_data_field_issue_status','fdis',"fdis.entity_id = n.nid AND (fdis.entity_type = 'node' AND fdis.deleted = '0')");

  $queryComments->condition('c.uid', $uid);
  // WHERE comment.uid = 2416470 AND node.type = 'project_issue' 
  $queryComments->condition('n.type', 'project_issue');
  // AND node.status = '1'
  $queryComments->condition('n.status', '1');
  // AND field_data_field_issue_status.field_issue_status_value IN  ('2', '7');
  // We hard code the status as it's a requirement for credits.
  $fieldStatus = Array(2,7);
  $queryComments->condition('field_issue_status_value', $fieldStatus, 'IN');

  // return $queryComments->execute()->fetchAll();
  return $queryComments->distinct()->execute()->fetchAll();
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
* Get the earliest comment and date where the user has a credit
*/
function getCommentsNode($origNode, $uid) {
  $createdDate = BIGDATE;
  if(!empty($origNode->nid)) {
    $node = node_load($origNode->nid);

    if(isset($node)) {
      echo "node:: " . $node->nid;
      $allComments = comment_get_thread($node, COMMENT_MODE_FLAT, 10000);

      print_r($allComments);

     foreach($allComments as $comment) {
      $currentComment = comment_load($comment);

      if (!empty($currentComment->uid)) {
        $cuid = $currentComment->uid;
        if ($cuid == $uid) {
            // Let's find the earliest comment, which we'll take as this user 1st contribution.
            if ($createdDate > $currentComment->created) {
              $createdDate = $currentComment->created;
              $firstCommentDate = $currentComment;
            }
        }
      }
     }

     echo PHP_EOL . "date:: " . $firstCommentDate->created . PHP_EOL;
     return $firstCommentDate;
    }
  }
}


/*
* Find if the current node contains users who have been credited for their contribution.
*/
function getCreditedUsers($node, $makers) {

  $comments = comment_get_thread($node, COMMENT_MODE_FLAT, 400);

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

/*
* 4.x-dev
*/
function legacyTwoDigits($input) {
    $regex = '/^\\d\\.x[-A-Za-z]*$/i';

    return preg_match($regex, $input);
}

/*
* 2.x.0-dev
* 2.0.0-dev
* 5.1.0-rc1
*/

function modernThreeDigits($input) {
  // $regex = '/^\\d[A-Za-z0-9]+\\.[A-Za-z0-9]+\\.\\d-[A-Za-z]+$/i';
  // $regex = '/^[0-9]+\\.[A-Za-z0-9]+\\.[A-Za-z0-9]+-[A-Za-z]+$/i';
  $regex = '/^[0-9]+\\.[A-Za-z0-9]+\\.[A-Za-z0-9]+[-A-Za-z0-9]*$/i';

  return preg_match($regex, $input);
}



/*
* Drupal modern == Drupal 7 or older.
*/
function isDrupalLegacy($version) {
    // $regex = '/\\d\\.[A-Za-z0-9]+-[A-Za-z]+/i';
    $regex = '/^\\d\\.[A-Za-z0-9]+-[A-Za-z]+$/i';
    return preg_match($regex, $version);
}

/*
* Drupal modern == Drupal 7 or older.
* 6.x-1.x-dev
* NOT 8.x-4.4
*/
function legacyDrupalModuleVersion($version) {
    $regex = '/^\\d\\.x-\\d\\.x[-A-Za-z0-9]+$/i';
    return preg_match($regex, $version);
}

/*
* Drupal modern == Drupal 7 or older.
* 8.x-2.x , 8.x-2.1 8.x-2.1-beta
*/
function legacyDrupalD8Module($version) {
  $regex = '/^\\d\\.x-\\d\\.x-[A-Za-z]+$/i';
  $regex = '/^8\\.x-[0-9]+\\.[A-Za-z0-9]+[-A-Za-z0-9]*$/i';
          // ^8\.x-[0-9]+\.[A-Za-z0-9]+[-A-Za-z0-9]*$
  return preg_match($regex, $version);
}

/*
* Drupal modern == Drupal 8 or later.
*/
function isDrupalModern($version) {
  $regex = '/^\\d+\\.[A-Za-z0-9]+-[A-Za-z]+$/i';
  return preg_match($regex, $version);
}


/*
1|Active
13|Needs work
8|Needs review
14|Reviewed & tested by the community
15|Patch (to be ported)
2|Fixed
4|Postponed
16|Postponed (maintainer needs more info)
3|Closed (duplicate)
5|Closed (won't fix)
6|Closed (works as designed)
18|Closed (cannot reproduce)
17|Closed (outdated)
7|Closed (fixed)
*/
