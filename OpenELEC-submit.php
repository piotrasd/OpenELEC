<?php

readfile("/usr/local/emhttp/update.htm");

function write_log($string) {
	syslog(LOG_INFO, "OpenELEC plugin: " . trim(str_replace('<br>', '', $string)));
}

function write_display($string, $failure) {
	if ($failure) {
		$string = '<span style="color: #FF0000">' . $string . '</span>';
	}
	echo "<script>addLog(\"{$string}\");</script>";
}


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
	$strPassthruDeviceShort = str_replace('0000:', '', $strPassthruDevice);
	$strPassthruDeviceLong = '0000:' . $strPassthruDeviceShort;

	// Determine the driver currently assigned to the device
	$strDriverSymlink = @readlink('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/driver');

	if ($strDriverSymlink !== false) {
		// Device is bound to a Driver already

	 	if (strpos($strDriverSymlink, 'vfio-pci') !== false) {
	 		// Driver bound to vfio-pci already - nothing left to do for this device now regarding vfio
	  		write_display('<br>Device ' . $strPassthruDeviceShort . ' already using vfio-pci driver');
	 		continue;
	 	}

 		// Driver bound to some other driver - attempt to unbind driver
 		write_display('<br>Unbinding device ' . $strPassthruDeviceShort . ' from current driver...');
 		if (file_put_contents('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/driver/unbind', $strPassthruDeviceLong) === false) {
			write_display('FAILED', true);
			write_log('ERROR: Failed to unbind device ' . $strPassthruDeviceShort . ' from current driver');
			sleep(4);
			exit(1);
		} else {
			write_display('Ok');
			write_log('Unbound device ' . $strPassthruDeviceShort . ' from current driver');
 		}
	}

	// Get Vendor and Device IDs for the passthru device
	$strVendor = file_get_contents('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/vendor');
	$strDevice = file_get_contents('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/device');

	// Attempt to bind driver to vfio-pci
 	write_display('<br>Binding device ' . $strPassthruDeviceShort . ' to vfio-pci driver...');
	if (file_put_contents('/sys/bus/pci/drivers/vfio-pci/new_id', $strVendor . ' ' . $strDevice) === false) {
		write_display('FAILED', true);
		write_log('ERROR: Failed to bind device ' . $strPassthruDeviceShort . ' to vfio-pci driver');
		sleep(4);
		exit(1);
	} else {
		write_display('Ok');
		write_log('Bound device ' . $strPassthruDeviceShort . ' to vfio-pci driver');
	}

}


// Open the seed xml
write_display('<br>Parsing seed xml file...');
write_log('Parsing seed xml file: ' . __DIR__ . '/OpenELEC.xml');
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
write_display('<br>Saving generated xml file...');
if (file_put_contents('/tmp/OpenELEC.xml', $strXMLFile) === false) {
	write_display('FAILED', true);
	write_log('ERROR: Failed generated xml file: /tmp/OpenELEC.xml');
	sleep(4);
	exit(1);
} else {
	write_display('Ok');
	write_log('Generated xml file: /tmp/OpenELEC.xml');
}

// Ensure NODATACOW is set to all KVM images
// passthru('chattr +C /mnt/cache/vms/kvm/');

// Start the VM
write_display('<br>Starting VM...');
write_log('Starting VM with "virsh create /tmp/OpenELEC.xml"');
write_display('<br>' . shell_exec('virsh create /tmp/OpenELEC.xml 2>&1'));
sleep(4);
