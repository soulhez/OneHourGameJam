<?php

function LoadEntries(){
	global $dictionary, $jams, $authors, $entries, $users, $config, $dbConn, $nextJamTime;
	
	//Clear public lists which get updated by this function
	$dictionary["jams"] = Array();
	$dictionary["jams_with_deleted"] = Array();
	$dictionary["authors"] = Array();
	$jams = Array();
	$authors = Array();
	$entries = Array();
	
	//Create lists of jams and jam entries
	$authorList = Array();
	$firstJam = true;
	$jamFromStart = 1;
	$totalEntries = 0;
	$largest_jam_number = -1;
	
	$sql = "SELECT * FROM jam ORDER BY jam_jam_number DESC";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";
	
	$suggestedNextJamTime = GetNextJamDateAndTime();
	$dictionary["next_jam_timer_code"] = gmdate("Y-m-d", $suggestedNextJamTime)."T".gmdate("H:i", $suggestedNextJamTime).":00Z";
	
	$currentJamData = GetCurrentJamNumberAndID();
	
	while($info = mysqli_fetch_array($data)){
		
		//Read data about the jam
		$newData = Array();
		$newData["jam_number"] = intval($info["jam_jam_number"]);
		$newData["start_time"] = $info["jam_start_datetime"];
		$newData["jam_id"] = intval($info["jam_id"]);
		$newData["jam_number_ordinal"] = ordinal(intval($info["jam_jam_number"]));
		$newData["username"] = $info["jam_username"];
		$newData["theme"] = $info["jam_theme"];
		$newData["theme_visible"] = $info["jam_theme"]; //Used for administration
		$newData["date"] = date("d M Y", strtotime($info["jam_start_datetime"]));
		$newData["time"] = date("H:i", strtotime($info["jam_start_datetime"]));
		$newData["colors"] = Array();
		if(intval($info["jam_deleted"]) == 1){
			$newData["jam_deleted"] = 1;
		}
		$jamColors = explode("|", $info["jam_colors"]);
		if(count($jamColors) == 0){
			$jamColors = Array("FFFFFF");
		}
		foreach($jamColors as $num => $color){
			$newData["colors"][] = Array("number" => $num, "color" => "#".$color, "color_hex" => $color);
		}
		$newData["colors_input_string"] = implode("-", $jamColors);
		$newData["minutes_to_jam"] = floor((strtotime($info["jam_start_datetime"] ." UTC") - time()) / 60);
		$newData["entries"] = Array();
		$newData["first_jam"] = $firstJam;
		$newData["entries_visible"] = $jamFromStart <= 2;
		if($firstJam){
			$firstJam = false;
		}
		
		$newData["is_recent"] = (intval($newData["jam_number"]) > intval($currentJamData["NUMBER"]) - intval($config["JAMS_CONSIDERED_RECENT"]));
		
		$sql = "SELECT * FROM entry WHERE entry_jam_id = ".$newData["jam_id"]." ORDER BY entry_id ASC";
		$data2 = mysqli_query($dbConn, $sql);
		$sql = "";
		
		while($info2 = mysqli_fetch_array($data2)){
			$entry = Array();
			
			//Entry basic information
			$entry["entry_id"] = $info2["entry_id"];
			$entry["title"] = $info2["entry_title"];
			$entry["title_url_encoded"] = urlencode($info2["entry_title"]);
			$entry["description"] = $info2["entry_description"];
			if(intval($info2["entry_deleted"]) == 1){
				$entry["entry_deleted"] = 1;
			}
			
			//Entry color
			$entry["color"] = "#".$info2["entry_color"];
			$entry["color256_red"] = hexdec(substr($info2["entry_color"], 0, 2));
			$entry["color256_green"] = hexdec(substr($info2["entry_color"], 2, 2));
			$entry["color256_blue"] = hexdec(substr($info2["entry_color"], 4, 2));
			$entry["color_lighter"] = "#".str_pad(dechex( ($entry["color256_red"] + 255) / 2 ), 2, "0", STR_PAD_LEFT).str_pad(dechex( ($entry["color256_green"] + 255) / 2 ), 2, "0", STR_PAD_LEFT).str_pad(dechex( ($entry["color256_blue"] + 255) / 2 ), 2, "0", STR_PAD_LEFT);
			$entry["color_non_white"] = "#".str_pad(dechex(min($entry["color256_red"], 0xDD)), 2, "0", STR_PAD_LEFT).str_pad(dechex(min($entry["color256_green"], 0xDD)), 2, "0", STR_PAD_LEFT).str_pad(dechex(min($entry["color256_blue"], 0xDD)), 2, "0", STR_PAD_LEFT);
			$entry["color_number"] = rand(0, count($newData["colors"]) - 1);
			foreach($newData["colors"] as $j => $clr){
				if($clr["color_hex"] == $entry["color"]){
					$entry["color_number"] = $clr["number"];
				}
			}
			
			//Entry author
			$author_username = $info2["entry_author"];
			$author = $author_username;
			$author_display = $author_username;
			if(isset($users[$author_username]["display_name"])){
				$author_display = $users[$author_username]["display_name"];
			}
			
			$entry["author_display"] = $author_display;
			$entry["author"] = $author;
			$entry["author_url_encoded"] = urlencode($author);
			
			$entry["url"] = str_replace("'", "\\'", $info2["entry_url"]);
			$entry["url_web"] = str_replace("'", "\\'", $info2["entry_url_web"]);
			$entry["url_windows"] = str_replace("'", "\\'", $info2["entry_url_windows"]);
			$entry["url_mac"] = str_replace("'", "\\'", $info2["entry_url_mac"]);
			$entry["url_linux"] = str_replace("'", "\\'", $info2["entry_url_linux"]);
			$entry["url_ios"] = str_replace("'", "\\'", $info2["entry_url_ios"]);
			$entry["url_android"] = str_replace("'", "\\'", $info2["entry_url_android"]);
			$entry["url_source"] = str_replace("'", "\\'", $info2["entry_url_source"]);
			$entry["screenshot_url"] = str_replace("'", "\\'", $info2["entry_screenshot_url"]);
			
			if($entry["url"] != ""){$entry["has_url"] = 1;}
			if($entry["url_web"] != ""){$entry["has_url_web"] = 1;}
			if($entry["url_windows"] != ""){$entry["has_url_windows"] = 1;}
			if($entry["url_mac"] != ""){$entry["has_url_mac"] = 1;}
			if($entry["url_linux"] != ""){$entry["has_url_linux"] = 1;}
			if($entry["url_ios"] != ""){$entry["has_url_ios"] = 1;}
			if($entry["url_android"] != ""){$entry["has_url_android"] = 1;}
			if($entry["url_source"] != ""){$entry["has_url_source"] = 1;}
			
			$entry["jam_number"] = $newData["jam_number"];
			$entry["jam_theme"] = $newData["theme"];
			
			$hasTitle = false;
			$hasDesc = false;
			$hasSS = false;
			
			if($entry["screenshot_url"] != "logo.png" &&
			   $entry["screenshot_url"] != ""){
				$entry["has_screenshot"] = 1;
				$hasSS = true;
			}
			
			if(trim($entry["title"]) != ""){
				$entry["has_title"] = 1;
				$hasTitle = true;
			}
			
			if(trim($entry["description"]) != ""){
				$entry["has_description"] = 1;
				$hasDesc = true;
			}
			
			if(!isset($entry["entry_deleted"])){
				if(isset($authorList[$author])){
					$authorList[$author]["entry_count"] += 1;
					$authorList[$author]["recent_participation"] += (($newData["is_recent"]) ? (100.0 / $config["JAMS_CONSIDERED_RECENT"]) : 0);
					if(intval($newData["jam_number"]) < intval($authorList[$author]["first_jam_number"])){
						$authorList[$author]["first_jam_number"] = $newData["jam_number"];
					}
					if(intval($newData["jam_number"]) > intval($authorList[$author]["last_jam_number"])){
						$authorList[$author]["last_jam_number"] = $newData["jam_number"];
					}
					$authorList[$author]["entries"][] = $entry;
				}else{
					if(isset($users[$author])){
						$authorList[$author] = $users[$author];
					}else{
						//Author does not have matching account (very old entry)
						$authorList[$author] = Array("username" => $author, "display_name" => $author_display);
					}
					$authorList[$author]["entry_count"] = 1;
					$authorList[$author]["recent_participation"] = (($newData["is_recent"]) ? (100.0 / $config["JAMS_CONSIDERED_RECENT"]) : 0);
					$authorList[$author]["first_jam_number"] = $newData["jam_number"];
					$authorList[$author]["last_jam_number"] = $newData["jam_number"];
					$authorList[$author]["entries"][] = $entry;
				}
			
				$newData["entries"][] = $entry;
				$entries[] = $entry;
			}
			$newData["entries_with_deleted"][] = $entry;
		}
		
		$totalEntries += count($newData["entries"]);
		$newData["entries_count"] = count($newData["entries"]);
		
		//Hide theme of not-yet-started jams
		
		$now = new DateTime();
		$datetime = new DateTime($newData["start_time"] . " UTC");
		$timeUntilJam = date_diff($datetime, $now);
		
		if($datetime > $now){
			$newData["theme"] = "Not yet announced";
			$newData["jam_started"] = false;
			if($timeUntilJam->days > 0){
				$newData["time_left"] = $timeUntilJam->format("%a days %H:%I:%S");
			}else if($timeUntilJam->h > 0){
				$newData["time_left"] = $timeUntilJam->format("%H:%I:%S");
			}else  if($timeUntilJam->i > 0){
				$newData["time_left"] = $timeUntilJam->format("%I:%S");
			}else if($timeUntilJam->s > 0){
				$newData["time_left"] = $timeUntilJam->format("%S seconds");
			}else{
				$newData["time_left"] = "Now!";
			}
			if(!isset($newData["jam_deleted"])){
				$nextJamTime = strtotime($newData["start_time"]);
				$dictionary["next_jam_timer_code"] = date("Y-m-d", $nextJamTime)."T".date("H:i", $nextJamTime).":00Z";
			}
		}else{
			$newData["jam_started"] = true;
		}
		
		//Insert into dictionary array
		if(!isset($newData["jam_deleted"])){
			$dictionary["jams"][] = $newData;
			$jams[] = $newData;
			$jamFromStart++;
			if($newData["jam_started"]){
				if($largest_jam_number < intval($newData["jam_number"])){
					$largest_jam_number = intval($newData["jam_number"]);
					$dictionary["current_jam"] = $newData;
				}
			}
		}
		$dictionary["jams_with_deleted"][] = $newData;
	}

	//Process authors list
	foreach($authorList as $k => $authorData){
		//Find admin candidates
		if($authorList[$k]["recent_participation"] >= $config["ADMIN_SUGGESTION_RECENT_PARTICIPATION"]){
			$authorList[$k]["admin_candidate_recent_participation_check_pass"] = 1;
		}
		if($authorList[$k]["entry_count"] >= $config["ADMIN_SUGGESTION_TOTAL_PARTICIPATION"]){
			$authorList[$k]["admin_candidate_total_participation_check_pass"] = 1;
		}
		if(	$authorList[$k]["admin_candidate_recent_participation_check_pass"] &&
			$authorList[$k]["admin_candidate_total_participation_check_pass"]){
				$authorList[$k]["is_admin_candidate"] = 1;
		}
		
		//Find inactive admins
		if($authorList[$k]["last_jam_number"] <= (count($jams) - $config["ADMIN_WARNING_WEEKS_SINCE_LAST_JAM"])){
			$authorList[$k]["is_inactive"] = 1;
		}
	}
	
	//Insert authors into dictionary
	foreach($authorList as $k => $authorData){
		$dictionary["authors"][] = $authorData;
		
		//Update users list with entry count for each
		foreach($dictionary["users"] as $i => $dictUserInfo){
			if($dictUserInfo["username"] == $k){
				$dictionary["users"][$i]["entry_count"] = $authorData["entry_count"];
				$dictionary["users"][$i]["recent_participation"] = $authorData["recent_participation"];
				$dictionary["users"][$i]["first_jam_number"] = $authorData["first_jam_number"];
				$dictionary["users"][$i]["last_jam_number"] = $authorData["last_jam_number"];
			}
		}
		//Update admins list with entry count for each
		foreach($dictionary["admins"] as $i => $dictUserInfo){
			if($dictUserInfo["username"] == $k){
				$dictionary["admins"][$i]["entry_count"] = $authorData["entry_count"];
				$dictionary["admins"][$i]["recent_participation"] = $authorData["recent_participation"];
				$dictionary["admins"][$i]["first_jam_number"] = $authorData["first_jam_number"];
				$dictionary["admins"][$i]["last_jam_number"] = $authorData["last_jam_number"];
				if(isset($authorData["is_inactive"])){
					$dictionary["admins"][$i]["is_inactive"] = 1;
				}
			}
		}
		//Update registered users list with entry count for each
		foreach($dictionary["registered_users"] as $i => $dictUserInfo){
			if($dictUserInfo["username"] == $k){
				$dictionary["registered_users"][$i]["entry_count"] = $authorData["entry_count"];
				$dictionary["registered_users"][$i]["recent_participation"] = $authorData["recent_participation"];
				$dictionary["registered_users"][$i]["first_jam_number"] = $authorData["first_jam_number"];
				$dictionary["registered_users"][$i]["last_jam_number"] = $authorData["last_jam_number"];
				if(isset($authorData["is_admin_candidate"])){
					$dictionary["registered_users"][$i]["is_admin_candidate"] = 1;
				}
			}
		}
		$authors[$authorData["username"]] = $authorData;
	}
	
	$dictionary["all_authors_count"] = count($authors);
	$dictionary["all_jams_count"] = count($jams);
	
	$dictionary["all_entries_count"] = $totalEntries;
	$dictionary["entries"] = $entries;
	
	//Prepare data for "Manage content" charts
	$jsFormattedThemesList = Array();
	$jsFormattedEntriesCountList = Array();
	foreach($jams as $id => $jam){
		$jsFormattedThemesList[] = "\"".str_replace("\"", "\\\"", $jam["theme"])."\"";
		$jsFormattedEntriesCountList[] = count($jam["entries"]);
	}
	$dictionary["js_formatted_themes_list"] = implode(",", array_reverse($jsFormattedThemesList));
	$dictionary["js_formatted_entries_count_list"] = implode(",", array_reverse($jsFormattedEntriesCountList));
	
	//Prepare data for "Manage users" charts
	$jsFormattedFirstTimeNumberList = Array();
	$jsFormattedLastTimeNumberList = Array();
	$jsFormattedFirstVsLastTimeDifferenceNumberList = Array();
	
	foreach($jams as $id => $jam){
		$jsFormattedFirstTimeNumberList[$jam["jam_number"]] = 0;
		$jsFormattedLastTimeNumberList[$jam["jam_number"]] = 0;
		$jsFormattedFirstVsLastTimeDifferenceNumberList[$jam["jam_number"]] = 0;
	}
	
	foreach($authorList as $id => $author){
		$firstJamNumber = $author["first_jam_number"];
		$lastJamNumber = $author["last_jam_number"];
		$jsFormattedFirstTimeNumberList[$firstJamNumber]++;
		$jsFormattedLastTimeNumberList[$lastJamNumber]--;
		
		$jsFormattedFirstVsLastTimeDifferenceNumberList[$firstJamNumber]++;
		$jsFormattedFirstVsLastTimeDifferenceNumberList[$lastJamNumber]--;
	}
	$dictionary["js_formatted_first_time_number_list"] = implode(",", array_reverse($jsFormattedFirstTimeNumberList));
	$dictionary["js_formatted_last_time_number_list"] = implode(",", array_reverse($jsFormattedLastTimeNumberList));
	$dictionary["js_formatted_first_vs_last_time_difference_number_list"] = implode(",", array_reverse($jsFormattedFirstVsLastTimeDifferenceNumberList));
}

//Checks if a jam is scheduled. If not and a jam is coming up, one is scheduled automatically.
function CheckNextJamSchedule(){
	global $themes, $nextJamTime;
	
	$autoScheduleThreshold = 2 * 60 * 60;
	
	$suggestedNextJamTime = GetNextJamDateAndTime();
	$now = time();
	$interval = $suggestedNextJamTime - $now;
	$colors = "e38484|e3b684|dee384|ade384|84e38d|84e3be|84d6e3|84a4e3|9684e3|c784e3";
	
	if($interval > 0 && $interval <= $autoScheduleThreshold){
		if($nextJamTime != ""){
			//A future jam is already scheduled
			return;
		}
		
		$selectedTheme = "";
		
		$selectedTheme = SelectRandomThemeByVoteDifference();
		if($selectedTheme == ""){
			$selectedTheme = SelectRandomThemeByPopularity();
		}
		if($selectedTheme == ""){
			$selectedTheme = SelectRandomTheme();
		}
		if($selectedTheme == ""){
			$selectedTheme = "Any theme";
		}
		
		$currentJamData = GetCurrentJamNumberAndID();
		$jamNumber = intval($currentJamData["NUMBER"] + 1);
		
		AddJamToDatabase("127.0.0.1", "AUTO", "AUTOMATIC", $jamNumber, $selectedTheme, "".gmdate("Y-m-d H:i", $suggestedNextJamTime), $colors);
	}
}

//Selects a random theme (or "" if none can be selected) by calculating the difference between positive and negative votes and
//selecting a proportional random theme by this difference
function SelectRandomThemeByVoteDifference(){
	global $themes;
	$minimumVotes = 10;
	
	$selectedTheme = "";
	
	$availableThemes = Array();
	$totalVotesDifference = 0;
	foreach($themes as $id => $theme){
		$themeOption = Array();
		
		if($theme["banned"]){
			continue;
		}
		
		$votesFor = $theme["votes_for"];
		$votesNeutral = $theme["votes_neutral"];
		$votesAgainst = $theme["votes_against"];
		$votesDifference = $votesFor - $votesAgainst;
		
		$votesTotal = $votesFor + $votesNeutral + $votesAgainst;
		$votesOpinionatedTotal = $votesFor + $votesAgainst;
		
		if($votesOpinionatedTotal <= 0){
			continue;
		}
		
		$votesPopularity = $votesFor / ($votesOpinionatedTotal);
		
		if($votesTotal <= 0 || $votesTotal <= $minimumVotes){
			continue;
		}
		
		$themeOption["theme"] = $theme["theme"];
		$themeOption["votes_for"] = $votesFor;
		$themeOption["votes_difference"] = $votesDifference;
		$themeOption["popularity"] = $votesPopularity;
		$totalVotesDifference += max(0, $votesDifference);
		
		$availableThemes[] = $themeOption;
	}
	
	if($totalVotesDifference > 0 && count($availableThemes) > 0){
		$selectedVote = rand(0, $totalVotesDifference);
		
		$runningVoteNumber = $selectedVote;
		foreach($availableThemes as $i => $availableTheme){
			$runningVoteNumber -= $availableTheme["votes_difference"];
			if($runningVoteNumber <= 0){
				$selectedTheme = $availableTheme["theme"];
				break;
			}
		}
	}
	
	return $selectedTheme;
}

//Selects a random theme (or "" if none can be selected) proportionally based on its popularity.
function SelectRandomThemeByPopularity(){
	global $themes;
	$minimumVotes = 10;
	
	$selectedTheme = "";
	
	$availableThemes = Array();
	$totalPopularity = 0;
	foreach($themes as $id => $theme){
		$themeOption = Array();
		
		if($theme["banned"]){
			continue;
		}
		
		$votesFor = $theme["votes_for"];
		$votesNeutral = $theme["votes_neutral"];
		$votesAgainst = $theme["votes_against"];
		$votesDifference = $votesFor - $votesAgainst;
		
		$votesTotal = $votesFor + $votesNeutral + $votesAgainst;
		$votesOpinionatedTotal = $votesFor + $votesAgainst;
		
		if($votesOpinionatedTotal <= 0){
			continue;
		}
		
		$votesPopularity = $votesFor / ($votesOpinionatedTotal);
		
		if($votesTotal <= 0 || $votesTotal <= $minimumVotes){
			continue;
		}
		
		$themeOption["theme"] = $theme["theme"];
		$themeOption["votes_for"] = $votesFor;
		$themeOption["votes_difference"] = $votesDifference;
		$themeOption["popularity"] = $votesPopularity;
		$totalPopularity += max(0, $votesPopularity);
		
		$availableThemes[] = $themeOption;
	}
	
	if($totalPopularity > 0 && count($availableThemes) > 0){
		$selectedPopularity = (rand(0, 100000) / 100000) * $totalPopularity;
		
		$runningPopularity = $selectedPopularity;
		foreach($availableThemes as $i => $availableTheme){
			$runningPopularity -= $availableTheme["popularity"];
			if($runningPopularity <= 0){
				$selectedTheme = $availableTheme["theme"];
				break;
			}
		}
	}
	
	return $selectedTheme;
}

//Selects a random theme with equal probability for all themes, not caring for number of votes
function SelectRandomTheme(){
	global $themes;
	$minimumVotes = 10;
	
	$selectedTheme = "";
	
	$availableThemes = Array();
	foreach($themes as $id => $theme){
		$themeOption = Array();
		
		if($theme["banned"]){
			continue;
		}
		
		$themeOption["theme"] = $theme["theme"];
		
		$availableThemes[] = $themeOption;
	}
	
	if(count($availableThemes) > 0){
		$selectedIndex = rand(0, count($availableThemes));
		$selectedTheme = $availableThemes[$selectedIndex]["theme"];
	}
	
	return $selectedTheme;
}

//Creates a new jam with the provided theme, which starts at the given date
//and time. All three are non-blank strings. $date and $time should be
//parsable by PHP's date(...) function. Function also authorizes the user
//(checks whether or not they are an admin).
function CreateJam($theme, $date, $time, $colorsList){
	global $ip, $userAgent, $loggedInUser;
	
	$currentJamData = GetCurrentJamNumberAndID();
	$jamNumber = intval($currentJamData["NUMBER"] + 1);
	$theme = trim($theme);
	$date = trim($date);
	$time = trim($time);
	$username = trim($loggedInUser["username"]);
	foreach($colorsList as $i => $color){
		$clr = trim($color);
		if(!preg_match('/^[0-9A-Fa-f]{6}/', $clr)){
			AddDataWarning("Invalid color: ".$clr." Must be a string of 6 hex values, which represent a color. Example:<br />FFFFFF-067BC2-D56062-F37748-ECC30B-84BCDA", false);
			return;
		}
		$colorsList[$i] = $clr;
	}
	
	//Authorize user (logged in)
	if(IsLoggedIn() === false){
		AddAuthorizationWarning("Not logged in.", false);
		return;
	}
	
	//Authorize user (is admin)
	if(IsAdmin() === false){
		AddAuthorizationWarning("Only admins can create jams.", false);
		return;
	}
	
	//Validate jam number
	if($jamNumber <= 0){
		AddDataWarning("Invalid jam number", false);
		return;
	}
	
	//Validate theme
	if(strlen($theme) <= 0){
		AddDataWarning("Invalid theme", false);
		return;
	}
	
	//Validate date and time and create datetime object
	if(strlen($date) <= 0){
		AddDataWarning("Invalid date", false);
		return;
	}else if(strlen($time) <= 0){
		AddDataWarning("Invalid time", false);
		return;
	}else{
		$datetime = strtotime($date." ".$time." UTC");
	}
	
	$colors = implode("|", $colorsList);
	
	$newJam = Array();
	$newJam["jam_number"] = $jamNumber;
	$newJam["theme"] = $theme;
	$newJam["date"] = gmdate("d M Y", $datetime);
	$newJam["time"] = gmdate("H:i", $datetime);
	$newJam["start_time"] = gmdate("c", $datetime);
	$newJam["entries"] = Array();
	
	AddJamToDatabase($ip, $userAgent, $username, $newJam["jam_number"], $newJam["theme"], "".gmdate("Y-m-d H:i", $datetime), $colors);
	
	AddDataSuccess("Jam Scheduled");
}

//Adds the jam with the provided data into the database
function AddJamToDatabase($ip, $userAgent, $username, $jamNumber, $theme, $startTime, $colors){
	global $dbConn;
	
	$escapedIP = mysqli_real_escape_string($dbConn, $ip);
	$escapedUserAgent = mysqli_real_escape_string($dbConn, $userAgent);
	$escapedUsername = mysqli_real_escape_string($dbConn, $username);
	$escapedJamNumber = mysqli_real_escape_string($dbConn, $jamNumber);
	$escapedTheme = mysqli_real_escape_string($dbConn, $theme);
	$escapedStartTime = mysqli_real_escape_string($dbConn, $startTime);
	$escapedColors = mysqli_real_escape_string($dbConn, $colors);
	
	$sql = "
		INSERT INTO jam
		(jam_id,
		jam_datetime,
		jam_ip,
		jam_user_agent,
		jam_username,
		jam_jam_number,
		jam_theme,
		jam_start_datetime,
		jam_colors,
		jam_deleted)
		VALUES
		(null,
		Now(),
		'$escapedIP',
		'$escapedUserAgent',
		'$escapedUsername',
		'$escapedJamNumber',
		'$escapedTheme',
		'$escapedStartTime',
		'$escapedColors',
		0);";
	
	$data = mysqli_query($dbConn, $sql);
	$sql = "";
}

//Edits an existing jam, identified by the jam number.
//Only changes the theme, date and time, does NOT change the jam number.
function EditJam($jamNumber, $theme, $date, $time, $colorsString){
	global $jams, $dbConn;
	
	//Authorize user (is admin)
	if(IsAdmin() === false){
		AddAuthorizationWarning("Only admins can edit jams.", false);
		return;
	}
	
	$theme = trim($theme);
	$date = trim($date);
	$time = trim($time);
	
	$colorsList = explode("-", $colorsString);
	$colorSHexCodes = Array();
	foreach($colorsList as $i => $color){
		$clr = trim($color);
		if(!preg_match('/^[0-9A-Fa-f]{6}/', $clr)){
			AddDataWarning("Invalid color: ".$clr." Must be a string of 6 hex values, which represent a color. Example:<br />FFFFFF-067BC2-D56062-F37748-ECC30B-84BCDA", false);
			return;
		}
		$colorSHexCodes[] = $clr;
	}
	$colors = implode("|", $colorSHexCodes);
	
	//Validate values
	$jamNumber = intval($jamNumber);
	if($jamNumber <= 0){
		AddDataWarning("invalid jam number", false);
		return;
	}
	
	if(strlen($theme) <= 0){
		AddDataWarning("invalid theme", false);
		return;
	}
	
	//Validate date and time and create datetime object
	if(strlen($date) <= 0){
		AddDataWarning("Invalid date", false);
		return;
	}else if(strlen($time) <= 0){
		AddDataWarning("Invalid time", false);
		return;
	}else{
		$datetime = strtotime($date." ".$time." UTC");
	}
	
	if(count($jams) == 0){
		return; //No jams exist
	}
	
	$escapedTheme = mysqli_real_escape_string($dbConn, $theme);
	$escapedStartTime = mysqli_real_escape_string($dbConn, "".gmdate("Y-m-d H:i", $datetime));
	$escapedJamNumber = mysqli_real_escape_string($dbConn, "$jamNumber");
	$escapedColors = mysqli_real_escape_string($dbConn, "$colors");
	
	$sql = "
		UPDATE jam
		SET jam_theme = '$escapedTheme', 
		    jam_start_datetime = '$escapedStartTime', 
		    jam_colors = '$escapedColors'
		WHERE jam_jam_number = $escapedJamNumber
		  AND jam_deleted = 0";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";
	
	AddDataSuccess("Jam Updated");
}



//Deletes an existing jam, identified by the jam number.
function DeleteJam($jamID){
	global $jams, $dbConn;
	
	//Authorize user (is admin)
	if(IsAdmin() === false){
		AddAuthorizationWarning("Only admins can delete jams.", false);
		return;
	}
	
	if(!CanDeleteJam($jamID)){
		AddInternalDataError("This jam cannot be deleted.", false);
		return;
	}
	
	//Validate values
	$jamID = intval($jamID);
	if($jamID <= 0){
		AddDataWarning("invalid jam ID", false);
		return;
	}
	
	if(count($jams) == 0){
		return; //No jams exist
	}
	
	$escapedJamID = mysqli_real_escape_string($dbConn, "$jamID");
	
	$sql = "UPDATE jam SET jam_deleted = 1 WHERE jam_id = $escapedJamID";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";
	
	AddDataSuccess("Jam Deleted");
}

//Returns true / false based on whether or not the specified jam can be deleted
function CanDeleteJam($jamID){
	global $jams, $dbConn;
	
	//Authorize user (is admin)
	if(IsAdmin() === false){
		return FALSE;
	}
	
	//Validate values
	$jamID = intval($jamID);
	if($jamID <= 0){
		return FALSE;
	}
	
	if(!JamExists($jamID)){
		return FALSE;
	}
	
	$escapedJamID = mysqli_real_escape_string($dbConn, "$jamID");
	
	$sql = "
		SELECT 1
		FROM entry
		WHERE entry_jam_id = $escapedJamID
		AND entry_deleted = 0;
		";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";
	
	if(mysqli_fetch_array($data)){
		return false;
	}else{
		return true;
	}
}

//Returns true / false based on whether or not the specified jam exists (and has not been deleted)
function JamExists($jamID){
	global $dbConn;
	
	//Validate values
	$jamID = intval($jamID);
	if($jamID <= 0){
		return FALSE;
	}
	
	$escapedJamID = mysqli_real_escape_string($dbConn, "$jamID");
	
	$sql = "
		SELECT 1
		FROM jam
		WHERE jam_id = $escapedJamID
		AND jam_deleted = 0;
		";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";
	
	if(mysqli_fetch_array($data)){
		return true;
	}else{
		return false;
	}
}

// Returns a jam given its number.
// The dictionary of jams must have been previously loaded.
function GetJamByNumber($jamNumber) {
	global $jams;

	foreach ($jams as $jam) {
		if ($jam["jam_number"] == $jamNumber) {
			return $jam;
		}
	}

	return null;
}

//Creates or updates a jam entry. $jam_number is a mandatory jam number to submit to.
//All other parameters are strings: $gameName and $gameURL must be non-blank
//$gameURL must be a valid URL, $screenshotURL can either be blank or a valid URL.
//If blank, a default image is used instead. description must be non-blank.
//Function also authorizes the user (must be logged in)
function SubmitEntry($jam_number, $gameName, $gameURL, $gameURLWeb, $gameURLWin, $gameURLMac, $gameURLLinux, $gameURLiOS, $gameURLAndroid, $gameURLSource, $screenshotURL, $description, $jamColorNumber){
	global $loggedInUser, $_FILES, $dbConn, $ip, $userAgent, $jams;
	
	$gameName = trim($gameName);
	$gameURL = trim($gameURL);
	$gameURLWeb = trim($gameURLWeb);
	$gameURLWin = trim($gameURLWin);
	$gameURLMac = trim($gameURLMac);
	$gameURLLinux = trim($gameURLLinux);
	$gameURLiOS = trim($gameURLiOS);
	$gameURLAndroid = trim($gameURLAndroid);
	$gameURLSource = trim($gameURLSource);
	$screenshotURL = trim($screenshotURL);
	$description = trim($description);
	$jamColorNumber = intval(trim($jamColorNumber));
	
	//Authorize user
	if(IsLoggedIn() === false){
		AddAuthorizationWarning("Not logged in.", false);
		return;
	}
	
	//Validate game name
	if(strlen($gameName) < 1){
		AddDataWarning("Game name not provided", false);
		return;
	}
	
	$urlValid = FALSE;
	//Validate that at least one of the provided game URLs is valid
	$gameURL = SanitizeURL($gameURL);
	$gameURLWeb = SanitizeURL($gameURLWeb);
	$gameURLWin = SanitizeURL($gameURLWin);
	$gameURLMac = SanitizeURL($gameURLMac);
	$gameURLLinux = SanitizeURL($gameURLLinux);
	$gameURLiOS = SanitizeURL($gameURLiOS);
	$gameURLAndroid = SanitizeURL($gameURLAndroid);
	$gameURLSource = SanitizeURL($gameURLSource);
	
	if($gameURL || $gameURLWeb || $gameURLWin || $gameURLMac || $gameURLLinux || $gameURLiOS || $gameURLAndroid){
		$urlValid = TRUE;
	}
	
	//Did at least one url pass validation?
	if($urlValid == FALSE){
		AddDataWarning("Invalid game url", false);
		return;
	}
	
	//Validate description
	if(strlen($description) <= 0){
		AddDataWarning("Invalid description", false);
		return;
	}
	
	//Check that a jam exists
	if (!is_int($jam_number)) {
		AddDataWarning('Invalid jam number', false);
		return;
	}
	$jam = GetJamByNumber($jam_number);
	if($jam == null || $jam["jam_number"] == 0){
		AddInternalDataError("No jam to submit to", false);
		return;
	}
	
	if(count($jams) == 0){
		AddInternalDataError("No jam to submit to", false);
		return;
	}
	
	//Validate color
	if($jamColorNumber < 0 || count($jam["colors"]) <= $jamColorNumber){
		AddDataWarning("Selected invalid color", false);
		return;
	}
	$color = $jam["colors"][$jamColorNumber]["color_hex"];
	
	//Upload screenshot
	$jam_folder = "data/jams/jam_$jam_number";
	if(isset($_FILES["screenshotfile"]) && $_FILES["screenshotfile"] != null && $_FILES["screenshotfile"]["size"] != 0){
		$uploadPass = 0;
		$imageFileType = strtolower(pathinfo($_FILES["screenshotfile"]["name"], PATHINFO_EXTENSION));
		$target_file = $jam_folder . "/".$loggedInUser["username"]."." . $imageFileType;
		$check = getimagesize($_FILES["screenshotfile"]["tmp_name"]);
		
		if($check !== false) {
			$uploadPass = 1;
		} else {
			AddDataWarning("Uploaded screenshot is not an image", false);
			return;
			$uploadPass = 0;
		}
		
		if ($_FILES["screenshotfile"]["size"] > 5000000) {
			AddDataWarning("Uploaded screenshot is too big (max 5MB)", false);
			return;
			$uploadPass = 0;
		}
		
		if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
		&& $imageFileType != "gif" ) {
			AddDataWarning("Uploaded screenshot is not jpeg, png or gif", false);
			return;
			$uploadPass = 0;
		}
		
		if($uploadPass == 1){
			if(!file_exists($jam_folder)){
				mkdir($jam_folder);
				file_put_contents($jam_folder."/.htaccess", "Order allow,deny\nAllow from all");
			}
			move_uploaded_file($_FILES["screenshotfile"]["tmp_name"], $target_file);
			$screenshotURL = $target_file;
		}
	}
	
	//Default screenshot URL
	if($screenshotURL == ""){
		$screenshotURL = "logo.png";
	}
	
	//Create or update entry
	if(isset($jam["entries"])){
		$entryUpdated = false;
		foreach($jam["entries"] as $i => $entry){
			if($entry["author"] == $loggedInUser["username"]){
				//Updating existing entry
				$existingScreenshot = $jam["entries"][$i]["screenshot_url"];
				if($screenshotURL == "logo.png"){
					if($existingScreenshot != "" && $existingScreenshot != "logo.png"){
						$screenshotURL = $existingScreenshot;
					}
				}
				
				$escapedGameName = mysqli_real_escape_string($dbConn, $gameName);
				$escapedGameURL = mysqli_real_escape_string($dbConn, $gameURL);
				$escapedGameURLWeb = mysqli_real_escape_string($dbConn, $gameURLWeb);
				$escapedGameURLWin = mysqli_real_escape_string($dbConn, $gameURLWin);
				$escapedGameURLMac = mysqli_real_escape_string($dbConn, $gameURLMac);
				$escapedGameURLLinux = mysqli_real_escape_string($dbConn, $gameURLLinux);
				$escapedGameURLiOS = mysqli_real_escape_string($dbConn, $gameURLiOS);
				$escapedGameURLAndroid = mysqli_real_escape_string($dbConn, $gameURLAndroid);
				$escapedGameURLSource = mysqli_real_escape_string($dbConn, $gameURLSource);
				$escapedScreenshotURL = mysqli_real_escape_string($dbConn, $screenshotURL);
				$escapedDescription = mysqli_real_escape_string($dbConn, $description);
				$escapedAuthorName = mysqli_real_escape_string($dbConn, $entry["author"]);
				$escaped_jamNumber = mysqli_real_escape_string($dbConn, $jam_number);
				$escaped_color = mysqli_real_escape_string($dbConn, $color);
				
				$sql = "
				UPDATE entry
				SET
					entry_title = '$escapedGameName',
					entry_url = '$escapedGameURL',
					entry_url_web = '$escapedGameURLWeb',
					entry_url_windows = '$escapedGameURLWin',
					entry_url_mac = '$escapedGameURLMac',
					entry_url_linux = '$escapedGameURLLinux',
					entry_url_ios = '$escapedGameURLiOS',
					entry_url_android = '$escapedGameURLAndroid',
					entry_url_source = '$escapedGameURLSource',
					entry_screenshot_url = '$escapedScreenshotURL',
					entry_description = '$escapedDescription',
					entry_color = '$escaped_color'
				WHERE 
					entry_author = '$escapedAuthorName'
				AND entry_jam_number = $escaped_jamNumber
				AND entry_deleted = 0;

				";
				$data = mysqli_query($dbConn, $sql);
				$sql = "";
	
				AddDataSuccess("Game Updated");
				
				$entryUpdated = true;
			}
		}
		if(!$entryUpdated){
			$currentJam = $jams[0];
			
			foreach($jams as $index => $eachJam){
				if($eachJam["jam_started"]){
					$currentJam = $jams[$index];
					break;
				}
			}
			
			if ($jam_number != $currentJam["jam_number"]) {
				AddDataWarning('Cannot make a new submission to a past jam', false);
				return;
			}

			$escaped_ip = mysqli_real_escape_string($dbConn, $ip);
			$escaped_userAgent = mysqli_real_escape_string($dbConn, $userAgent);
			$escaped_jamId = mysqli_real_escape_string($dbConn, $jam["jam_id"]);
			$escaped_jamNumber = mysqli_real_escape_string($dbConn, $jam["jam_number"]);
			$escaped_gameName = mysqli_real_escape_string($dbConn, $gameName);
			$escaped_description = mysqli_real_escape_string($dbConn, $description);
			$escaped_aurhor = mysqli_real_escape_string($dbConn, $loggedInUser["username"]);
			$escaped_gameURL = mysqli_real_escape_string($dbConn, $gameURL);
			$escaped_gameURLWeb = mysqli_real_escape_string($dbConn, $gameURLWeb);
			$escaped_gameURLWin = mysqli_real_escape_string($dbConn, $gameURLWin);
			$escaped_gameURLMac = mysqli_real_escape_string($dbConn, $gameURLMac);
			$escaped_gameURLLinux = mysqli_real_escape_string($dbConn, $gameURLLinux);
			$escaped_gameURLiOS = mysqli_real_escape_string($dbConn, $gameURLiOS);
			$escaped_gameURLAndroid = mysqli_real_escape_string($dbConn, $gameURLAndroid);
			$escaped_gameURLSource = mysqli_real_escape_string($dbConn, $gameURLSource);
			$escaped_ssURL = mysqli_real_escape_string($dbConn, $screenshotURL);
			$escaped_color = mysqli_real_escape_string($dbConn, $color);
			
			$sql = "
				INSERT INTO entry
				(entry_id,
				entry_datetime,
				entry_ip,
				entry_user_agent,
				entry_jam_id,
				entry_jam_number,
				entry_title,
				entry_description,
				entry_author,
				entry_url,
				entry_url_web,
				entry_url_windows,
				entry_url_mac,
				entry_url_linux,
				entry_url_ios,
				entry_url_android,
				entry_url_source,
				entry_screenshot_url,
				entry_color)
				VALUES
				(null,
				Now(),
				'$escaped_ip',
				'$escaped_userAgent',
				$escaped_jamId,
				$escaped_jamNumber,
				'$escaped_gameName',
				'$escaped_description',
				'$escaped_aurhor',
				'$escaped_gameURL',
				'$escaped_gameURLWeb',
				'$escaped_gameURLWin',
				'$escaped_gameURLMac',
				'$escaped_gameURLLinux',
				'$escaped_gameURLiOS',
				'$escaped_gameURLAndroid',
				'$escaped_gameURLSource',
				'$escaped_ssURL',
				'$escaped_color');
			";
			$data = mysqli_query($dbConn, $sql);
			$sql = "";
	
			AddDataSuccess("Game Submitted");
		}
	}
	
	LoadEntries();
}

//Edits an existing entry, identified by the jam number and author.
//Only changes the title, game url and screenshot url, does NOT change the jam number or author.
function EditEntry($jamNumber, $author, $title, $gameURL, $screenshotURL){
	global $jams;
	
	//Authorize user (is admin)
	if(IsAdmin() === false){
		AddAuthorizationWarning("Only admins can edit entries.", false);
		return;
	}
	
	$author = trim($author);
	$title = trim($title);
	$gameURL = trim($gameURL);
	$screenshotURL = trim($screenshotURL);
	
	//Validate values
	$jamNumber = intval($jamNumber);
	if($jamNumber <= 0){
		AddDataWarning("invalid jam number", false);
		return;
	}
	
	//Validate title
	if(strlen($title) <= 0){
		AddDataWarning("invalid title", false);
		return;
	}
	
	//Validate Game URL
	if(SanitizeURL($gameURL) === false){
		AddDataWarning("Invalid game URL", false);
		return;
	}
	
	//Validate Screenshot URL
	if($screenshotURL == ""){
		$screenshotURL = "logo.png";
	}else if(SanitizeURL($screenshotURL) === false){
		AddDataWarning("Invalid screenshot URL. Leave blank for default.", false);
		return;
	}
	
	if(count($jams) == 0){
		return; //No jams exist
	}
	
	foreach($jams as $i => $jam){
		if(intval($jam["jam_number"]) == $jamNumber){
			foreach($jam["entries"] as $j => $entry){
				if($entry["author"] == $author){
					$jam["entries"][$j]["title"] = $title;
					$jam["entries"][$j]["url"] = $gameURL;
					$jam["entries"][$j]["screenshot_url"] = $screenshotURL;
					file_put_contents("data/jams/jam_$jamNumber.json", json_encode($jam));
					break;
				}
			}
			break;
		}
	}
}

//Deletes an existing entry, identified by the entryID.
function DeleteEntry($entryID){
	global $jams, $dbConn;
	
	//Authorize user (is admin)
	if(IsAdmin() === false){
		AddAuthorizationWarning("Only admins can delete entries.", false);
		return;
	}
	
	if(!CanDeleteEntry($entryID)){
		AddDataWarning("This entry cannot be deleted.", false);
		return;
	}
	
	//Validate values
	$entryID = intval($entryID);
	if($entryID <= 0){
		AddDataWarning("invalid jam ID", false);
		return;
	}
	
	if(count($jams) == 0){
		return; //No jams exist
	}
	
	$escapedEntryID = mysqli_real_escape_string($dbConn, "$entryID");
	
	$sql = "UPDATE entry SET entry_deleted = 1 WHERE entry_id = $escapedEntryID";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";
	
	AddDataSuccess("Game Deleted");
}

//Returns true / false based on whether or not the specified entry can be deleted
function CanDeleteEntry($entryID){
	global $jams, $dbConn;
	
	//Authorize user (is admin)
	if(IsAdmin() === false){
		return FALSE;
	}
	
	//Validate values
	$entryID = intval($entryID);
	if($entryID <= 0){
		return FALSE;
	}
	
	if(!EntryExists($entryID)){
		return FALSE;
	}
	
	return true;
}

//Returns true / false based on whether or not the specified entry exists (and has not been deleted)
function EntryExists($entryID){
	global $dbConn;
	
	//Validate values
	$entryID = intval($entryID);
	if($entryID <= 0){
		return FALSE;
	}
	
	$escapedEntryID = mysqli_real_escape_string($dbConn, "$entryID");
	
	$sql = "
		SELECT 1
		FROM entry
		WHERE entry_id = $escapedEntryID
		AND entry_deleted = 0;
		";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";
	
	if(mysqli_fetch_array($data)){
		return true;
	}else{
		return false;
	}
}

function GetEntriesOfUserFormatted($author){
	global $dbConn;
	
	$escapedAuthor = mysqli_real_escape_string($dbConn, $author);
	$sql = "
		SELECT *
		FROM entry
		WHERE entry_author = '$escapedAuthor';
	";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";
	
	return ArrayToHTML(MySQLDataToArray($data)); 
}

function GetJamsOfUserFormatted($username){
	global $dbConn;
	
	$escapedUsername = mysqli_real_escape_string($dbConn, $username);
	$sql = "
		SELECT *
		FROM jam
		WHERE jam_username = '$escapedUsername';
	";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";
	
	return ArrayToHTML(MySQLDataToArray($data)); 
}

?>