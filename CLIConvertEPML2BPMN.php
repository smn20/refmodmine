<?php
$start = time();
require 'autoloader.php';

print("\n-------------------------------------------------\n RefModMining - EPC (EPML) to BPMN (BPMN) Converter \n-------------------------------------------------\n\n");

// Hilfeanzeige auf Kommandozeile
if ( !isset($argv[1]) || !isset($argv[2]) || !isset($argv[3]) ) {
	exit("   Please provide the following parameters:\n
   input=           path to input epml (containing one EPC only!)
   output=          path to output bpmn
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
$email   = substr($argv[3], 13, strlen($argv[3]));

print("
input: ".$input."
output: ".$output."
notification: ".$email."

checking input parameters ...
");

// Check input
if ( file_exists($input) ) {
	print "  input ... ok\n";
} else {
	exit("  input ... failed (file does not exist)\n\n");
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
$modelsInFile = count($xml->xpath("//process"));

// print infos to console
print("\nModel file: ".$input."\n");
print("Start EPML to BPMN transformation and conversion ...\n");

// Model transformation
foreach ($xml->xpath("//epc") as $xml_epc) {
	$epcID = isset($xml_epc["epcId"]) ? (string) $xml_epc["epcId"] : (string) $xml_epc["EpcId"];
	$epc = new EPC($xml, $epcID, $xml_epc["name"]);
	$bpmn = $epc->transformToBPMN();
	$generatedFile = $bpmn->exportBPMN();
	rename($generatedFile, $output);
	break;
}

print(" done");

$readme  = "EPC in ".$input." successfully converted to BPMN.";
$sid = $output;
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
	$notificationResult = EMailNotifyer::sendCLIConvertEPML2BPMNNotification($email, $readme);
	if ( $notificationResult ) {
		print("ok");
	} else {
		print("error");
	}
}

// Ausgabe der Dateiinformationen auf der Kommandozeile
print("\n\nDuration: ".$minutes." Min. ".$seconds." Sec.\n");
Logger::log($email, "CLIConvertEPML2BPMN finished: input=".$input." output=".$output, "ACCESS");
?>