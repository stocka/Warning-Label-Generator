<?php

// Warning Label Generator
// Copyright Andrew Stock <totally@funoninter.net>

// -----------------------------------------------------------------------
// Constants, array initialization, etcetera.
// -----------------------------------------------------------------------

define("HEADING_FONTSIZE", 36);
define("HEADING_BASELINE", 112);

define("TOP_Y", 150);
define("TEXT_BLOCK_HEIGHT", 300);
define("LEFTMOST_X", 525);
define("TEXT_BLOCK_WIDTH", 600);
define("FONTSIZE",28);
define("FONT", "HelveticaBd.ttf");

// Set the enviroment variable for GD
putenv('GDFONTPATH=' . realpath('.'));

$logos = array( 
"High Voltage" => "./logos/high_voltage.png", 
"Radiation" => "./logos/radiation_warning.png", 
"Biohazard" => "./logos/biohazard_symbol.png", 
"Flammable" => "./logos/Hazard_F.png",
"Toxic" => "./logos/Hazard_T.png",
"Explosive" => "./logos/Hazard_E.png",
"Corrosive" => "./logos/Hazard_C.png",
"Oxidizing" => "./logos/Hazard_O.png",
"Harmful" => "./logos/Hazard_X.png",
"Dangerous for the environment" => "./logos/Hazard_N.png"
);

// Set of colors to use.
// Two values: first is background color, second is heading text color
$colors = array(
"Yellow" => array("#fce000", "#000000"),
"Green" => array("#00af8a", "#ffffff"),
"Blue" => array("#0019a8", "#ffffff"),
"Red" => array("#f42941", "#ffffff"),
"Orange" => array("#ff5c00", "#000000"),
"Purple" => array("#b727bf", "#ffffff"),
"Black" => array("#000000", "#ffffff")
);

// -----------------------------------------------------------------------
// Helper Functions
// -----------------------------------------------------------------------

// from http://www.anyexample.com/programming/php/php_convert_rgb_from_to_html_hex_color.xml
function html2rgb($color)
{
    if ($color[0] == '#')
        $color = substr($color, 1);

    if (strlen($color) == 6)
        list($r, $g, $b) = array($color[0].$color[1],
                                 $color[2].$color[3],
                                 $color[4].$color[5]);
    elseif (strlen($color) == 3)
        list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
    else
        return false;

    $r = hexdec($r); $g = hexdec($g); $b = hexdec($b);

    return array($r, $g, $b);
}

// -----------------------------------------------------------------------
// Form processing
// -----------------------------------------------------------------------

// Initialize params
$logo_sel = trim($_POST['logo']);
$color_sel = trim($_POST['color']);
if( isset($_POST['heading']) && trim($_POST['heading'])) {
	$header_text = trim($_POST['heading']);
}
else {
	$header_text = "WARNING";
}
$message = trim($_POST['message']);

// Does the logo provided actually exist, and is the message not blank?  If so, we're going to
// assume we have to return an image, as opposed to an HTML page
if( array_key_exists($logo_sel, $logos) && $message != "") 
{
	// Load in the warning template
	$im = null;
	$im = imagecreatefrompng('./logos/warning.png');

	if( $im == null) die();
				
	// set antialiasing, alpha channel information
	imageantialias($im, true);
	imagesavealpha($im, true);
	
	// First step: replace black with whatever color they specified in the dropdown
	
	// Replace Chroma color - either use what's specified in the query string as "color" or default to green
	if( array_key_exists($color_sel, $colors) )
	{
		$col_rgb_array = html2rgb($colors[$color_sel][0]);
		$header_rgb_array = html2rgb($colors[$color_sel][1]);
	}
	else
	{
		$col_rgb_array = html2rgb("#00af8a");
		$header_rgb_array = html2rgb("#ffffff");
	}
	imagefilter($im, IMG_FILTER_COLORIZE, $col_rgb_array[0], $col_rgb_array[1], $col_rgb_array[2]);
	
	// Add logo
	$logo_image = imagecreatefrompng($logos[$logo_sel]);
	if( $logo_image == null) die();
	
	// Copy logo into main image
	// The logo starts at 75, 75 in the destination image, and should be 375x375.
	imagecopy($im, $logo_image, 75, 75, 0, 0, 375, 375);
	
	// Free up logo - we don't need it anymore
	imagedestroy($logo_image);
	
	// Set up some colors - the header text color they specified, as well as black for the main text
	$header_text_color = imagecolorallocate($im, $header_rgb_array[0], $header_rgb_array[1], $header_rgb_array[2]);
	$black = imagecolorallocate($im, 0, 0, 0);
	$bounding_box = array();
	$centered_x = 0; // This is going to depend on what we calculate for the bounding box.
	// Split up the text into lines
	$textlines = array();
	$textlines = explode("\n", wordwrap($message, floor(TEXT_BLOCK_WIDTH / (0.8*FONTSIZE)), "\n", TRUE));

	// The baseline is from the top.
	$baseline_y = TOP_Y + (1.5*FONTSIZE);	
	
	// First step - render heading.
	// This mainly differs from the other text rendering in that we use a different font size and baseline value - the act of centering is pretty much identical
	$bounding_box = imagettfbbox(HEADING_FONTSIZE, 0, FONT, $header_text);
	// The initial X position (far-left) is going to be the width of the image minus the width of the bounding box, divided by two.
	$centered_x = LEFTMOST_X + ((TEXT_BLOCK_WIDTH - $bounding_box[4]) / 2);
	// Write the header
	imagettftext($im, HEADING_FONTSIZE, 0, $centered_x, HEADING_BASELINE, $header_text_color, FONT, $header_text);
		
	foreach($textlines as $line_to_write) {
		$line_to_write = trim($line_to_write);
		
		if( $line_to_write != "" ) 
		{
			$bounding_box = imagettfbbox(FONTSIZE, 0, FONT, $line_to_write);
			
			// The initial X position (far-left) is going to be the width of the image minus the width of the bounding box, divided by two.
			$centered_x = LEFTMOST_X + ((TEXT_BLOCK_WIDTH - $bounding_box[4]) / 2);
			
			// Write the text
			$bounding_box = imagettftext( $im, FONTSIZE, 0, $centered_x, $baseline_y, $black, FONT, $line_to_write);
			
			// Move down
			// 1 corresponds with lower left's Y coord, and 7 with upper left's Y coord.
			$baseline_y += abs($bounding_box[1] - $bounding_box[7]) + floor(FONTSIZE/2);
		}
	}
	
	// Set the content type, and render to image
	header("Content-type: image/png");
	imagepng($im, NULL);
	imagedestroy($im);

}
else
{ ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Warning Label Generator</title>
	<style type="text/css">
	
		label
		{
			display: block;
			margin-top: 5px;
		}
	
		input, select, textarea
		{
			display: block;
		}
		
		input, select
		{
			width: 225px;
		}
	
	</style>
</head>
<body style="padding-top: 150px;">
	<div>
	<div style="width: 400px; margin: auto;">
	<h1>Warning Label Generator</h1>
	<form method="post" action="warning.php">
	<fieldset>
	
	<label for="logo">Logo:</label>
	<select name="logo" id="logo">
<?php
foreach(array_keys($logos) as $logo) {
echo "\t\t<option>".$logo."</option>\n";
}
?>
	</select>
	
	<label for="heading">Heading (optional):</label>
	<input type="text" name="heading" id="heading" />
	
	<label for="message">Text:</label>
	<textarea name="message" id="message" rows="7" cols="26"></textarea>
	
	<label for="color">Color:</label> 
	<select name="color" id="color">
<?php
foreach(array_keys($colors) as $color) {
	echo "\t\t<option>".$color."</option>\n";
}
?>
	</select>
	
	<input type="submit" value="Generate" style="margin-top: 5px;" />
	</fieldset>
	</form>
	</div>
	</div>
</body>
</html>

<?php
}
?>