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
