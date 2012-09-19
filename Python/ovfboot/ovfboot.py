#!/usr/bin/env python
#
# File Name:     ovfboot
# Author:        Ryan Uber <ryan@blankbmx.com>
#
# Description:   This script reads in data from an OVF environment document and applies
#                it to the running system at boot time.
#
# chkconfig:     2345 08 88

from os import path, mkdir, getenv, environ, walk
from re import match
from sys import exit
from subprocess import Popen, PIPE
from shutil import copyfile, rmtree
from xml.dom.minidom import parseString

class OvfPropertyConsumer:

    def __init__(self):

        self.halt_path              = '/sbin/halt'
        self.mount_path             = '/bin/mount'
        self.umount_path            = '/bin/umount'
        self.chpasswd_path          = '/usr/sbin/chpasswd'
        self.cdrom_path             = '/dev/cdrom'
        self.temp_mount_path        = '/tmp/ovfboot-mount'
        self.ovfxml_cdrom_path      = path.join(self.temp_mount_path, 'ovf-env.xml')
        self.ovfxml_path            = '/etc/ovf-env.xml'
        self.interface_config_path  = '/etc/sysconfig/network-scripts/ifcfg-eth0'
        self.network_config_path    = '/etc/sysconfig/network'
        self.dns_config_path        = '/etc/resolv.conf'

        self.properties = {
            'root_password':'', 'host_fqdn':'', 'ip_0':'',  'netmask_0':'',
            'gateway_0':'',     'dns_0':'',     'dns_1':'', 'dns_2':''
        }
            

    # If an error is encountered, display it and halt the system since we cannot
    # proceed with the rest of the configuration of the system.
    def raise_error(self, message):
        print('%s\n\nHalting system...\n' % message)
        Popen([self.halt_path, '-f'])
        exit(1)

    # Validate a fully qualified domain name (FQDN)
    def validate_fqdn(self, fqdn):
        return match('^[a-z0-9-]+(\.[a-z0-9-]+)+?\.[a-z]{2,6}$', fqdn)

    # Validate a version 4 IP address
    def validate_ipv4(self, a, i=0):
        for x in a.split('.'):
            i+=(x.isdigit() and int(x)>=0 and int(x)<256)
        return i==4

    # Validate OVF environment - If the user did not provide all required values,
    # or if any of the values do not validate, we need to halt with an error.
    def validate_env(self, v=[], i=[]):
        for p in ['ip_0', 'netmask_0', 'gateway_0', 'dns_0', 'dns_1', 'dns_2']:
            if self.validate_ipv4(self.properties[p]):
                v.append({'key':p,'value':self.properties[p]})
            elif not (p[0:4] == 'dns_' and self.properties[p] == ''):
                i.append({'key':p,'value':self.properties[p]})

        for p in ['host_fqdn']:
            if self.validate_fqdn(self.properties['host_fqdn']):
                v.append({'key':p,'value':self.properties[p]})
            else:
                i.append({'key':p,'value':self.properties[p]})

        if len(i) != 0:
            print '\n' * 24
            print 'Failed while validating configuration\n\n'
            print 'The following parameters have improper values:'
            for p in i:
                print '%s="%s"' % (p['key'], p['value'])
            print '\n'
            print 'The following parameters appear to be configured correctly:'
            for p in v:
                print '%s="%s"' % (p['key'], p['value'])
            self.raise_error('Environment validation failed')

    # Copy the OVF environment document off of the CDROM device to the local disk.
    # This gurantees that we will be able to read it later on if needed.
    def copy_ovf(self):

        print 'Attempting to fetch OVF environment...'

        if not path.exists(self.temp_mount_path):
            mkdir(self.temp_mount_path)

        sp = Popen([self.mount_path, self.cdrom_path, self.temp_mount_path], stderr=PIPE)
        sp.wait()

        if sp.returncode != 0:
            self.raise_error('Failed to mount CDROM device %s' % self.cdrom_path)

        try: copyfile(self.ovfxml_cdrom_path, self.ovfxml_path)
        except OSError:
            self.raise_error('Failed to copy ovf environment document to local disk %s' % self.ovfxml_cdrom_path);

        sp = Popen([self.umount_path, self.cdrom_path], stderr=PIPE)
        sp.wait()

        if sp.returncode != 0:
            self.raise_error('Failed to unmount CDROM device %s' % self.cdrom_path)

        try: rmtree(self.temp_mount_path)
        except OSError:
            self.raise_error('Failed to clean up temporary mount point %s' % self.temp_mount_path)

    # Read the OVF environment document from the local disk and register facter
    # variables to make them available via Puppet.
    def consume_ovf(self):

        print 'Registering configuration...'

        fh = open(self.ovfxml_path, 'r')
        xml = fh.read()
        fh.close
        data = parseString(xml)
        properties = data.getElementsByTagName('Property')
        for property in properties:
            key, value = [ property.attributes['oe:key'].value, property.attributes['oe:value'].value ]
            if key in self.properties:
                self.properties[key] = value

    # Set the password for the root UNIX account on the system.
    def set_root_password(self):

        print 'Setting root password...'

        sp = Popen([self.chpasswd_path], stderr=PIPE, stdin=PIPE)
        sp.communicate(input='root:'+self.properties['root_password'])
        sp.wait()

        if sp.returncode != 0:
            self.raise_error('Failed to set root password')

    # Write out network interface configuration file
    def config_interface(self):

        print 'Configuring network interface...'

        fh = open(self.interface_config_path, 'w')
        fh.write(
            'DEVICE=eth0\nONBOOT=yes\nBOOTPROTO=static\nIPADDR=%s\nNETMASK=%s\nGATEWAY=%s\n'
            %(self.properties['ip_0'], self.properties['netmask_0'], self.properties['gateway_0'])
        )
        fh.close()

    # Write out network configuration file
    def config_network(self):

        print 'Configuring network...'

        fh = open(self.network_config_path, 'w')
        fh.write('NETWORKING=yes\nNETWORKING_IPV6=no\nHOSTNAME=%s\n' % self.properties['host_fqdn'])
        fh.close()

    # Write out dns configuration file
    def config_dns(self):

        print 'Configuring name servers...'

        fh = open(self.dns_config_path, 'w')
        count = 0
        while count < 3:
            if self.properties['dns_'+str(count)] != '':
                fh.write('nameserver %s\n' % self.properties['dns_'+str(count)])
            count += 1
        fh.close()

# Main program flow
ovfenv = OvfPropertyConsumer()
ovfenv.copy_ovf()
ovfenv.consume_ovf()
ovfenv.validate_env()
ovfenv.set_root_password()
ovfenv.config_network()
ovfenv.config_interface()
ovfenv.config_dns()
