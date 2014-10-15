<?php

$varGPU = $_POST['gpu'];
$varAudio = $_POST['audio'];
$varUSBList = empty($_POST['usb']) ? [] : $_POST['usb'];
$varMAC = empty($_POST['mac']) ? '52:54:00:xx:xx:xx' : $_POST['mac'];
$varBridge = $_POST['bridge'];
$varReadonly = empty($_POST['readonly']) ? '' : '<readonly/>';


// Replace wildcard chars in MAC
$varMACparts = explode(':', $varMAC);
$varMACparts = array_filter($varMACparts);
for ($i=0; $i < 6; $i++) {
	if (empty($varMACparts[$i]) || stripos($varMACparts[$i], 'x') !== false) {
		$varMACparts[$i] = dechex(rand(0, 255));
	}
}
$varMAC = implode(':', $varMACparts);


// VFIO the GPU and Audio
passthru('/usr/local/sbin/vfio-bind 0000:' . $varGPU . ' 0000:' . $varAudio);


// Open the seed xml
$strXMLFile = file_get_contents(__DIR__ . '/OpenELEC.xml');


// Replace variables
$strXMLFile = str_replace('{{GPU_ADDR}}', $varGPU, $strXMLFile);
$strXMLFile = str_replace('{{AUDIO_ADDR}}', $varAudio, $strXMLFile);
$strXMLFile = str_replace('{{NET_MAC}}', $varMAC, $strXMLFile);
$strXMLFile = str_replace('{{NET_BRIDGE}}', $varBridge, $strXMLFile);
$strXMLFile = str_replace('{{MOUNT_READONLY}}', $varReadonly, $strXMLFile);

// loop through usb devices and replace with blocks of code
$varUSBDevices = '';
foreach ($varUSBList as $varUSBItem) {
	list($vendor, $product) = explode(':', $varUSBItem);

	if (empty($vendor) || empty($vendor)) {
		continue;
	}

	$varUSBDevices .= "	<hostdev mode='subsystem' type='usb'>\n";
	$varUSBDevices .= "		<source>\n";
	$varUSBDevices .= "			<vendor id='0x" . $vendor . "'/>\n";
	$varUSBDevices .= "			<product id='0x" . $product . "'/>\n";
	$varUSBDevices .= "		</source>\n";
	$varUSBDevices .= "	</hostdev>\n\n";
}
$strXMLFile = str_replace('{{USB_DEVICES}}', $varUSBDevices, $strXMLFile);


// Save the modified xml to the tmp folder
file_put_contents('/tmp/OpenELEC.xml', $strXMLFile);

// Ensure NODATACOW is set to all KVM images
// passthru('chattr +C /mnt/cache/vms/kvm/');

// Start the VM
passthru('virsh create /tmp/OpenELEC.xml');
