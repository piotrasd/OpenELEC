<?php

require_once('/usr/local/emhttp/update.php');


$varGPU = empty($_POST['gpu']) ? '' : $_POST['gpu'];
$varAudio = empty($_POST['audio']) ? '' : $_POST['audio'];
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
$arrPassthruDevices = array_filter([$varGPU, $varAudio]);
foreach ($arrPassthruDevices as $strPassthruDevice) {
	// Ensure we have leading 0000:
	$strPassthruDevice = '0000:' . str_replace('0000:', '', $strPassthruDevice);

	// Determine the driver currently assigned to the device
	$strDriverSymlink = @readlink('/sys/bus/pci/devices/' . $strPassthruDevice . '/driver');

 	if ($strDriverSymlink == '/sys/bus/pci/drivers/vfio-pci/') {
 		// Driver bound to vfio-pci already
  		write_log('Device ' . str_replace('0000:', '', $strPassthruDevice) . ' already using vfio-pci driver<br>');
 		continue;
 	} else if ($strDriverSymlink !== false) {
 		// Driver bound to some other driver
 		// Attempt to unbind driver
 		write_log('Unbinding device ' . str_replace('0000:', '', $strPassthruDevice) . ' from current driver...');
 		if (file_put_contents('/sys/bus/pci/devices/' . $strPassthruDevice . '/driver/unbind', $strPassthruDevice) === false) {
			write_log('FAILED');
			sleep(5);
			exit(1);
		} else {
			write_log('Ok<br>');
 		}
	}

	// Get Vendor and Device IDs for the passthru device
	$strVendor = file_get_contents('/sys/bus/pci/devices/' . $strPassthruDevice . '/vendor');
	$strDevice = file_get_contents('/sys/bus/pci/devices/' . $strPassthruDevice . '/device');

	// Attempt to bind driver to vfio-pci
 	write_log('Binding device ' . str_replace('0000:', '', $strPassthruDevice) . ' to vfio-pci driver...');
	if (file_put_contents('/sys/bus/pci/drivers/vfio-pci/new_id', $strVendor . ' ' . $strDevice) === false) {
		write_log('FAILED');
		sleep(5);
		exit(1);
	} else {
		write_log('Ok<br>');
	}

}


// Open the seed xml
write_log('Parsing seed xml file...<br>');
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
write_log('Saving generated xml file...');
if (file_put_contents('/tmp/OpenELEC.xml', $strXMLFile) === false) {
	write_log('FAILED');
	sleep(5);
	exit(1);
} else {
	write_log('Ok<br>');
}

// Ensure NODATACOW is set to all KVM images
// passthru('chattr +C /mnt/cache/vms/kvm/');

// Start the VM
write_log('Starting VM...<br>');
write_log(exec('virsh create /tmp/OpenELEC.xml'));
sleep(5);
