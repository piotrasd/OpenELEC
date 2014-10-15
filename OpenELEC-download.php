#!/usr/bin/php
<?php
	// DOWNLOAD SCRIPT
		passthru("mkdir -p /mnt/user/vms/kvm/OpenELEC/ && curl -s http://dnld.lime-technology.com/vms/OpenELEC/OpenELEC-4.2.0-1_LT.tar.xz | pv -i10 -bnW | tar --wildcards --sparse -kxJ -C /mnt/user/vms/kvm/OpenELEC/ *.img");

	// CREATE APPDATA DIRECTORY
		passthru("mkdir -p /mnt/user/appdata/OpenELEC/");
?>