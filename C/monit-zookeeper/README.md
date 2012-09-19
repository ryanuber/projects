monit-zookeeper - Write Monit data to Zookeeper in real-time
============================================================

* Original Post: http://www.ryanuber.com/monit-zookeeper-write-monit-data-to-zookeeper-in-realtime.html

This patch provides extra configuration options for Monit to connect to
a Zookeeper instance. Resources will update their status on their own
znodes, which can be watched or queried from other programs.

Awesome
-------
Once you have configured a Zookeeper host in your monitrc, you can then
start up Monit and watch it begin to write to the prefix you specified
with information about what is happening inside of Monit. For example,
you set the Zookeeper prefix to "/monit/hosts" in your monitrc, and you
are monitoring 3 services, "Apache", "SSH", and "Avahi". When you start
Monit, you will see znodes be created for each once their status becomes
available (typically after some startdelay):

    '
    '-- monit
        '-- hosts
            |-- host1.example.com
            |   |-- Apache
            |   |-- Avahi
            |   '-- SSH
            |-- host2.example.com
            |   |-- Apache
            |   |-- Avahi
            |   '-- SSH
            '-- host3.example.com
                |-- Apache
                |-- Avahi
                '-- SSH

Each of the znodes will contain a text string that Monit generates as a
status message. This typically looks something like:

    'httpd' process running with pid 3674

You can create watches on the nodes, or write scripts to query for service
status remotely in close-to-realtime without having to query the node directly,
you could have a web UI displaying the data collected by monit-zookeeper,
and probably a slew of other things as well.

Lame
----
This patch is likely not implemented as well as it could be, so performance,
while it is very good on a single node monitoring a few services, may be
impacted while monitoring many services.

Currently there is no logic around Zookeeper permissions - znodes are created
and updated with the infamous ZOO_ACL_UNSAFE. This won't really be an issue
if you have your Zookeeper instance dedicated to Monit data, but in the real
world that probably won't be the case.

The Zookeeper connect string logic runs during each znode create / update.
I would like to see this moved somewhere so it gets executed only once at
Monit start time and keep the connect string handy rather than any time it
needs to write some data.
