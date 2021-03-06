<?php
$start = time();
require 'autoloader.php';

print("\n-------------------------------------------------\n RefModMining - Label Tagger \n-------------------------------------------------\n\n");

// Hilfeanzeige auf Kommandozeile
if ( !isset($argv[1]) || !isset($argv[2]) || !isset($argv[3]) || !isset($argv[4]) ) {
	exit("   Please provide the following parameters:\n
   input=           path to input epml
   output=          path to output csv
   language=        de | en
   notification=
      no
      [E-Mail adress]

   please user the correct order!
			
ERROR: Parameters incomplete
");
}

// Checking Parameters
$input   = substr($argv[1], 6,  strlen($argv[1]));
$output   = substr($argv[2], 7,  strlen($argv[2]));
$lang   = strtolower(substr($argv[3], 9, strlen($argv[3])));
$email   = substr($argv[4], 13, strlen($argv[4]));

print("
input: ".$input."
output: ".$output."
language: ".$lang."
notification: ".$email."

checking input parameters ...
");

// Check input
if ( file_exists($input) ) {
	print "  input ... ok\n";
} else {
	exit("  input ... failed (file does not exist)\n\n");
}

// Check language
if ( $lang == "de" || $lang == "en" ) {
	print "  language ... ok\n";
} else {
	exit("  language ... failed (language unknown)\n\n");
}

// Check notification
$doNotify = true;
if ( empty($email) || $email == "no" ) {
	$doNotify = false;
	print "  notification ... ok (notification disabled)\n";
} else {
	print "  notification ... ok (mail to ".$email.")\n";
}


// Verarbeitung der Modelldatei
$content_file = file_get_contents($input);
$xml = new SimpleXMLElement($content_file);
$modelsInFile = count($xml->xpath("//epc"));

// print infos to console
print("\nModel file: ".$input."\n");
print("Number of models: ".$modelsInFile."\n\n");
print("Start NLP tagging ...\n");

// initiate progress bar
$modelCount = 0;
$progressBar = new CLIProgressbar($modelsInFile, 0.1);
$progressBar->run($modelCount);

$generatedFiles = array();
$csvContent = "model;node-type;id;label;tagged-label;tag-set;high-level-tag-set;label-style";

// Tagging model selection
$pos_tagger_model = "";
switch ( $lang ) {
	case "de": $pos_tagger_model = "german-fast.tagger"; break;
	default: $pos_tagger_model = "english-left3words-distsim.tagger"; break;
}

// Analyze all nodes in all models in the file
foreach ($xml->xpath("//epc") as $xml_epc) {
	$epc = new EPCNLP($xml, $xml_epc["epcId"], $xml_epc["name"]);
	$epc->loadLabelTags($pos_tagger_model);
	$epc->generateHighLevelLabelTags($lang);
	$epc->detectLableStyles($lang);
	//var_dump($epc);
	$csvContent .= $epc->getEPMLNLPAnalysisCSVPart();
	//$file_uri = $epc->exportNLPAnalysisCSV();
	//array_push($generatedFiles, $file_uri);
	$modelCount++;
	$progressBar->run($modelCount);
}
print(" done");

//print("\n\nGenerated files:");
//foreach ( $generatedFiles as $uri ) print("\n   ".$uri);

// ERSTELLEN DER AUSGABEDATEIEN
$fileGenerator = new FileGenerator($output, $csvContent);
$fileGenerator->setPathFilename($output);
$fileGenerator->setContent($csvContent);
$uri_csv = $fileGenerator->execute(false);
// AUSGABEDATEIEN ERSTELLT

$readme  = "NLP-Tagging for model file ".$input." successfully finished.";
$sid = $uri_csv;
$sid = str_replace("workspace/", "", $sid);
$pos = strpos($sid, "/");
$sid = $pos ? substr($sid, 0, $pos) : $sid;
$readme .= "\n\nYour workspace: ".Config::WEB_PATH."index.php?sid=".$sid."&site=workspace";
//$readme .= "\r\n\r\nGenerated files:";
//$readme .= implode("\r\n   ", $generatedFiles);

// Berechnungdauer
$duration = time() - $start;
$seconds = $duration % 60;
$minutes = floor($duration / 60);

$readme .= "\r\n\r\nDuration: ".$minutes." Min. ".$seconds." Sec.";

if ( $doNotify ) {
	print("\n\nSending notification ... ");
	$notificationResult = EMailNotifyer::sendCLIModelNLPTaggingNotification($email, $readme);
	if ( $notificationResult ) {
		print("ok");
	} else {
		print("error");
	}
}

// Ausgabe der Dateiinformationen auf der Kommandozeile
print("\n\nDuration: ".$minutes." Min. ".$seconds." Sec.\n\n");
Logger::log($email, "CLIModelNLPTagger finished: input=".$input." output=".$output, "ACCESS");
?>