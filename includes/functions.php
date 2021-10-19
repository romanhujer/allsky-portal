<?php

/**
 *
 * This code is executed outside of a function and is needed by index.php and public.php.
 * It's centralized in this file to make it easier to maintain.
 *
*/

//FUTURE define('ALLSKY_CONFIG_DIR', '/config');		// name of directory only.

// These values are updated during installation.
define('ALLSKY_HOME',    'XX_ALLSKY_HOME_XX');
define('ALLSKY_SCRIPTS', 'XX_ALLSKY_SCRIPTS_XX');
define('ALLSKY_IMAGES',  'XX_ALLSKY_IMAGES_XX');
//FUTURE define('ALLSKY_CONFIG',  'XX_ALLSKY_CONFIG_XX');
define('RASPI_CONFIG',   'XX_RASPI_CONFIG_XX');

if (ALLSKY_HOME == "XX_ALLSKY_HOME_XX") {
	// This file hasn't been updated yet after installation.
	echo "<div style='font-size: 200%;'>";
	echo "<span style='color: red'>";
	echo "Please run the following from the 'allsky' directory before using the WebUI:";
	echo "</span>";
	echo "<br> &nbsp; &nbsp; &nbsp; sudo gui/install --update";
	echo "</div>";
	exit;
}

// xxx COMPATIBILITY CHECK:
// Version 0.8 and older of allsky had config.sh and autocam.sh in $ALLSKY_HOME.
// Newer versions have config.sh in $ALLSKY_CONFIG and don't need autocam.sh since "auto" is no longer a valid CAMERA type.
// Can't change a constant after it's defined so use temp names first.
$allsky_config_dir = '/config';
$allsky_config = ALLSKY_HOME . $allsky_config_dir;	// value updated during installation
if (file_exists($allsky_config . '/config.sh')) {
	define('ALLSKY_CONFIG_DIR', '/config');			// name of just the directory
	define('ALLSKY_CONFIG', $allsky_config);
} else {
	define('ALLSKY_CONFIG_DIR', '');
	define('ALLSKY_CONFIG', ALLSKY_HOME);
}

$cam = get_variable(ALLSKY_CONFIG .'/config.sh', 'CAMERA=', '');
if ($cam == '') {
	echo "<div style='color: red; font-size: 200%;'>";
	echo "CAMERA type not defined.";
	echo "<br>Please update '" . ALLSKY_CONFIG . "/config.sh'";
	echo "</div>";
	exit;
} else if ($cam == 'auto') {
	echo "<div style='color: red; font-size: 200%;'>";
	echo "A CAMERA setting of 'auto' is no longer supported.<br>You must set it to the type of camera you have.";
	echo "<br>Please update '" . ALLSKY_CONFIG . "/config.sh'";
	echo "</div>";
	exit;
}

define('RASPI_CAMERA_SETTINGS', RASPI_CONFIG . '/settings_'.$cam.'.json');
if (! file_exists(RASPI_CAMERA_SETTINGS)) {
	echo "<div style='color: red; font-size: 200%;'>";
	echo "ERROR: Unable to find camera settings file for camera of type '$cam'.";
	echo "<br>Please check the " . RASPI_CONFIG . " directory for 'settings_$cam.json'.";
	echo "</div>";
	exit;
}

$camera_settings_str = file_get_contents(RASPI_CAMERA_SETTINGS, true);
$camera_settings_array = json_decode($camera_settings_str, true);
// $img_dir is an alias in the web server's config.
// It's the same as ALLSKY_HOME which is the physical path name on the server.
$img_dir = get_variable(ALLSKY_CONFIG . '/config.sh', 'IMG_DIR=', 'current');
// xxx new way:  $image_name = $img_dir . "/" . $camera_settings_array['filename'];
// old way uses IMG_PREFIX:
$img_prefix = get_variable(ALLSKY_CONFIG .'/config.sh', 'IMG_PREFIX=', '');
$image_name = $img_dir . "/" . $img_prefix . $camera_settings_array['filename'];
$darkframe = $camera_settings_array['darkframe'];


////////////////// Determine delay between refreshes of the image.
// Determine if it's day or night so we know which delay to use.
// The time between daylight exposures is (daydelay + dayexposure).
$daydelay = $camera_settings_array["daydelay"] + $camera_settings_array["dayexposure"];

// The time between night exposures is (nightdelay + nightmaxexposure).
// Both can be large numbers so use both.
if (isset($camera_settings_array['nightmaxexposure']))	// not defined for RPiHQ cameras
	$x = $camera_settings_array['nightmaxexposure'];
else
	$x = $camera_settings_array['nightexposure'];
$nightdelay = $camera_settings_array["nightdelay"] + $x;

$angle = $camera_settings_array['angle'];
$lat = $camera_settings_array['latitude'];
$lon = $camera_settings_array['longitude'];
exec("sunwait poll exit set angle $angle $lat $lon", $return);
if ($return[0] == 'DAY') {
	$delay = $daydelay;
} else {
	$delay = $nightdelay;
}
// Divide by 2 to lessen the delay between a new picture and when we check.
$delay /= 2;

// Convert to seconds for display.
$daydelay /= 1000;
$nightdelay /= 1000;


/**
*
* Is this a valid directory name?
* This helps keep us from displaying and deleting invalid directories.
* Example directory: 20210710.  They should start with "2" for the 2000's.
*
*/
function is_valid_directory($directory_name) {
	return preg_match('/^(2\d{7}|test\w*)$/', basename($directory_name));
}

/**
*
* Add CSRF Token to form
*
*/
function CSRFToken() {
?>
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
<?php
}

/**
*
* Validate CSRF Token
*
*/
function CSRFValidate() {
  if ( hash_equals($_POST['csrf_token'], $_SESSION['csrf_token']) ) {
    return true;
  } else {
    error_log('CSRF violation');
    return false;
  }
}

/**
* Test whether array is associative
*/
function isAssoc($arr) {
  return array_keys($arr) !== range(0, count($arr) - 1);
}

/**
*
* Display a selector field for a form. Arguments are:
*   $name:     Field name
*   $options:  Array of options
*   $selected: Selected option (optional)
*       If $options is an associative array this should be the key
*
*/
function SelectorOptions($name, $options, $selected = null) {
  echo "<select class=\"form-control\" name=\"$name\">";
  foreach ( $options as $opt => $label) {
    $select = '';
    $key = isAssoc($options) ? $opt : $label;
    if( $key == $selected ) {
      $select = " selected";
    }
    echo "<option value=\"$key\"$select>$label</options>";
  }
  echo "</select>";
}

/**
*
* @param string $input
* @param string $string
* @param int $offset
* @param string $separator
* @return $string
*/
function GetDistString( $input,$string,$offset,$separator ) {
	$string = substr( $input,strpos( $input,$string )+$offset,strpos( substr( $input,strpos( $input,$string )+$offset ), $separator ) );
	return $string;
}

/**
*
* @param array $arrConfig
* @return $config
*/
function ParseConfig( $arrConfig ) {
	$config = array();
	foreach( $arrConfig as $line ) {
		$line = trim($line);
		if( $line != "" && $line[0] != "#" ) {
			$arrLine = explode( "=",$line );
			$config[$arrLine[0]] = ( count($arrLine) > 1 ? $arrLine[1] : true );
		}
	}
	return $config;
}

/**
*
* @param string $freq
* @return $channel
*/
function ConvertToChannel( $freq ) {
  $channel = ($freq - 2407)/5;	// check for 2.4 GHz
  if ($channel > 0 && $channel <= 14) {
    return $channel . " / 2.4GHz";
  } else {	// check for 5 GHz
    $channel = ($freq - 5030)/5;
    if ($channel >= 7 && $channel <= 165) {
      // There are also some channels in the 4915 - 4980 range...
      return $channel . " / 5GHz";
    } else {
      return 'Invalid&nbsp;Channel, Hz=' . $freq;
    }
  }
}

/**
* Converts WPA security string to readable format
* @param string $security
* @return string
*/
function ConvertToSecurity( $security ) {
  $options = array();
  preg_match_all('/\[([^\]]+)\]/s', $security, $matches);
  foreach($matches[1] as $match) {
    if (preg_match('/^(WPA\d?)/', $match, $protocol_match)) {
      $protocol = $protocol_match[1];
      $matchArr = explode('-', $match);
      if (count($matchArr) > 2) {
        $options[] = $protocol . ' ('. $matchArr[2] .')';
      } else {
        $options[] = $protocol;
      }
    }
  }

  if (count($options) === 0) {
    // This could also be WEP but wpa_supplicant doesn't have a way to determine
    // this.
    // And you shouldn't be using WEP these days anyway.
    return 'Open';
  } else {
    return implode('<br />', $options);
  }
}

/**
*
*
*/
function DisplayOpenVPNConfig() {

	exec( 'cat '. RASPI_OPENVPN_CLIENT_CONFIG, $returnClient );
	exec( 'cat '. RASPI_OPENVPN_SERVER_CONFIG, $returnServer );
	exec( 'pidof openvpn | wc -l', $openvpnstatus);

	if( $openvpnstatus[0] == 0 ) {
		$status = '<div class="alert alert-warning alert-dismissable">OpenVPN is not running
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button></div>';
	} else {
		$status = '<div class="alert alert-success alert-dismissable">OpenVPN is running
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button></div>';
	}

	// parse client settings
	foreach( $returnClient as $a ) {
		if( $a[0] != "#" ) {
			$arrLine = explode( " ",$a) ;
			$arrClientConfig[$arrLine[0]]=$arrLine[1];
		}
	}

	// parse server settings
	foreach( $returnServer as $a ) {
		if( $a[0] != "#" ) {
			$arrLine = explode( " ",$a) ;
			$arrServerConfig[$arrLine[0]]=$arrLine[1];
		}
	}
	?>
	<div class="row">
	<div class="col-lg-12">
    	<div class="panel panel-primary">           
			<div class="panel-heading"><i class="fa fa-lock fa-fw"></i> Configure OpenVPN 
            </div>
        <!-- /.panel-heading -->
        <div class="panel-body">
        	<!-- Nav tabs -->
            <ul class="nav nav-tabs">
                <li class="active"><a href="#openvpnclient" data-toggle="tab">Client settings</a>
                </li>
                <li><a href="#openvpnserver" data-toggle="tab">Server settings</a>
                </li>
            </ul>
            <!-- Tab panes -->
           	<div class="tab-content">
           		<p><?php echo $status; ?></p>
            	<div class="tab-pane fade in active" id="openvpnclient">
            		
            		<h4>Client settings</h4>
					<form role="form" action="?page=save_hostapd_conf" method="POST">

					<div class="row">
						<div class="form-group col-md-4">
	                        <label>Select OpenVPN configuration file (.ovpn)</label>
	                        <input type="file" name="openvpn-config">
	                    </div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">Client Log</label>
							<input type="text" class="form-control" id="disabledInput" name="log-append" type="text" placeholder="<?php echo $arrClientConfig['log-append']; ?>" disabled />
						</div>
					</div>
				</div>
				<div class="tab-pane fade" id="openvpnserver">
            		<h4>Server settings</h4>
            		<div class="row">
						<div class="form-group col-md-4">
            			<label for="code">Port</label> 
            			<input type="text" class="form-control" name="openvpn_port" value="<?php echo $arrServerConfig['port'] ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Protocol</label>
						<input type="text" class="form-control" name="openvpn_proto" value="<?php echo $arrServerConfig['proto'] ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Root CA certificate</label>
						<input type="text" class="form-control" name="openvpn_rootca" placeholder="<?php echo $arrServerConfig['ca']; ?>" disabled />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Server certificate</label>
						<input type="text" class="form-control" name="openvpn_cert" placeholder="<?php echo $arrServerConfig['cert']; ?>" disabled />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Diffie Hellman parameters</label>
						<input type="text" class="form-control" name="openvpn_dh" placeholder="<?php echo $arrServerConfig['dh']; ?>" disabled />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">KeepAlive</label>
						<input type="text" class="form-control" name="openvpn_keepalive" value="<?php echo $arrServerConfig['keepalive']; ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Server log</label>
						<input type="text" class="form-control" name="openvpn_status" placeholder="<?php echo $arrServerConfig['status']; ?>" disabled />
						</div>
					</div>
            	</div>
				<input type="submit" class="btn btn-outline btn-primary" name="SaveOpenVPNSettings" value="Save settings" />
				<?php
				if($hostapdstatus[0] == 0) {
					echo '<input type="submit" class="btn btn-success" name="StartOpenVPN" value="Start OpenVPN" />';
				} else {
					echo '<input type="submit" class="btn btn-warning" name="StopOpenVPN" value="Stop OpenVPN" />';
				}
				?>
				</form>
		</div><!-- /.panel-body -->
    </div><!-- /.panel-primary -->
    <div class="panel-footer"> Information provided by openvpn</div>
</div><!-- /.col-lg-12 -->
</div><!-- /.row -->
<?php
}

/**
*
*
*/
function DisplayTorProxyConfig(){

	exec( 'cat '. RASPI_TORPROXY_CONFIG, $return );
	exec( 'pidof tor | wc -l', $torproxystatus);

	if( $torproxystatus[0] == 0 ) {
		$status = '<div class="alert alert-warning alert-dismissable">TOR is not running
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button></div>';
	} else {
		$status = '<div class="alert alert-success alert-dismissable">TOR is running
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button></div>';
	}

	foreach( $return as $a ) {
		if( $a[0] != "#" ) {
			$arrLine = explode( " ",$a) ;
			$arrConfig[$arrLine[0]]=$arrLine[1];
		}
	}

	?>
	<div class="row">
	<div class="col-lg-12">
    	<div class="panel panel-primary">           
			<div class="panel-heading"><i class="fa fa-eye-slash fa-fw"></i> Configure TOR proxy
            </div>
        <!-- /.panel-heading -->
        <div class="panel-body">
        	<!-- Nav tabs -->
            <ul class="nav nav-tabs">
                <li class="active"><a href="#basic" data-toggle="tab">Basic</a>
                </li>
                <li><a href="#relay" data-toggle="tab">Relay</a>
                </li>
            </ul>

            <!-- Tab panes -->
           	<div class="tab-content">
           		<p><?php echo $status; ?></p>

            	<div class="tab-pane fade in active" id="basic">
            		<h4>Basic settings</h4>
					<form role="form" action="?page=save_hostapd_conf" method="POST">
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">VirtualAddrNetwork</label>
							<input type="text" class="form-control" name="virtualaddrnetwork" value="<?php echo $arrConfig['VirtualAddrNetwork']; ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">AutomapHostsSuffixes</label>
							<input type="text" class="form-control" name="automaphostssuffixes" value="<?php echo $arrConfig['AutomapHostsSuffixes']; ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">AutomapHostsOnResolve</label>
							<input type="text" class="form-control" name="automaphostsonresolve" value="<?php echo $arrConfig['AutomapHostsOnResolve']; ?>" />
						</div>
					</div>	
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">TransListenAddress</label>
							<input type="text" class="form-control" name="translistenaddress" value="<?php echo $arrConfig['TransListenAddress']; ?>" />
						</div>
					</div>	
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">DNSPort</label>
							<input type="text" class="form-control" name="dnsport" value="<?php echo $arrConfig['DNSPort']; ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">DNSListenAddress</label>
							<input type="text" class="form-control" name="dnslistenaddress" value="<?php echo $arrConfig['DNSListenAddress']; ?>" />
						</div>
					</div>
				</div>
				<div class="tab-pane fade" id="relay">
            		<h4>Relay settings</h4>
            		<div class="row">
						<div class="form-group col-md-4">
							<label for="code">ORPort</label>
							<input type="text" class="form-control" name="orport" value="<?php echo $arrConfig['ORPort']; ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">ORListenAddress</label>
							<input type="text" class="form-control" name="orlistenaddress" value="<?php echo $arrConfig['ORListenAddress']; ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">Nickname</label>
							<input type="text" class="form-control" name="nickname" value="<?php echo $arrConfig['Nickname']; ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">Address</label>
							<input type="text" class="form-control" name="address" value="<?php echo $arrConfig['Address']; ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">RelayBandwidthRate</label>
							<input type="text" class="form-control" name="relaybandwidthrate" value="<?php echo $arrConfig['RelayBandwidthRate']; ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">RelayBandwidthBurst</label>
							<input type="text" class="form-control" name="relaybandwidthburst" value="<?php echo $arrConfig['RelayBandwidthBurst']; ?>" />
						</div>
					</div>
            	</div>
		
				<input type="submit" class="btn btn-outline btn-primary" name="SaveTORProxySettings" value="Save settings" />
				<?php 
				if( $torproxystatus[0] == 0 ) {
					echo '<input type="submit" class="btn btn-success" name="StartTOR" value="Start TOR" />';
				} else {
					echo '<input type="submit" class="btn btn-warning" name="StopTOR" value="Stop TOR" />';
				};
				?>
				</form>
			</div><!-- /.tab-content -->
		</div><!-- /.panel-body -->
		<div class="panel-footer"> Information provided by tor</div>
    </div><!-- /.panel-primary -->
</div><!-- /.col-lg-12 -->
</div><!-- /.row -->
<?php 
}

/**
*
*
*/
function SaveTORAndVPNConfig(){
  if( isset($_POST['SaveOpenVPNSettings']) ) {
    // TODO
  } elseif( isset($_POST['SaveTORProxySettings']) ) {
    // TODO
  } elseif( isset($_POST['StartOpenVPN']) ) {
    echo "Attempting to start openvpn";
    exec( 'sudo /etc/init.d/openvpn start', $return );
    foreach( $return as $line ) {
      echo $line."<br />";
    }
  } elseif( isset($_POST['StopOpenVPN']) ) {
    echo "Attempting to stop openvpn";
    exec( 'sudo /etc/init.d/openvpn stop', $return );
    foreach( $return as $line ) {
      echo $line."<br />";
    }
  } elseif( isset($_POST['StartTOR']) ) {
    echo "Attempting to start TOR";
    exec( 'sudo /etc/init.d/tor start', $return );
    foreach( $return as $line ) {
      echo $line."<br />";
    }
  } elseif( isset($_POST['StopTOR']) ) {
    echo "Attempting to stop TOR";
    exec( 'sudo /etc/init.d/tor stop', $return );
    foreach( $return as $line ) {
      echo $line."<br />";
    }
  }
}

/**
*
* Get a variable from a file and return its value; if not there, return the default.
* NOTE: The variable's value is anything after the equal sign, so there shouldn't be a comment on the line.
* NOTE: There may be something before $searchfor, e.g., "export X=1", where "X" is $searchfor.
*/
function get_variable($file, $searchfor, $default)
{
	// get the file contents
	$contents = file_get_contents($file);
	if ("$contents" == "") return($default);	// file not found or not readable

	// escape special characters in the query
	$pattern = preg_quote($searchfor, '/');
	// finalise the regular expression, matching the whole line
	$pattern = "/^.*$pattern.*\$/m";

	// search, and store all matching occurences in $matches, but only return the last one
	$num_matches = preg_match_all($pattern, $contents, $matches);
	if ($num_matches) {
		$double_quote = '"';

		// Format: [stuff]$searchfor=$value   or   [stuff]$searchfor="$value"
		// Need to delete  [stuff]$searchfor=  and optional double quotes
		$last = $matches[0][$num_matches - 1];	// get the last one
		$last = explode( '=', $last)[1];	// get everything after equal sign
		$last = str_replace($double_quote, "", $last);
		return($last);
	} else {
   		return($default);
	}
}

/**
* 
* List a type of file - either "All" (case sensitive) for all days, or only for the specified day.
*/
function ListFileType($dir, $imageFileName, $formalImageTypeName, $type) {	// if $dir is not null, it ends in "/"
	$num = 0;	// Let the user know when there are no images for the specified day
	// "/images" is an alias in the web server for ALLSKY_IMAGES
	$images_dir = "/images";
	$chosen_day = $_GET['day'];
	echo "<h2>$formalImageTypeName - $chosen_day</h2>\n";
	echo "<div class='row'>\n";
	if ($chosen_day === 'All'){
		if ($handle = opendir(ALLSKY_IMAGES)) {
		    while (false !== ($day = readdir($handle))) {
				if (is_valid_directory($day)) {
					$days[] = $day;
					$num += 1;
				}
		    }
		    closedir($handle);
		}

		if ($num == 0) {
			// This could indicate an error, or the user just installed allsky
			echo "<span class='alert-warning'>There are no image directories.</span>";
		} else {
			rsort($days);
			$num = 0;
			foreach ($days as $day) {
				$imageTypes = array();
				foreach (glob(ALLSKY_IMAGES . "/$day/$dir$imageFileName-$day.*") as $imageType) {
					foreach (glob(ALLSKY_IMAGES . "/$day/$dir$imageFileName-$day.*") as $imageType) {
						$imageTypes[] = $imageType;
						$num += 1;
					}
					foreach ($imageTypes as $imageType) {
						$imageType_name = basename($imageType);
						$fullFilename = "$images_dir/$day/$dir$imageType_name";
						if ($type == "picture") {
							echo "<a href='$fullFilename'>";
							echo "<div style='float: left; width: 100%; margin-bottom: 2px;'>";
								echo "<label>$day</label>";
							echo "<img src='$fullFilename' style='margin-left: 10px; max-width: 50%; max-height:100px'/>";
							echo "</div></a>\n";
							} else {	// video
							// echo "<video width='640' height='480' controls>
							// xxxx Would be nice to show a thumbnail since loading all the videos
							// is bandwidth intensive.  How do you make a thumbnail from a video?
							echo "<a href='$fullFilename'>";
							echo "<div style='float: left; width: 100%; margin-bottom: 2px;'>";
							echo "<label style='vertical-align: middle'>$day &nbsp; &nbsp;</label>";
							echo "<video width='85%' height='85%' controls style='vertical-align: middle'>";
							echo "<source src='$fullFilename' type='video/mp4'>";
								echo "<source src='movie.ogg' type='video/ogg'>";
							echo "Your browser does not support the video tag.";
							echo "/video>";
							echo "/div></a>\n";
						}
			  		}
				}
				if ($num == 0) {
					echo "<span class='alert-warning'>There are no $formalImageTypeName.</span>";
				}
			}
		}
	} else {
		foreach (glob(ALLSKY_IMAGES . "/$chosen_day/$dir$imageFileName-$chosen_day.*") as $imageType) {
			$imageTypes[] = $imageType;
			$num += 1;
		}
		if ($num == 0) {
			echo "<span class='alert-warning'>There are no $formalImageTypeName for this day.</span>";
		} else {
			foreach ($imageTypes as $imageType) {
				$imageType_name = basename($imageType);
				$fullFilename = "$images_dir/$chosen_day/$dir$imageType_name";
				if ($type == "picture") {
				    echo "<a href='$fullFilename'>
					<div style='float: left'>
					<img src='$fullFilename' style='max-width: 100%;max-height:400px'/>
					</div></a>\n";
				} else {	//video
				    echo "<a href='$fullFilename'>";
				    echo "<div style='float: left; width: 100%'>
					<video width='85%' height='85%' controls>
						<source src='$fullFilename' type='video/mp4'>
						<source src='movie.ogg' type='video/ogg'>
						Your browser does not support the video tag.
					</video>
					</div></a>\n";
				}
			}
		}
	}
        echo "</div>";
}

?>
