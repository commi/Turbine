<?php

/**
 * This file is part of Turbine
 * http://github.com/SirPepe/Turbine
 *
 * Copyright Peter Kröner
 * Licensed under GNU LGPL 3, see license.txt or http://www.gnu.org/licenses/
 */


/**
 * background gradient
 * creates a cross-browser linear vertical or horizontal background gradient (other angles or radial gradient not supported)
 * Adds vendor-specific code for gradients
 *
 * Usage:     Use simplest possible notation as planned by W3C: http://dev.w3.org/csswg/css3-images/#linear-gradients
 *            background: linear-gradient([direction:<top|left>],[startcolor<hex|rgb|rgba>],[endcolor<hex|rgb|rgba>])
 *
 * Example 1: background: linear-gradient(top,#FFF,#000) // vertical gradient, from top to bottom, from white to black
 * Example 2: background-image: linear-gradient(left,rgb(255,255,255),rgb(0,0,0)) // horizontal gradient, from left to right, from white to black
 * Status:    Stable
 * Version:   2.0
 *
 *
 * backgroundgradient
 * @param mixed &$parsed
 * @return void
 */
function backgroundgradient(&$parsed){
	global $cssp, $browser;
	include('lib/utility.php');
	$settings = Plugin::get_settings('backgroundgradient');
	// Searches for W3C-style two-stepped linear gradient
	$gradientregex = '/linear-gradient\s*?\(\s*?(top|left)\s*?,\s*(\#[0-9A-F]+|(?:rgb|hsl)(?:a)*\s*\(.+?\)),\s*(\#[0-9A-F]+|(?:rgb|hsl)(?:a)*\s*\(.+?\))\s*\)/i';
	// In which properties to search
	$urlproperties = array('background', 'background-image');
	// Loop through the array
	foreach($parsed as $block => $css){
		foreach($parsed[$block] as $selector => $styles){
			foreach($urlproperties as $property){
				if(isset($parsed[$block][$selector][$property])){
					$num_values = count($parsed[$block][$selector][$property]);
					for($i = 0; $i < $num_values; $i++){
						if(preg_match($gradientregex, $parsed[$block][$selector][$property][$i], $matches) > 0){
							// Recalculate the matched colors to rgba for maximum compatibility
							$matches[2] = Utility::rgbasyntax(Utility::any2rgba($matches[2]));
							$matches[3] = Utility::rgbasyntax(Utility::any2rgba($matches[3]));

							// Gecko
							$parsed[$block][$selector][$property][] = preg_replace(
								$gradientregex,
								'-moz-linear-gradient('.$matches[1].','.$matches[2].','.$matches[3].')',
								$parsed[$block][$selector][$property][$i]
							);
							// Opera >= 11.10
							$parsed[$block][$selector][$property][] = preg_replace(
								$gradientregex,
								'-o-linear-gradient('.$matches[1].','.$matches[2].','.$matches[3].')',
								$parsed[$block][$selector][$property][$i]
							);
							// IE >= 11
							$parsed[$block][$selector][$property][] = preg_replace(
								$gradientregex,
								'-ms-linear-gradient('.$matches[1].','.$matches[2].','.$matches[3].')',
								$parsed[$block][$selector][$property][$i]
							);

							// Webkit and KHTML
							if(strtolower($matches[1]) == 'top'){
								$webkit_gradientdirection = 'left top,left bottom';
							}
							else{
								$webkit_gradientdirection = 'left top,right top';
							}
							$parsed[$block][$selector][$property][] = preg_replace(
								$gradientregex,
								'-webkit-gradient(linear,'.$webkit_gradientdirection.',from('.$matches[2].'),to('.$matches[3].'))',
								$parsed[$block][$selector][$property][$i]
							);

							// Use a SVG background for Opera AND IE9
							if(($browser->engine == 'opera' AND $browser->engine_version < 11.10) OR ($browser->engine == 'ie' AND $browser->engine_version >= 9.0)){
								if('top' == strtolower($matches[1])){
									$svg_direction = 'x1="0%" y1="0%" x2="0%" y2="100%"';
								}
								else{
									$svg_direction = 'x1="0%" y1="0%" x2="100%" y2="0%"';
								}
								$svg_startcolor = $matches[2];
								$svg_endcolor   = $matches[3];

								$svg_url = 'data:image/svg+xml;base64,' . preg_replace('/\s+/', ' ', base64_encode('<?xml version="1.0" standalone="no"?>
								<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
								<svg width="100%" height="100%" version="1.1" xmlns="http://www.w3.org/2000/svg">
									<defs>
										<linearGradient id="gradient" '.$svg_direction.'>
											<stop offset="0%" style="stop-color:'.$svg_startcolor.';" />
											<stop offset="100%" style="stop-color:'.$svg_endcolor.';" />
										</linearGradient>
									</defs>
									<rect width="100%" height="100%" style="fill:url(#gradient)"/>
								</svg>'));

								$parsed[$block][$selector][$property][] = preg_replace(
									$gradientregex,
									'url('.$svg_url.')',
									$parsed[$block][$selector][$property][$i]
								);
							}

							// Add comment for the background property
							CSSP::comment($parsed[$block][$selector], $property, 'Modified by background-gradient plugin');

							// Use filter fallbacks in IE<9
							if(!in_array('noie', $settings) AND ($browser->engine == 'ie' AND $browser->engine_version < 9)){
								$filter_properties = array();
								if(strtolower($matches[1]) == 'top'){
									$ie_gradienttype = '0';
								}
								else{
									$ie_gradienttype = '1';
								}
								// Convert colors to hex
								$matches[2] = Utility::hexsyntax(Utility::any2rgba($matches[2]), true);
								$matches[3] = Utility::hexsyntax(Utility::any2rgba($matches[3]), true);
								// Build filter
								$filter = 'progid:DXImageTransform.Microsoft.gradient(startColorstr='.$matches[2].',endColorstr='.$matches[3].',gradientType='.$ie_gradienttype.')';
								// Legacy IE compliance
								$filter_properties['filter'] = array($filter);
								// IE8 compliance
								$filter_properties['-ms-filter'] = array($filter);
								// Salvage any background information that is NOT gradient stuff and preserve it in a form IE can handle
								$background_rest = preg_replace(
									'/(-moz-|-webkit-|-khtml-)*linear-gradient\s*?\(\s*?(top|left)\s*?,\s*(\#[0-9A-F]+|(?:rgb|hsl)(?:a)*\s*\(.+?\)),\s*(\#[0-9A-F]+|(?:rgb|hsl)(?:a)*\s*\(.+?\))\s*\)/i',
									'',
									$parsed[$block][$selector][$property][$i]
								);
								$background_rest = trim($background_rest);
								if($background_rest != ''){
									#$filter_properties['*background'] = array($background_rest);            // IE7 and 6
									$filter_properties['background /*\**/'] = array($background_rest.'\9'); // IE8
								}
								// hasLayout stuff
								$filter_properties['zoom'] = array('1');

								// Insert all filters
								$cssp->insert_properties($filter_properties, $block, $selector, NULL, $property);
								foreach($filter_properties as $filter_property => $filter_value){
									CSSP::comment($parsed[$block][$selector], $filter_property, 'Modified by background-gradient plugin');
								}
							} // End if(!in_array('noie', $settings))

							// Remove the original value
							//unset($parsed[$block][$selector][$property][$i]);

						}
					}
				}
			}
		}
	}
}


/**
 * Register the plugin
 */
$cssp->register_plugin('backgroundgradient', 'backgroundgradient', 'before_glue', 0);


?>
