<?php

// Allow CORS for requests from your GitHub Pages URL
header("Access-Control-Allow-Origin: https://yourusername.github.io");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require 'vendor/autoload.php'; // Include the Composer autoload file

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// AWS S3 configuration
$bucketName = 'YOUR_BUCKET_NAME';  // Replace with your S3 bucket name
$region = 'YOUR_BUCKET_REGION';     // Replace with your S3 bucket region
$accessKey = 'YOUR_ACCESS_KEY';     // Replace with your Access Key ID
$secretKey = 'YOUR_SECRET_KEY';     // Replace with your Secret Access Key
$filepathPostfix = ".csv";

// Initialize S3 client
$s3Client = new S3Client([
  'version' => 'latest',
  'region' => $region,
  'credentials' => [
      'key' => $accessKey,
      'secret' => $secretKey,
  ],
]);

// Create and upload CSV data to S3
function uploadToS3($s3Client, $bucketName, $key, $data) {
	try {
		$s3Client->putObject([
			'Bucket' => $bucketName,
			'Key'    => $key,
			'Body'   => $data,
			'ContentType' => 'text/csv',
		]);
		echo "Uploaded: {$key}\n";
	} catch (AwsException $e) {
		echo "Error uploading: {$e->getMessage()}\n";
	}
}

function sanitize($string = '', $is_filename = FALSE) {
    // Replace all weird characters with dashes
    $string = preg_replace('/[^\w\-'. ($is_filename ? '~_\.' : ''). ']+/u', '-', $string);
    // Only allow one dash separator at a time (and make string lowercase)
    return strtolower(preg_replace('/--+/u', '-', $string));
}

// Add a unique identifier to the filename
function generateUniqueFilename($baseFilename) {
  // Use timestamp and random number to ensure uniqueness
  $timestamp = date('YmdHis');
  $randomString = bin2hex(random_bytes(4)); // 8 character random string
  return $baseFilename . "_" . $timestamp . "_" . $randomString . ".csv";
}


$sessionParam = null;

//get_magic_quotes_gpc() was removed in PHP 8
if(version_compare(PHP_VERSION, '8.0.0', '<') and get_magic_quotes_gpc()){
	$sessionParam = stripslashes($_POST['sessionJSON']);
}else{
	$sessionParam = $_POST['sessionJSON'];
}


$session = json_decode($sessionParam);


$filepathPrefix = "../results/".sanitize($string = $session->testId, $is_filename =FALSE)."/";
$filepathPostfix = ".csv";

// Log file path for reference
error_log("Result saved to: " . $filepathPostfix);

if (!is_dir($filepathPrefix)) {
    mkdir($filepathPrefix);
}
$length = count($session->participant->name);


// MUSHRA
$write_mushra = false;
$mushraCsvData = array();

$input = array("session_test_id");
for($i =0; $i < $length; $i++){
	array_push($input, $session->participant->name[$i]);
}
array_push($input, "session_uuid", "trial_id", "rating_stimulus", "rating_score", "rating_time", "rating_comment");
array_push($mushraCsvData, $input);
 
foreach ($session->trials as $trial) {
  if ($trial->type == "mushra") {
  $write_mushra = true;

    foreach ($trial->responses as $response) {


    $results = array($session->testId);
    for($i =0; $i < $length; $i++){
      array_push($results, $session->participant->response[$i]);
    }
    array_push($results, $session->uuid, $trial->id, $response->stimulus, $response->score, $response->time, $response->comment);

      array_push($mushraCsvData, $results);


    }
      /*array_push($mushraCsvData, array($session->testId, $session->participant->email, $session->participant->age, $session->participant->gender, $trial->id, $response->stimulus, $response->score, $response->time, $response->comment));
     *
     */
  }
}
		
// if ($write_mushra) {
// 	$filename = $filepathPrefix."mushra".$filepathPostfix;
// 	$isFile = is_file($filename);
// 	$fp = fopen($filename, 'a');
// 	foreach ($mushraCsvData as $row) {
// 		if ($isFile) {	    	
// 			$isFile = false;
// 		} else {
// 		   fputcsv($fp, $row);
// 		}
// 	}
// 	fclose($fp);
// }
if ($write_mushra) {
	$csvData = '';
	ob_start(); // Start output buffering
	$fp = fopen('php://output', 'w');
	foreach ($mushraCsvData as $row) {
		fputcsv($fp, $row);
	}
	fclose($fp);
	$csvData = ob_get_clean(); // Get contents of the output buffer

	$baseFilename = "mushra/" . sanitize($session->testId);
	// $filename = "mushra/" . sanitize($session->testId) . $filepathPostfix; // S3 path
	$uniqueFilename = generateUniqueFilename($baseFilename);
	
	// uploadToS3($s3Client, $bucketName, $filename, $csvData); // Upload to S3
	uploadToS3($s3Client, $bucketName, $uniqueFilename, $csvData);
}



// paired comparison
$write_pc = false;
$pcCsvData = array();
// array_push($pcCsvData, array("session_test_id", "participant_email", "participant_age", "participant_gender", "trial_id", "choice_reference", "choice_non_reference", "choice_answer", "choice_time", "choice_comment"));

$input = array("session_test_id");
for($i =0; $i < $length; $i++){
	array_push($input, $session->participant->name[$i]);
}
array_push($input, "trial_id", "choice_reference", "choice_non_reference", "choice_answer", "choice_time", "choice_comment");
array_push($pcCsvData, $input);



foreach ($session->trials as $trial) {
  if ($trial->type == "paired_comparison") {
	  foreach ($trial->responses as $response) {	  	
	  	$write_pc = true;
		  
		 
		$results = array($session->testId);
		for($i =0; $i < $length; $i++){
			array_push($results, $session->participant->response[$i]);
		}  
		array_push($results, $trial->id, $response->reference, $response->nonReference, $response->answer, $response->time, $response->comment);
	  
	  	array_push($pcCsvData, $results); 
		  
		  
	    // array_push($pcCsvData, array($session->testId, $session->participant->email, $session->participant->age, $session->participant->gender, $trial->id, $response->reference, $response->nonReference, $response->answer, $response->time, $response->comment));    
	  }
  }
}

// if ($write_pc) {
// 	$filename = $filepathPrefix."paired_comparison".$filepathPostfix;
// 	$isFile = is_file($filename);
// 	$fp = fopen($filename, 'a');
// 	foreach ($pcCsvData as $row) {
// 		if ($isFile) {	    	
// 			$isFile = false;
// 		} else {
// 		   fputcsv($fp, $row);
// 		}
// 	}
// 	fclose($fp);
// }
if ($write_pc) {
  $csvData = '';
  ob_start(); // Start output buffering
  $fp = fopen('php://output', 'w');
  foreach ($pcCsvData as $row) {
      fputcsv($fp, $row);
  }
  fclose($fp);
  $csvData = ob_get_clean(); // Get contents of the output buffer

  $filename = "pc/" . sanitize($session->testId) . $filepathPostfix; // S3 path
  uploadToS3($s3Client, $bucketName, $filename, $csvData); // Upload to S3
}



// bs1116
$write_bs1116 = false;
$bs1116CsvData = array();

$input = array("session_test_id");
for($i =0; $i < $length; $i++){
	array_push($input, $session->participant->name[$i]);
}
array_push($input,  "trial_id", "rating_reference", "rating_non_reference", "rating_reference_score", "rating_non_reference_score", "rating_time", "choice_comment");
array_push($bs1116CsvData, $input);

// array_push($bs1116CsvData, array("session_test_id", "participant_email", "participant_age", "participant_gender", "trial_id", "rating_reference", "rating_non_reference", "rating_reference_score", "rating_non_reference_score", "rating_time", "choice_comment"));
foreach ($session->trials as $trial) {
  if ($trial->type == "bs1116") {
	  foreach ($trial->responses as $response) {	  	
	  	$write_bs1116 = true;
		  
		$results = array($session->testId);
		for($i =0; $i < $length; $i++){
			array_push($results, $session->participant->response[$i]);
		}  
		array_push($results, $trial->id, $response->reference, $response->nonReference, $response->referenceScore, $response->nonReferenceScore, $response->time, $response->comment);
	  
	  	array_push($bs1116CsvData, $results); 
		  
	    // array_push($bs1116CsvData, array($session->testId, $session->participant->email, $session->participant->age, $session->participant->gender, $trial->id, $response->reference, $response->nonReference, $response->referenceScore, $response->nonReferenceScore, $response->time, $response->comment));    
	  }
  }
}

// if ($write_bs1116) {
// 	$filename = $filepathPrefix."bs1116".$filepathPostfix;
// 	$isFile = is_file($filename);
// 	$fp = fopen($filename, 'a');
// 	foreach ($bs1116CsvData as $row) {
// 		if ($isFile) {	    	
// 			$isFile = false;
// 		} else {
// 		   fputcsv($fp, $row);
// 		}
// 	}
// 	fclose($fp);
// }
if ($write_bs1116) {
  $csvData = '';
  ob_start(); // Start output buffering
  $fp = fopen('php://output', 'w');
  foreach ($bs1116CsvData as $row) {
      fputcsv($fp, $row);
  }
  fclose($fp);
  $csvData = ob_get_clean(); // Get contents of the output buffer

  $filename = "bs1116/" . sanitize($session->testId) . $filepathPostfix; // S3 path
  uploadToS3($s3Client, $bucketName, $filename, $csvData); // Upload to S3
}


//lms
$write_lms = false;
$lmsCSVdata = array();
// array_push($lmsCSVdata, array("session_test_id", "participant_email", "participant_age", "participant_gender", "trial_id", "stimuli_rating", "stimuli", "rating_time"));

$input = array("session_test_id");
for($i =0; $i < $length; $i++){
	array_push($input, $session->participant->name[$i]);
}
array_push($input,  "trial_id", "stimuli_rating", "stimuli", "rating_time");
array_push($lmsCSVdata, $input);


foreach($session->trials as $trial) {
	if($trial->type == "likert_multi_stimulus") {
		foreach ($trial->responses as $response) {
			$write_lms = true; 
			
			$results = array($session->testId);
			for($i =0; $i < $length; $i++){
				array_push($results, $session->participant->response[$i]);
			}  
			array_push($results,  $trial->id, " $response->stimulusRating ", $response->stimulus, $response->time);
		  
		  	array_push($lmsCSVdata, $results); 
			
			// array_push($lmsCSVdata, array($session->testId, $session->participant->email, $session->participant->age, $session->participant->gender, $trial->id, " $response->stimuliRating ", $response->stimuli, $response->time));
		}
	}
}

// if($write_lms){
// 	$filename = $filepathPrefix."lms".$filepathPostfix;
// 	$isFile = is_file($filename); 
// 	$fp = fopen($filename, 'a');
// 	foreach($lmsCSVdata as $row){
// 		if ($isFile){
// 			$isFile = false; 
// 		} else {
// 			fputcsv($fp,$row);
// 		}
// 	}
// 	fclose($fp);
// }
if ($write_lms) {
  $csvData = '';
  ob_start(); // Start output buffering
  $fp = fopen('php://output', 'w');
  foreach ($lmsCSVdata as $row) {
      fputcsv($fp, $row);
  }
  fclose($fp);
  $csvData = ob_get_clean(); // Get contents of the output buffer

  $filename = "lms/" . sanitize($session->testId) . $filepathPostfix; // S3 path
  uploadToS3($s3Client, $bucketName, $filename, $csvData); // Upload to S3
}


//lss
$write_lss = false;
$lssCSVdata = array();
// array_push($lssCSVdata, array("session_test_id", "participant_email", "participant_age", "participant_gender", "trial_id", "stimuli_rating", "stimuli", "rating_time"));

$input = array("session_test_id");
for($i =0; $i < $length; $i++){
	array_push($input, $session->participant->name[$i]);
}
array_push($input,  "trial_id");
$ratingCount = count($session->trials[0]->responses[0]->stimulusRating);
if($ratingCount > 1) {
    for($i =0; $i < $ratingCount; $i++){
        array_push($input, "stimuli_rating" . ($i+1));
    }
} else {
    array_push($input, "stimuli_rating");
}
array_push($input, "stimuli", "rating_time");
array_push($lssCSVdata, $input);

foreach($session->trials as $trial) {
	
	if($trial->type == "likert_single_stimulus") {
		foreach ($trial->responses as $response) {
			$write_lss = true; 
			
				$results = array($session->testId);
			for($i =0; $i < $length; $i++){
				array_push($results, $session->participant->response[$i]);
			}
            array_push($results, $trial->id);
            $results = array_merge($results, $response->stimulusRating);
            array_push($results, $response->stimulus, $response->time);
		  
		  	array_push($lssCSVdata, $results); 
			
			// array_push($lssCSVdata, array($session->testId, $session->participant->email, $session->participant->age, $session->participant->gender, $trial->id, " $response->stimulusRating ", $response->stimulus, $response->time));
		}
	}
}

// if($write_lss){
// 	$filename = $filepathPrefix."lss".$filepathPostfix;
// 	$isFile = is_file($filename); 
// 	$fp = fopen($filename, 'a');
// 	foreach($lssCSVdata as $row){
// 		if ($isFile){
// 			$isFile = false; 
// 		} else {
// 			fputcsv($fp,$row);
// 		}
// 	}
// 	fclose($fp);
// }
if ($write_lms) {
  $csvData = '';
  ob_start(); // Start output buffering
  $fp = fopen('php://output', 'w');
  foreach ($lssCSVdata as $row) {
      fputcsv($fp, $row);
  }
  fclose($fp);
  $csvData = ob_get_clean(); // Get contents of the output buffer

  $filename = "lss/" . sanitize($session->testId) . $filepathPostfix; // S3 path
  uploadToS3($s3Client, $bucketName, $filename, $csvData); // Upload to S3
}



//spatial
//localization
$write_spatial_localization = false;
$spatial_localizationData = array();

$input = array("session_test_id");
for($i =0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}
array_push($input,  "trial_id", "name", "stimulus", "position_x", "position_y", "position_z");
array_push($spatial_localizationData, $input);


// 
foreach ($session->trials as $trial) {
    
  if ($trial->type == "localization") {
    
      foreach ($trial->responses as $response) {        
        $write_spatial_localization = true;
    
        $results = array($session->testId);
        for($i =0; $i < $length; $i++){
            array_push($results, $session->participant->response[$i]);
        }  
array_push($results, $trial->id, $response->name, $response->stimulus, $response->position[0], $response->position[1], $response->position[2]);
      
      
        array_push($spatial_localizationData, $results); 
             
      }
  }
}

// if ($write_spatial_localization) {
    
//     $filename = $filepathPrefix."spatial_localization".$filepathPostfix;
//     $isFile = is_file($filename);
//     $fp = fopen($filename, 'a');
//     foreach ($spatial_localizationData as $row) {
//         if ($isFile) {          
//             $isFile = false;
//         } else {
//            fputcsv($fp, $row);
//         }
//     }
//     fclose($fp);
// }
if ($write_spatial_localization) {
  $csvData = '';
  ob_start(); // Start output buffering
  $fp = fopen('php://output', 'w');
  foreach ($spatial_localizationData as $row) {
      fputcsv($fp, $row);
  }
  fclose($fp);
  $csvData = ob_get_clean(); // Get contents of the output buffer

  $filename = "spatial_localization/" . sanitize($session->testId) . $filepathPostfix; // S3 path
  uploadToS3($s3Client, $bucketName, $filename, $csvData); // Upload to S3
}



//asw
$write_spatial_asw = false;
$spatial_aswData = array();

$input = array("session_test_id");
for($i =0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}

array_push($input,  "trial_id", "name", "stimulus", "position_outerRight_x", "position_outerRight_y", "position_outerRight_z", "position_innerRight_x", "position_innerRight_y", "position_innerRight_z", "position_innerLeft_x", "position_innerLeft_y", "position_innerLeft_z", "position_outerLeft_x", "position_outerLeft_y", "position_outerLeft_z");
array_push($spatial_aswData, $input);


// 
foreach ($session->trials as $trial) {

  if ($trial->type == "asw") {
    
      foreach ($trial->responses as $response) {        
        $write_spatial_asw = true;
    
        $results = array($session->testId);
        for($i =0; $i < $length; $i++){
            array_push($results, $session->participant->response[$i]);
        }  
                array_push($results, $trial->id, $response->name, $response->stimulus, $response->position_outerRight[0], $response->position_outerRight[1], $response->position_outerRight[2], $response->position_innerRight[0], $response->position_innerRight[1], $response->position_innerRight[2], $response->position_innerLeft[0], $response->position_innerLeft[1], $response->position_innerLeft[2], $response->position_outerLeft[0], $response->position_outerLeft[1], $response->position_outerLeft[2]);
        
        array_push($spatial_aswData, $results); 
             
      }
  }
}

// if ($write_spatial_asw) {

//     $filename = $filepathPrefix."spatial_asw".$filepathPostfix;
//     $isFile = is_file($filename);
//     $fp = fopen($filename, 'a');
//     foreach ($spatial_aswData as $row) {
//         if ($isFile) {          
//             $isFile = false;
//         } else {
//            fputcsv($fp, $row);
//         }
//     }
//     fclose($fp);
// }
if ($write_spatial_localization) {
  $csvData = '';
  ob_start(); // Start output buffering
  $fp = fopen('php://output', 'w');
  foreach ($spatial_aswData as $row) {
      fputcsv($fp, $row);
  }
  fclose($fp);
  $csvData = ob_get_clean(); // Get contents of the output buffer

  $filename = "spatial_asw/" . sanitize($session->testId) . $filepathPostfix; // S3 path
  uploadToS3($s3Client, $bucketName, $filename, $csvData); // Upload to S3
}


//hwd
$write_spatial_hwd = false;
$spatial_hwdData = array();

$input = array("session_test_id");
for($i =0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}

array_push($input,  "trial_id", "name", "stimulus", "position_outerRight_x", "position_outerRight_y", "position_outerRight_z", "position_innerRight_x", "position_innerRight_y", "position_innerRight_z", "position_innerLeft_x", "position_innerLeft_y", "position_innerLeft_z", "position_outerLeft_x", "position_outerLeft_y", "position_outerLeft_z", "height", "depth");
array_push($spatial_hwdData, $input);


// 
foreach ($session->trials as $trial) {

  if ($trial->type == "hwd") {
    
      foreach ($trial->responses as $response) {        
        $write_spatial_hwd = true;
    
        $results = array($session->testId);
        for($i =0; $i < $length; $i++){
            array_push($results, $session->participant->response[$i]);
        }  
                array_push($results, $trial->id, $response->name, $response->stimulus, $response->position_outerRight[0], $response->position_outerRight[1], $response->position_outerRight[2], $response->position_innerRight[0], $response->position_innerRight[1], $response->position_innerRight[2], $response->position_innerLeft[0], $response->position_innerLeft[1], $response->position_innerLeft[2], $response->position_outerLeft[0], $response->position_outerLeft[1], $response->position_outerLeft[2], $response->height, $response->depth);
        
        array_push($spatial_hwdData, $results); 
             
      }
  }
}

// if ($write_spatial_hwd) {
    
//     $filename = $filepathPrefix."spatial_hwd".$filepathPostfix;
//     $isFile = is_file($filename);
//     $fp = fopen($filename, 'a');
//     foreach ($spatial_hwdData as $row) {
//         if ($isFile) {          
//             $isFile = false;
//         } else {
//            fputcsv($fp, $row);
//         }
//     }
//     fclose($fp);
// }
if ($write_spatial_hwd) {
  $csvData = '';
  ob_start(); // Start output buffering
  $fp = fopen('php://output', 'w');
  foreach ($spatial_hwdData as $row) {
      fputcsv($fp, $row);
  }
  fclose($fp);
  $csvData = ob_get_clean(); // Get contents of the output buffer

  $filename = "spatial_hwd/" . sanitize($session->testId) . $filepathPostfix; // S3 path
  uploadToS3($s3Client, $bucketName, $filename, $csvData); // Upload to S3
}



//lev
$write_spatial_lev = false;
$spatial_levData = array();

$input = array("session_test_id");
for($i =0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}

array_push($input,  "trial_id", "name", "stimulus", "position_center_x", "position_center_y", "position_center_z", "position_height_x", "position_height_y", "position_height_z", "position_width1_x", "position_width1_y", "position_width1_z", "position_width2_x", "position_width2_y", "position_width2_z");
array_push($spatial_levData, $input);


// 
foreach ($session->trials as $trial) {
    
  if ($trial->type == "lev") {
    
      foreach ($trial->responses as $response) {        
        $write_spatial_lev = true;
    
        $results = array($session->testId);
        for($i =0; $i < $length; $i++){
            array_push($results, $session->participant->response[$i]);
        }  
        array_push($results, $trial->id, $response->name, $response->stimulus, $response->position_center[0], $response->position_center[1], $response->position_center[2], $response->position_height[0], $response->position_height[1], $response->position_height[2], $response->position_width1[0], $response->position_width1[1], $response->position_width1[2], $response->position_width2[0], $response->position_width2[1], $response->position_width2[2]);
        
        array_push($spatial_levData, $results); 
             
      }
  }
}

// if ($write_spatial_lev) {
//     $filename = $filepathPrefix."spatial_lev".$filepathPostfix;
//     $isFile = is_file($filename);
//     $fp = fopen($filename, 'a');
//     foreach ($spatial_levData as $row) {
//         if ($isFile) {          
//             $isFile = false;
//         } else {
//            fputcsv($fp, $row);
//         }
//     }
//     fclose($fp);
// }
if ($write_spatial_lev) {
  $csvData = '';
  ob_start(); // Start output buffering
  $fp = fopen('php://output', 'w');
  foreach ($spatial_levData as $row) {
      fputcsv($fp, $row);
  }
  fclose($fp);
  $csvData = ob_get_clean(); // Get contents of the output buffer

  $filename = "spatial_lev/" . sanitize($session->testId) . $filepathPostfix; // S3 path
  uploadToS3($s3Client, $bucketName, $filename, $csvData); // Upload to S3
}

?>
