<?php
/**
 * Mapping Kalkulator - Speziell fuer den Mapping-Contest der BPM2015 konzipiert
 * https://ai.wu.ac.at/emisa2015/contest.php
 */
$start = time();
require 'autoloader.php';

print("\n----------------------------------------------------------\n RefModMining - N-Ary Semantic Cluster Matching (BPM2015) \n----------------------------------------------------------\n");

// Hilfeanzeige auf CLI
if ( !isset($argv[1]) || !isset($argv[2]) || !isset($argv[3]) || !isset($argv[4]) ) {
	exit("   Optionen:\n
   input=           path to input epml file
   output=          path to output file
   format=          zip (including rdf and txt matchings) | xml (rmm)
   notification=
      no
      [E-Mail adress]

   please user the correct order!
			
ERROR: Parameters incomplete");
};

// Checking Parameters
$input   	  = substr($argv[1], 6,  strlen($argv[1]));
$output  	  = substr($argv[2], 7,  strlen($argv[2]));
$format  	  = substr($argv[3], 7,  strlen($argv[3]));
$email        = substr($argv[4], 13, strlen($argv[4]));

print("
input: ".$input."
output: ".$output."
format: ".$format."
notification: ".$email."

checking input parameters ...
");

// Check input
if ( file_exists($input) ) {
	print "  input ... ok\n";
} else {
	exit("  input ... failed (file does not exist)");
}

// Check format
if ( $format == "zip" || $format == "xml" ) {
	print "   format ... ok\n";
} else {
	exit("  format ... failed (only zip and xml are allowed)");
}

// Check notification
$doNotify = true;
if ( empty($email) || $email == "no" ) {
	$doNotify = false;
	print "  notification ... ok (notification disabled)\n";
} else {
	print "  notification ... ok (mail to ".$email.")\n";
}

// Laden der Modelldateien
$content_file_1 = file_get_contents($input);
$xml1 = new SimpleXMLElement($content_file_1);

// Vorbereitung der Forschrittsanzeige
$modelsInFile1 = count($xml1->xpath("//epc"));
$numOfAllModels = $modelsInFile1;
$countCombinations = (($modelsInFile1 * $modelsInFile1)/2)-($modelsInFile1/2);
$countCompletedCombinations = 0;
$progress = 0.1;

// Ausgabe der Informationen zum Skript-Run auf der Kommandozeile
print("\nNumber of models: ".count($xml1->xpath("//epc"))."\n");
print("Number of model permutations: ".$countCombinations);

// ReadMe.txt erzeugen
$readme = "--------------------------------------------------------------------------\r\n";
$readme .= " RMMaaS - Process Matching - N-Ary Homogeneity-based Cluster Matching (PMC2015)\r\n";
$readme .= "--------------------------------------------------------------------------\r\n\r\n";
$readme .= "Log:\r\n";
$readme .= " - Model file:  ".$input." (".$modelsInFile1." models)\r\n";
$readme .= " - Number of model pairs: ".$countCombinations;

$generatedFiles = array();
$generatedRDFs = array();
$naryMapping = new NAryWordstemMappingWithAntonyms2015();
foreach ($xml1->xpath("//epc") as $xml_epc1) {
	$nameOfEPC1 = (string) $xml_epc1["name"];
	$epc = new EPC($xml1, $xml_epc1["epcId"], $xml_epc1["name"]);
	$naryMapping->addEPC($epc);
}
//$naryMapping->sortEPCsByName();
$naryMapping->solveShortcuts();

$clusterFiles = $naryMapping->map2015(false);
foreach ( $clusterFiles as $filename ) {
	array_push($generatedFiles, $filename);
}


$antonymCache = NLP::loadAntonymLabelsFromPersistedFile();
$nonAntonymCache = NLP::loadNonAntonymLabelsFromPersistedFile();

$corrspondencyCache = NLP::loadCorrespondentLabelsFromPersistedFile();
$nonCorrespondencyCache = NLP::loadNonCorrespondentLabelsFromPersistedFile();


print("\n\nDerive binary mapping and do post-processing...\n");
// Extract binary mappings
for ( $i=0; $i<$modelsInFile1; $i++ ) {
	for ( $j=$i+1; $j<$modelsInFile1; $j++ ) {
	//for ( $j=0; $j<$modelsInFile1; $j++ ) {
		$mapping = $naryMapping->extractBinaryMapping($naryMapping->epcs[$i], $naryMapping->epcs[$j]);
		$mapping->map("AllOne");
		$mapping->deleteDummyTransitions();
		
		print(" current model pair: ".$mapping->epc1->name." => ".$mapping->epc2->name."\n");		
		$mapping->removeAntonymMaps($antonymCache, $nonAntonymCache);
		$mapping->removeMissingVerbObjectCorrespondentMaps($corrspondencyCache, $nonCorrespondencyCache);
		
		if ( $numOfAllModels == 2 && $naryMapping->harmonizationDegree == 1 ) {
			$mapping->removeMapsCausedByHarmonizedModel();
		}
		
		$mapping->removeMatchesForWhichAnAdversingIdentyMatchIsAvailable();
		$mapping->improveComplexMatchesBasedOnContext();
		
		if ( $naryMapping->harmonizationDegree >= ($naryMapping->threshold_ontology_quote/100) ) {
			$mapping->improveComplexMatchesCausedByHarmonizedModels();
		}
		
		//$file = $mapping->exportAndreasSonntag();
		//$file = $mapping->export();
		//array_push($generatedFiles, $file);
		
		$genericMapping = $mapping->convertToGenericMapping();
		
		//$file = $genericMapping->exportTXT_BPMContest2013($naryMapping->epcs[$i], $naryMapping->epcs[$j]);
		//$file = $genericMapping->exportRDF_BPMContest2015();
		//array_push($generatedFiles, $file);
		
		if ( $numOfAllModels == 2 ) {
			
			$file = $genericMapping->exportRDF_BPMContest2015_Dataset3();
			array_push($generatedFiles, $file);
			
		} else {
		
			$file = $genericMapping->exportRDF_BPMContest2015();
			array_push($generatedFiles, $file);
			array_push($generatedRDFs, $file);
			
			$file = $genericMapping->exportRDF_BPMContest2015(true);
			array_push($generatedFiles, $file);
		
		}
		
		// FORTSCHRITTSANZEIGE
		//print(".");
		$countCompletedCombinations++;
		if ( ($countCompletedCombinations/$countCombinations) >= $progress ) {
			//print(" ".($progress*100)."% ");
			$progress += 0.1;
		}
		// ENDE DER FORTSCHRITTSANZEIGE
	}
}

print("\ndone");

// Berechnungdauer
$duration = time() - $start;
$seconds = $duration % 60;
$minutes = floor($duration / 60);

$readme .= "\r\n\r\nEnd: ".date("d.m.Y H:i:s")."\r\n";
$readme .= "Duration: ".$minutes." Min. ".$seconds." Sec.";
$fileGenerator = new FileGenerator("ReadMe.txt", $readme);
$fileGenerator->setFilename("ReadMe.txt");
$fileGenerator->setContent($readme);
$uri_readme_txt = $fileGenerator->execute();
array_push($generatedFiles, $uri_readme_txt);
// AUSGABEDATEIEN ERSTELLT


if ( $format == "zip" ) {
	// ZIP ALL FILES
	print("\n\nZip files ... ");
	$zip = new ZipArchive();
	if ( $zip->open($output, ZipArchive::CREATE) ) {
		foreach ( $generatedFiles as $filename ) {
			$pos = strrpos($filename, "/");
			$file = substr($filename, $pos+21);
			$zip->addFile($filename, $file);
		}
		$zip->close();
		foreach ( $generatedFiles as $filename ) {
			unlink($filename);
		}
		$numFiles = count($generatedFiles);
		print("done (#files: ".$numFiles.", status".$zip->status.")");
	} else {
		exit("\nCannot open <".$output.">. Error creating zip file.\n");
	}
	// ZIP COMPLETED
} elseif ( $format == "xml" ) {
	
	// CREATE XML from single RDFs by RefMod-Miner Matching Converter
	print("\n\nCreating aggregated matching XML file . ");
	
	$_POST["matchings"] = implode(",", $generatedRDFs);
	$_POST["model_set"] = $input;
	$_POST["output_file"] = $output;
	$actionHandler = new WorkspaceActionHandler();
	$actionHandler->run("CONVERT_MATCHING");
		
	foreach ( $generatedFiles as $filename ) {
		unlink($filename);
	}
	
 	sleep(1); print("."); sleep(1); print("."); sleep(1);
	
	print(" done");
}


// Extract session ID from uri
$sid = $output;
$sid = str_replace("workspace/", "", $sid);
$pos = strpos($sid, "/");
$sid = $pos ? substr($sid, 0, $pos) : $sid;
$readme .= "Your workspace: ".Config::WEB_PATH."index.php?sid=".$sid."&site=workspace";

if ( $doNotify ) {
	print("\n\nSending notification ... ");
	$notificationResult = EMailNotifyer::sendCLINSCMNotification($email, $readme);
	if ( $notificationResult ) {
		print("ok");
	} else {
		print("error");
	}
}

print("\n\nDuration: ".$minutes." Min. ".$seconds." Sec.\n");
print("N-Ary Semantic Cluster Matching finished successfully.\n");

Logger::log($email, "CLINarYSemanticClusterMatching finished: input=".$input." output=".$output, "ACCESS");
?>
