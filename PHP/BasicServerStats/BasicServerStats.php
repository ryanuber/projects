<?php

/**
 * File Name: get_server_stats.php
 * Author:    Ryan R. Uber <ryan@blankbmx.com>
 *
 * Provides an easy http-accessible script to display commonly needed
 * server statics information.
 * 
 * Note: Enable exec() to get Apache connection stats.
 * Run:  echo -n "PASSWORD HERE" | md5sum
 *       to generate a password
 */
 
# Authentication Definitions
$useAuth = false;
$authMD5 = 'Paste generated MD5SUM here';
 
# Perform login
if ( $useAuth === true )
{
    session_start();
 
    if ( $_SERVER['REQUEST_METHOD'] == "POST" )
    {
        if ( md5 ( $_POST['password'] ) == $authMD5 )
        {
            $_SESSION['loggedIn'] = 'true';
        }
    }
 
    # Prompt for password
    if ( ! isset ( $_SESSION['loggedIn'] ) )
    {
?>
<form action=<?=$_SERVER['PHP_SELF']?> method=POST>
<font size=5>Authentication required</font><br>
<font size=2><b><i>Password: </b></i><input type=password name=password><br>
<input type=submit value="Authenticate">
</form>
<?php
        die();
    }
}
 
# Initialize Variables
$hostname           = $_SERVER['HTTP_HOST'];
$unique             = array();
$www_unique_count   = 0;
$www_total_count    = 0;
$proc_count         = 0;
$display_www        = false;
 
# Check if 'exec()' is enabled
if ( function_exists ( 'exec' ) )
{
    $display_www = true;
 
    # Get HTTP connections
    @exec ( 'netstat -an | egrep \':80|:443\' | awk \'{print $5}\' | grep -v \':::\*\' |  grep -v \'0.0.0.0\'', $results );
    foreach ( $results as $result )
    {
        $array = explode ( ':', $result );
        $www_total_count ++;
 
        if ( preg_match ( '/^::/', $result ) )
        {
            $ipaddr = $array[3];
        }
 
        else
        {
            $ipaddr = $array[0];
        }
 
        if ( ! in_array ( $ipaddr, $unique ) )
        {
            $unique[] = $ipaddr;
            $www_unique_count ++;
        }
    }
    unset ( $results );
}
 
# Get Server Load
$loadavg = explode ( ' ', file_get_contents ( '/proc/loadavg' ) );
$loadavg = "{$loadavg[0]} {$loadavg[1]} {$loadavg[2]}";
 
# Get Disk Utilization
$disktotal = disk_total_space ( '/' );
$diskfree  = disk_free_space  ( '/' );
$diskuse   = round ( 100 - ( ( $diskfree / $disktotal ) * 100 ) ) . "%";
 
# Get server uptime
$uptime = floor ( preg_replace ( '/\.[0-9]+/', '', file_get_contents ( '/proc/uptime' ) ) / 86400 );
 
# Get kernel version
$kernel = explode ( ' ', file_get_contents ( '/proc/version' ) );
$kernel = $kernel[2];
 
# Get number of processes
$dh = opendir ( '/proc' );
while ( $dir = readdir ( $dh ) )
{
    if ( is_dir ( '/proc/' . $dir ) )
    {
        if ( preg_match ( '/^[0-9]+$/', $dir ) )
        {
            $proc_count ++;
        }
    }
}
 
# Get memory usage
foreach ( file ( '/proc/meminfo' ) as $result )
{
    $array = explode ( ':', str_replace ( ' ', '', $result ) );
    $value = preg_replace ( '/kb/i', '', $array[1] );
    if ( preg_match ( '/^MemTotal/', $result ) )
    {
        $totalmem = $value;
    }
 
    elseif ( preg_match ( '/^MemFree/', $result ) )
    {
        $freemem = $value;
    }
 
    elseif ( preg_match ( '/^Buffers/', $result ) )
    {
        $buffers = $value;
    }
 
    elseif ( preg_match ( '/^Cached/', $result ) )
    {
        $cached = $value;
    }
 
}
$freemem = ( $freemem + $buffers + $cached );
$usedmem = round ( 100 - ( ( $freemem / $totalmem ) * 100 )  ) . "%";
?>
 
<html>
<body>
<font size=5><b><?=$hostname?></b></font><br><br>
<?php
if ( $display_www === true )
{
?>
<font size=4><b>Web Server (80 and 443)</b></font><br>
<font size=3><b><i><?=$www_unique_count?></b></i></font><font size=2> unique connections</font><br>
<font size=3><b><i><?=$www_total_count?></b></i></font><font size=2> total connections</font><br>
<?php
}
?>
<font size=3><i><b>Kernel Version:</b> <?=$kernel?></i></font><br>
<font size=3><i><b>Uptime:</b> <?=$uptime?> days</i></font><br>
<font size=3><i><b>Load Average:</b> <?=$loadavg?></i></font><br>
<font size=3><i><b>Disk Use:</b> <?=$diskuse?></i></font><br>
<font size=3><i><b>Memory Utilization: </b><?=$usedmem?></i></font><br>
<font size=3><i><b>Total Processes: </b><?=$proc_count?></i></font><br>
</body>
</html>
