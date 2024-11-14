<?php
/*
Plugin Name: Crazy Simple Calendar
Description: This is a super simple calendar plugin for managing a monthly table of daily events.
Version: 1.5.3.4
Author: StratLab Marketing
Author URI: https://stratlab.ca/
Text Domain: simple-calendar
Requires at least: 6.0
Requires PHP: 7.0
Plugin URI: https://github.com/carterfromsl/simple-calendar/
*/

// Connect with the StratLab Auto-Updater for plugin updates
add_action('plugins_loaded', function() {
    if (class_exists('StratLabUpdater')) {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_file = __FILE__;
        $plugin_data = get_plugin_data($plugin_file);

        do_action('stratlab_register_plugin', [
            'slug' => plugin_basename($plugin_file),
            'repo_url' => 'https://api.github.com/repos/carterfromsl/simple-calendar/releases/latest',
            'version' => $plugin_data['Version'], 
            'name' => $plugin_data['Name'],
            'author' => $plugin_data['Author'],
            'homepage' => $plugin_data['PluginURI'],
            'description' => $plugin_data['Description'],
            'access_token' => '', // Add if needed for private repo
        ]);
    }
});

class Simple_Calendar_Plugin {
	
	public function register_query_vars($vars) {
		$vars[] = 'cs-year';
		$vars[] = 'cs-month';
		return $vars;
	}

    public function __construct() {
		add_shortcode('simple_calendar', array($this, 'generate_calendar'));
		add_shortcode('simple_event', array($this, 'simple_event_shortcode'));
		add_action('admin_menu', array($this, 'register_admin_page'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
   
		add_filter('query_vars', array($this, 'register_query_vars'));
		register_activation_hook(__FILE__, array($this, 'flush_rewrite_rules'));
	}

	// Method to flush rewrite rules
    public function flush_rewrite_rules() {
        flush_rewrite_rules();
    }

    public function enqueue_styles() {
        wp_enqueue_style('simple-calendar', plugins_url('style.css', __FILE__));
    }
    
    public function enqueue_admin_styles($hook) {
        if ('toplevel_page_simple-calendar' !== $hook) {
            return;
        }
        wp_enqueue_style('simple-calendar-admin', plugins_url('admin-styles.css', __FILE__));
    }
	
	public function cleanup_old_data() {
		// Get date for 12 months ago
		$dateThreshold = new DateTime();
		$dateThreshold->modify("-12 month");

		// Get all simple_calendar options
		global $wpdb;
		$option_names = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'simple_calendar_%'");

		foreach ($option_names as $option_name) {
			// Split the option name into parts
			$parts = explode('_', $option_name);
			// Construct a DateTime object for the date stored in the option
			$option_date = new DateTime($parts[2].'-'.$parts[3].'-01');

			// If this option is for a date more than 12 months ago, delete it
			if ($option_date < $dateThreshold) {
				delete_option($option_name);
			}
		}
	}

    public function register_admin_page() {
        add_menu_page(
            'Simple Calendar Settings',
            'Simple Calendar',
            'manage_options',
            'simple-calendar',
			array($this, 'admin_page_content'),
			'dashicons-calendar-alt',
			10.5
        );
    }

    public function admin_page_content() {
		
		$successMessage = '';

		// Check if the "insert_holidays" button was clicked
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['insert_holidays'])) {
			$this->insert_common_holidays();
			$successMessage = "<div class='updated'><p>Common holidays have been successfully added to your calendar for the next three years.</p></div>";
		}

		// Display the success message at the top of the page
		if (!empty($successMessage)) {
			echo $successMessage;
		}
		
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_month_year'])) {
			$month = str_pad($_POST['month'], 2, '0', STR_PAD_LEFT);
			$year = $_POST['year'];
		} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_events'])) {
			$month = $_POST['selected_month'];
			$year = $_POST['selected_year'];
		} else {
			$date = new DateTime();
			$month = $date->format('m');
			$year = $date->format('Y');
		}

		$numDaysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$monthName = DateTime::createFromFormat('!m', $month)->format('F');
		$monthNameShort = substr($monthName, 0, 3);

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_events'])) {
			for ($day = 1; $day <= $numDaysInMonth; $day++) {
				$option_name = "simple_calendar_{$year}_{$month}_".str_pad($day, 2, '0', STR_PAD_LEFT);
				if (!empty($_POST[$option_name])) {
					update_option($option_name, wp_kses_post($_POST[$option_name]));
				} else {
					delete_option($option_name);
				}
			}
		}

		// Check if the "save_css" button was clicked
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_css'])) {
			// Save the custom CSS
			update_option('simple_calendar_custom_css', wp_strip_all_tags($_POST['custom_css']));
		}

		echo "<div class='cs-admin'>";
		echo "<h2>Crazy Simple Calendar Settings</h2>";
		echo "Enter the content for each day of this month here.<br>HTML is allowed but be careful to use proper formatting!";
		echo "<p>Include <code>[simple_calendar]</code> on the page or post you want your calendar to be displayed.</p>";
		echo "<p>Or use <code>[simple_event]</code> to display the event(s) for today.</p>";

		echo "<form method='post'>";
		echo "<select name='month' id='month'>";
		for ($i = 1; $i <= 12; $i++) {
			$selected = $i == $month ? 'selected' : '';
			echo "<option value='{$i}' {$selected}>".date('F', mktime(0, 0, 0, $i, 10))."</option>";
		}
		echo "</select>";
		echo "<input type='number' name='year' id='year' value='{$year}' min='2000' max='2099'>";
		echo "<input class='button' type='submit' name='select_month_year' value='Select'>";

		echo "<h2>Events for {$monthName}, {$year}</h2>";

		echo "<input type='hidden' name='selected_month' value='{$month}'>";
		echo "<input type='hidden' name='selected_year' value='{$year}'>";
		
		echo "<div class='cs-cell-wrap'>";
		
		for ($day = 1; $day <= $numDaysInMonth; $day++) {
			$option_name = "simple_calendar_{$year}_{$month}_".str_pad($day, 2, '0', STR_PAD_LEFT);
			$option_value = get_option($option_name, '');
			echo "<div class='cs-cell'><label for='{$option_name}'>{$monthNameShort} {$day}, {$year}</label> <textarea id='{$option_name}' name='{$option_name}'>{$option_value}</textarea></div>";
		}
		
		echo "</div>";

		echo "<input class='button button-primary button-large' type='submit' name='save_events' value='Save'>";
		echo "</form>";
		
		// Check if the "insert_holidays" button was clicked
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['insert_holidays'])) {
			$this->insert_common_holidays();
		}

		// Form to pre-populate holidays
		echo "<div class='cs-meta'><h2>Insert Common Holidays</h2>";
		echo "<p>Click the button below to pre-populate the next 3 years of your calendar with common holidays! If you already have content entered on a holiday, that date will be skipped.</p>";
		echo "<form method='post'>";
		echo "<input class='button' type='submit' name='insert_holidays' value='Pre-Populate Holidays'>";
		echo "</form></div>";
			
		// Add textarea for custom CSS
		echo "<div class='cs-meta'><h2>Custom CSS</h2>";
		echo "<form method='post'>";
		echo "<textarea name='custom_css' rows='10' cols='50'>" . get_option('simple_calendar_custom_css', '') . "</textarea>";
		echo "<input class='button' type='submit' name='save_css' value='Save CSS'>";
		echo "</form></div>";
		
		// Check if the "delete old data" button was clicked
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_old_data'])) {
			$this->cleanup_old_data();
		}
		
		// Add button for deleting old data
		echo "<div class='cs-meta'><h2>Clean up old data</h2>";
		echo "<p>Click the button below to delete all calendar data older than 12 months. This can help keep your site speed up!</p>";
		echo "<form method='post'>";
		echo "<input class='button' type='submit' name='delete_old_data' value='Delete Old Calendar Data'>";
		echo "</form></div>";
		
		echo "</div>";
	}
	
	public function insert_common_holidays() {
		// Define common holidays
		$holidays = [
			['12-25', '<b>Christmas Day 🌟</b>'],
			['12-24', '<b>Christmas Eve 🎄</b>'],
			['01-01', '<b>New Year\'s Day 🥳</b>'],
			['12-31', '<b>New Year\'s Eve 🎆</b>'],
			['10-31', '<b>Halloween 🎃</b>'],
			['07-01', '<b>Canada Day 🇨🇦</b>'],
			['11-11', '<b>Remembrance Day 🌺</b>'],
			['02-14', '<b>Valentine\'s Day ❤️</b>'],
			['03-17', '<b>Saint Patrick\'s Day ☘️</b>']
			];

		// Get the current year
		$currentYear = (int) date('Y');

		// Loop through the next three years
		for ($year = $currentYear; $year <= $currentYear + 2; $year++) {
			foreach ($holidays as $holiday) {
				list($date, $content) = $holiday;
				$dateParts = explode('-', $date);
				$month = str_pad($dateParts[0], 2, '0', STR_PAD_LEFT);
				$day = str_pad($dateParts[1], 2, '0', STR_PAD_LEFT);

				$option_name = "simple_calendar_{$year}_{$month}_{$day}";

				// Only insert the holiday if there is no existing content
				if (empty(get_option($option_name))) {
					update_option($option_name, wp_kses_post($content));
				}
			}
		}
	}

    public function get_current_date() {
        $timezone_string = get_option('timezone_string');
        if (!$timezone_string) {
            $offset  = get_option('gmt_offset');
            $hours   = (int) $offset;
            $minutes = abs(($offset - floor($offset)) * 60);
            $offset  = sprintf('%+03d:%02d', $hours, $minutes);
            $timezone_string = $offset;
        }
        $timezone = new DateTimeZone($timezone_string);
        return new DateTime('now', $timezone);
    }

    public function calendar_template($year, $month) {
        $firstDayOfMonth = DateTime::createFromFormat('Y-m-d', "{$year}-{$month}-01");
        $numDaysInMonth = $firstDayOfMonth->format('t');
        $dayOfWeekFirstDay = $firstDayOfMonth->format('w');
        $monthName = $firstDayOfMonth->format('F');
		
		// Get the current date for comparison
		$currentDate = new DateTime();
		$currentDay = $currentDate->format('j');
		$currentMonth = $currentDate->format('m');
		$currentYear = $currentDate->format('Y');
        
        $calendar = "<div class='cs-calendar'><table>\n<tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr>\n";
		
        // Create the first row conditionally only if there are leading empty cells
		$openRow = false;
		if ($dayOfWeekFirstDay > 0) {
			$calendar .= "<tr>";
			$openRow = true;
			// Add empty cells for days of the previous month
			for ($i = 0; $i < $dayOfWeekFirstDay; $i++) {
				$calendar .= "<td class='inactive'></td>";
			}
		}
        
        for ($day = 1; $day <= $numDaysInMonth; $day++) {
            if (($day + $dayOfWeekFirstDay - 1) % 7 == 0) {
                $calendar .= "</tr>\n<tr>";
            }
            $option_name = "simple_calendar_{$year}_{$month}_".str_pad($day, 2, '0', STR_PAD_LEFT);
            $content = get_option($option_name, '');
			
            // Determine the appropriate classes
			$classes = [];
			$classes[] = empty($content) ? 'no-event' : 'has-event';
			if ($day == $currentDay && $month == $currentMonth && $year == $currentYear) {
				$classes[] = 'today';
			}

			$classString = implode(' ', $classes);

			// Generate the day cell
			$calendar .= "<td class='{$classString}'><span class='date'>{$day}</span>" . (!empty($content) ? " <div class='cs-event'>{$content}</div>" : '') . "</td>";
		}
		
		// Close the last row if it's open
		if ($openRow) {
			$calendar .= "</tr>\n";
		}
        
        $calendar .= "</table></div>";
        return $calendar;
    }

    public function generate_calendar($atts = []) {
		$atts = shortcode_atts(['hide_title' => false, 'hide_nav' => false], $atts, 'simple_calendar');

		// Get the selected year and month, defaulting to the current year and month if not specified
		$year = isset($_GET['cs-year']) ? intval($_GET['cs-year']) : date('Y');
		$month = isset($_GET['cs-month']) ? str_pad(intval($_GET['cs-month']), 2, '0', STR_PAD_LEFT) : date('m');

		$output = '<div class="cs-wrap">';

		// Display the calendar title if not hidden
		if (!$atts['hide_title']) {
			$monthName = DateTime::createFromFormat('!m', $month)->format('F');
			$output .= "<h2 class='cs-title'>Events for {$monthName}, {$year}</h2>";
		}

		if (!$atts['hide_nav']) {
			// Calculate previous and next month/year
			$prevDate = new DateTime("{$year}-{$month}-01");
			$prevDate->modify('-1 month');
			$prevMonth = $prevDate->format('m');
			$prevYear = $prevDate->format('Y');

			$nextDate = new DateTime("{$year}-{$month}-01");
			$nextDate->modify('+1 month');
			$nextMonth = $nextDate->format('m');
			$nextYear = $nextDate->format('Y');

			// Create Prev/Next links with the new year and month query structure
			$prev_month_link = add_query_arg(['cs-year' => $prevYear, 'cs-month' => $prevMonth]);
			$next_month_link = add_query_arg(['cs-year' => $nextYear, 'cs-month' => $nextMonth]);

			// Display Prev/Next month links
			$output .= "<div class='cs-nav'>
							<a href='{$prev_month_link}' id='prev-month'>Previous Month</a>
							<a href='{$next_month_link}' id='next-month'>Next Month</a>
						</div>";

			// Add the year and month dropdowns inside the form
			$output .= "<div class='cs-search'><form method='get' action=''>";

			// Year dropdown (previous 2 years to next 7 years)
			$output .= "<select id='cs-year' name='cs-year'>";
			$currentYear = date('Y');
			for ($i = $currentYear - 2; $i <= $currentYear + 2; $i++) {
				$selected = $i == $year ? 'selected' : '';
				$output .= "<option value='{$i}' {$selected}>{$i}</option>";
			}
			$output .= "</select>";

			// Month dropdown (January to December)
			$output .= "<select id='cs-month' name='cs-month'>";
			for ($m = 1; $m <= 12; $m++) {
				$monthValue = str_pad($m, 2, '0', STR_PAD_LEFT);
				$monthName = DateTime::createFromFormat('!m', $monthValue)->format('F');
				$selected = $monthValue == $month ? 'selected' : '';
				$output .= "<option value='{$monthValue}' {$selected}>{$monthName}</option>";
			}
			$output .= "</select>";

			// Submit button
			$output .= "<button class='button' type='submit'>Go</button></form></div>";
		}
		
		// Generate the calendar with the given year and month
		$output .= "<div class='clear'></div>";
		$output .= $this->calendar_template($year, $month);
		
		$output .= '</div>';

		return $output;
	}
	
	public function simple_event_shortcode($atts = []) {
		// Merge user provided attributes and defaults
		$atts = shortcode_atts([
			'offset' => '0'
		], $atts, 'simple_event');

		// Calculate the desired date
		$timestamp = current_time('timestamp') + intval($atts['offset']) * DAY_IN_SECONDS;
		$year = date('Y', $timestamp);
		$month = date('m', $timestamp);
		$day = date('d', $timestamp);
		$fullmonth = date('M', $timestamp);

		// Fetch the event information
		$option_name = "simple_calendar_{$year}_{$month}_{$day}";
		$event = get_option($option_name, 'No events scheduled.');

		// Generate the output
		$output = "<div class='cs-event-single'><h4 class='cs-event-single-title'>Events for <strong>{$fullmonth} {$day}, {$year}</strong>:</h4><div class='cs-event-single-content'>{$event}</div></div>";

		return $output;
	}

}

// Initialize the plugin
new Simple_Calendar_Plugin();
?>
