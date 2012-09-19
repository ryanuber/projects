OVF Property Environment Boot Script
====================================

This script reads an OVF environment document at boot time and applies
a set of OVF properties to basic system configuration files, allowing
the machine to be configured externally without requring any fancy
agent or other runtime configuration scripts.

Requirements and assumptions
----------------------------
This script is currently only tested against VMWare, however, it is
written around using an emulated CDROM device instead of the VMWare
tools for OVF property transport.

You will need to set the following OVF properties on your virtual
machine, and make sure that they are exposed via the ISO method:

* root_password
* host_fqdn
* ip_0
* netmask_0
* gateway_0
* dns_0
* dns_1
* dns_2

This list of supported parameters could be very easily extended if you
needed to configure something application-specific. The above
properties are just implementation defaults and are subject to change.

When booting, the temporary CDROM device is mounted, and the OVF
document is copied into /etc/ovf-env.xml before reading. This makes
reading the XML document post-boot much easier than re-mounting, re-
reading/parsing, and again unmounting the temporary CDROM device.
Currently this script makes no use of the OVF environment cache
in /etc, but perhaps that will come at a later time.
